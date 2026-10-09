<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Send the Professional Dispatch or Walmart onboarding email by hand: to a member, or to someone who hasn't signed up yet.
$me = require_admin();
$cities = array_column(db_all('SELECT city FROM walmart_routes ORDER BY active DESC, city'), 'city');
$f = ['email' => '', 'first_name' => '', 'type' => 'dispatch', 'city' => ''];
if (email_valid($pre = strtolower(trim(as_str($_GET['email'] ?? ''))))) {
    $f['email'] = $pre; // "Send onboarding email" on a member's page or a record
    $f['first_name'] = mb_substr(trim(as_str($_GET['first'] ?? '')), 0, 60) ?: (string) (record_by_email($pre)['first_name'] ?? '');
}
$errors = [];
$preview = null;
$user = null;

if (is_post()) {
    csrf_check();
    $f['email'] = strtolower(post_line('email', 190));
    $f['first_name'] = post_line('first_name', 60);
    $f['type'] = isset(ONB_TRACKS[$t = as_str($_POST['type'] ?? '')]) ? $t : '';
    $f['city'] = $f['type'] === 'walmart' ? post_line('city', 80) : '';
    if (!email_valid($f['email'])) $errors[] = 'Please enter a valid email address.';
    if ($f['type'] === '') $errors[] = 'Please choose which email to send.';
    if ($f['type'] === 'walmart' && !in_array($f['city'], $cities, true)) $errors[] = 'Please choose the city for the Walmart email.';
    $user = $errors ? null : db_one('SELECT * FROM users WHERE email = ?', [$f['email']]);
    if ($user && $user['is_admin']) $errors[] = 'That email belongs to a staff account.';
    if (!$errors && as_str($_POST['action'] ?? '') === 'send') {
        if (rate_limited('onb_manual', (string) $me['id'], 60, 3600)) {
            $errors[] = 'You sent a lot of onboarding emails in the last hour. Please wait a little before sending more.';
        } else {
            record_hit('onb_manual', (string) $me['id']);
            if ($user) { // their onboarding page asks for this email's documents (unless staff already reviewed them)
                $row = onboarding_row((int) $user['id']);
                if (!$row) {
                    onboarding_start((int) $user['id'], $f['type']);
                } elseif ($row['track'] !== $f['type'] && in_array($row['stage'], ['documents', 'changes'], true)) {
                    db_run('UPDATE onboarding SET track = ?, updated_at = NOW() WHERE user_id = ?', [$f['type'], $user['id']]);
                }
            }
            [$subject, $text, $html] = manual_onboarding_email($f['email'], $f['type'], $f['city'], $f['first_name'], $user);
            if (send_mail($f['email'], $subject, $text, $html, support_email())) {
                db_run('INSERT INTO onboarding_emails (email, user_id, type, city, first_name, had_account, sent_by, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$f['email'], $user['id'] ?? null, $f['type'], $f['city'], $f['first_name'], $user ? 1 : 0, $me['id']]);
                flash('success', ONB_TRACKS[$f['type']] . ' email sent to ' . $f['email'] . ($f['city'] !== '' ? ' (' . $f['city'] . ')' : '') . '.');
                redirect('admin/send-onboarding.php');
            }
            $errors[] = 'The email could not be sent' . (mail_last_error() !== '' ? ' (' . mail_last_error() . ')' : '') . '. Check Admin → Email check, then try again.';
        }
    }
    if (!$errors) {
        $preview = manual_onboarding_email($f['email'], $f['type'], $f['city'], $f['first_name'], $user, true);
    }
}

$recent = db_all('SELECT m.*, u.name, u.id AS uid, s.name AS sender, r.id AS rec_id FROM onboarding_emails m LEFT JOIN users u ON u.id = m.user_id
    LEFT JOIN users s ON s.id = m.sent_by LEFT JOIN member_records r ON r.email = m.email AND r.merged_user_id IS NULL ORDER BY m.id DESC LIMIT 30');

page_header('Send onboarding email');
admin_open('send');
?>
<?= admin_head('Send onboarding email', 'Send the Professional Dispatch or Walmart email yourself. Members get their personal upload link. Someone without an account gets a link to sign up, then goes straight to their documents.') ?>

<div class="os-layout">
<section class="card panel os-compose">
  <header class="panel-head"><h2><?= icon('send') ?>New email</h2></header>
  <form method="post" action="<?= e(url('admin/send-onboarding.php')) ?>" class="panel-body os-form" id="os-form" data-os-form novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <div><label for="os-email">Email address</label>
      <input id="os-email" name="email" type="email" maxlength="190" required value="<?= e($f['email']) ?>" placeholder="driver@email.com" autocomplete="off"></div>
    <div><label for="os-first">First name <span class="opt">(optional)</span></label>
      <input id="os-first" name="first_name" type="text" maxlength="60" value="<?= e($f['first_name']) ?>" placeholder="e.g. Marcus" autocomplete="off">
      <p class="hint">Left empty, the email uses the name on their account, or “Hello there”.</p></div>
    <fieldset class="veh-pick os-types"><legend>Which email?</legend>
      <div class="veh-grid os-type-grid">
        <?php foreach (['dispatch' => ['truck', 'Box trucks, semis and vans'], 'walmart' => ['route', 'SUVs and other vehicles']] as $k => [$ic, $sub]): ?>
          <label class="veh-opt"><input type="radio" name="type" value="<?= e($k) ?>"<?= $f['type'] === $k ? ' checked' : '' ?> data-os-type>
            <span class="veh-card"><?= icon($ic, 'ic veh-ic') ?><span class="veh-name"><?= e(ONB_TRACKS[$k]) ?><small><?= e($sub) ?></small></span><span class="veh-tick" aria-hidden="true"><?= icon('check') ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div data-os-city<?= $f['type'] === 'walmart' ? '' : ' hidden' ?>><label for="os-city">Walmart route city</label>
      <?php if ($cities): ?>
        <select id="os-city" name="city"><option value="">Select a city…</option>
          <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"<?= $f['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <p class="hint">The city goes in the email’s subject and first paragraph. Add cities in <a href="<?= e(url('admin/walmart.php')) ?>">Walmart routes</a>.</p>
      <?php else: ?>
        <p class="hint">No Walmart cities yet. Add them in <a href="<?= e(url('admin/walmart.php')) ?>">Walmart routes</a> first.</p>
      <?php endif; ?>
    </div>
    <div class="os-acts">
      <button class="btn btn-ghost" type="submit" name="action" value="preview" data-busy="Loading preview…"><?= icon('eye') ?> Preview</button>
      <button class="btn btn-primary" type="submit" name="action" value="send"><?= icon('send') ?> Send email</button>
    </div>
  </form>
</section>

<div class="os-side">
  <?php if ($preview): [$pSubject, , $pHtml] = $preview;
      $row = $user ? onboarding_row((int) $user['id']) : null;
      $body = preg_match('#<body[^>]*>(.*)</body>#s', $pHtml, $m) ? $m[1] : '';
      $body = str_replace(rtrim((string) config('app_url'), '/') . url('assets/brand/email-logo.png'), url('assets/brand/email-logo.png'), $body); // the logo from this site ?>
  <section class="card panel os-preview" aria-labelledby="os-preview-title">
    <header class="panel-head"><h2 id="os-preview-title"><?= icon('eye') ?>Preview</h2>
      <button class="btn btn-primary btn-sm" type="submit" form="os-form" name="action" value="send"><?= icon('send') ?> Send this email</button></header>
    <div class="panel-body">
      <dl class="os-meta">
        <div><dt>To</dt><dd><?= e($f['email']) ?></dd></div>
        <div><dt>Subject</dt><dd><?= e($pSubject) ?></dd></div>
      </dl>
      <p class="os-who<?= $user && is_onboarded($row['stage'] ?? null) ? ' is-warn' : '' ?>"><?= icon($user ? 'user' : 'plus') ?><span><?php if (!$user): ?>
        <b>No account with this email yet<?= ($pRec = record_by_email($f['email'])) ? '. It’s on your records as ' . e(trim($pRec['first_name'] . ' ' . $pRec['last_name'])) : '' ?>.</b> The button opens sign-up with their email filled in. Once they create the account, they go straight to their <?= e(ONB_TRACKS[$f['type']]) ?> documents.
      <?php elseif (is_onboarded($row['stage'] ?? null)): ?>
        <b><?= e((string) $user['name']) ?> is already onboarded.</b> You can still send it.
      <?php else: ?>
        <b><?= e((string) $user['name']) ?></b> is a member<?= $row ? ' (' . e(ONB_STAGES[$row['stage']] ?? $row['stage']) . ')' : '' ?>. The button opens their personal document upload.
        <?php if ($row && $row['track'] !== $f['type'] && in_array($row['stage'], ['documents', 'changes'], true)): ?>Their onboarding will switch to the <?= e(ONB_TRACKS[$f['type']]) ?> documents.<?php endif; ?>
      <?php endif; ?></span></p>
      <div class="os-mail" inert><?= $body ?></div>
    </div>
  </section>
  <?php endif; ?>

  <section class="card panel os-recent">
    <header class="panel-head"><h2><?= icon('clock') ?>Recently sent</h2><small><?= count($recent) ? 'Last ' . count($recent) : '' ?></small></header>
    <?php if (!$recent): ?>
      <div class="panel-body"><p class="muted mb-0">Emails you send here show up in this list.</p></div>
    <?php else: ?>
      <ul class="os-list">
        <?php foreach ($recent as $r): ?>
          <li>
            <span class="os-when"><?= e(fmt_date((string) $r['sent_at'], 'M j')) ?><small><?= e(fmt_date((string) $r['sent_at'], 'g:i a')) ?></small></span>
            <span class="os-to"><b><?= e($r['name'] !== null ? (string) $r['name'] : (string) $r['email']) ?></b><small><?= e($r['name'] !== null ? (string) $r['email'] : 'No account yet') ?></small></span>
            <span class="os-kind"><span class="mail-dot <?= e($r['type']) ?>"><?= e($r['type'] === 'walmart' ? 'Walmart' : 'Dispatch') ?></span><?php if ($r['city'] !== ''): ?><small><?= e((string) $r['city']) ?></small><?php endif; ?></span>
            <span class="os-state"><?php if ($r['uid'] !== null): ?><a href="<?= e(url('admin/driver.php?id=' . (int) $r['uid'])) ?>"><?= $r['joined_at'] ? 'Signed up ' . e(fmt_date((string) $r['joined_at'], 'M j')) : 'Member' ?></a><?php elseif ($r['rec_id'] !== null): ?><a href="<?= e(url('admin/record.php?id=' . (int) $r['rec_id'])) ?>">Waiting for sign-up</a><?php else: ?><span class="muted">Waiting for sign-up</span><?php endif; ?>
              <small>by <?= e(first_name((string) ($r['sender'] ?? 'Staff'))) ?></small></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
</div>
<?php dash_close(); page_footer();
