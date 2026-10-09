<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Send the Professional Dispatch or Walmart onboarding email by hand: to a member, or to someone who hasn't signed up yet.
$me = require_admin();
$cities = array_column(db_all('SELECT city FROM walmart_routes ORDER BY active DESC, city'), 'city');
// The email type and city stay picked after each send, so the next one only needs the address (and a first name)
$last = (array) ($_SESSION['os_last'] ?? []);
$f = ['email' => '', 'first_name' => '', 'type' => isset(ONB_TRACKS[$last['type'] ?? '']) ? $last['type'] : 'dispatch', 'city' => (string) ($last['city'] ?? '')];
$mailSent = mail_sent_24h();
$mailLimit = mail_daily_limit();
if (email_valid($pre = strtolower(trim(as_str($_GET['email'] ?? ''))))) {
    $f['email'] = $pre; // "Send onboarding email" on a member's page or a record
    $f['first_name'] = mb_substr(trim(as_str($_GET['first'] ?? '')), 0, 60) ?: (string) (record_by_email($pre)['first_name'] ?? '');
}
$errors = [];
$preview = null;
$user = null;
// "Send to a list" (admins): paste first names and emails, check them, and the scheduled task sends them in the background
$mode = is_full_admin() && as_str($_GET['mode'] ?? $_POST['mode'] ?? '') === 'list' ? 'list' : 'one';
$L = ['type' => $f['type'], 'city' => $f['city'], 'list' => ''];
$listErrors = [];
$checked = null; // [ready, skipped] after "Check list"

if (is_post() && $mode === 'list') {
    csrf_check();
    $act = as_str($_POST['action'] ?? '');
    $back = 'admin/send-onboarding.php?mode=list';
    if ($act === 'check' || $act === 'queue') {
        $L['type'] = isset(ONB_TRACKS[$t = as_str($_POST['type'] ?? '')]) ? $t : '';
        $L['city'] = $L['type'] === 'walmart' ? post_line('city', 80) : '';
        $L['list'] = post('list', 200000);
        if ($L['type'] === '') $listErrors[] = 'Please choose which email to send.';
        if ($L['type'] === 'walmart' && !in_array($L['city'], $cities, true)) $listErrors[] = 'Please choose the city for the Walmart email.';
        if (trim($L['list']) === '') $listErrors[] = 'Please paste the first names and emails, one person per line.';
        if (!$listErrors) {
            [$people, $bad] = bulk_parse($L['list']);
            $checked = bulk_check($people, $bad);
            if ($act === 'queue' && $checked[0]) {
                foreach ($checked[0] as [$email, $first]) {
                    db_run('INSERT INTO onboarding_queue (email, first_name, type, city, added_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                        [$email, $first, $L['type'], $L['city'], $me['id']]);
                }
                $_SESSION['os_last'] = ['type' => $L['type'], 'city' => $L['city']];
                $n = count($checked[0]);
                flash('success', ($n === 1 ? '1 person' : number_format($n) . ' people') . ' added to the sending list. ' . ONB_TRACKS[$L['type']] . ' emails go out in the background, about '
                    . bulk_per_day() . ' a day between 9 am and 7 pm New York time.');
                redirect($back);
            }
            if ($act === 'queue') $listErrors[] = 'Nobody on this list can be emailed right now. See why on the right.';
        }
    } elseif ($act === 'pause' || $act === 'resume') {
        meta_set('bulk_paused', $act === 'pause' ? '1' : '0');
        flash('success', $act === 'pause' ? 'Sending is paused. Nobody else on the list gets an email until you resume.' : 'Sending resumed.');
        redirect($back);
    } elseif ($act === 'clear') {
        $n = db_run("UPDATE onboarding_queue SET status = 'removed', note = 'Removed before sending' WHERE status = 'waiting'");
        flash('success', $n === 1 ? '1 person was removed from the sending list.' : number_format($n) . ' people were removed from the sending list.');
        redirect($back);
    } elseif ($act === 'per_day') {
        $n = (int) post('per_day', 4);
        meta_set('bulk_per_day', (string) max(25, min(900, $n)));
        flash('success', 'The list now sends up to ' . bulk_per_day() . ' emails a day.');
        redirect($back);
    }
}

if (is_post() && $mode === 'one') {
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
    if (!$errors && ($stop = db_one('SELECT created_at, by_staff FROM followup_optout WHERE email = ?', [$f['email']])) && $stop['by_staff'] === null) {
        $errors[] = 'This person unsubscribed from our emails on ' . fmt_date((string) $stop['created_at']) . ', so it can’t be sent.';
    }
    $again = !$errors ? db_one('SELECT type, sent_at FROM onboarding_emails WHERE email = ? AND sent_at > NOW() - INTERVAL 7 DAY ORDER BY id DESC LIMIT 1', [$f['email']]) : null;
    if (!$errors && as_str($_POST['action'] ?? '') === 'send') {
        if ($again && empty($_POST['again'])) {
            $errors[] = 'You already sent them the ' . (ONB_TRACKS[$again['type']] ?? 'onboarding') . ' email on ' . fmt_date((string) $again['sent_at'], 'M j, g:i a') . '. Tick “Send it again” to send another.';
        } elseif ($mailSent >= $mailLimit - 20) {
            $errors[] = 'Your mailbox has sent ' . $mailSent . ' emails in the last 24 hours, and Hostinger’s limit is ' . number_format($mailLimit) . ' a day. Please wait a few hours so sign-up emails keep going out.';
        } elseif (rate_limited('onb_manual', (string) $me['id'], 200, 3600)) {
            $errors[] = 'You sent 200 onboarding emails in the last hour. Please take a short break before sending more.';
        } else {
            record_hit('onb_manual', (string) $me['id']);
            if (onboarding_email_send($f['email'], $f['type'], $f['city'], $f['first_name'], (int) $me['id'])) {
                $_SESSION['os_last'] = ['type' => $f['type'], 'city' => $f['city']];
                flash('success', ONB_TRACKS[$f['type']] . ' email sent to ' . $f['email'] . ($f['city'] !== '' ? ' (' . $f['city'] . ')' : '') . '.');
                redirect('admin/send-onboarding.php');
            }
            $errors[] = 'The email could not be sent' . (mail_last_error() !== '' ? ' (' . mail_last_error() . ')' : '') . (is_full_admin() ? '. Check Admin → Email check, then try again.' : '. Please try again, or ask an admin to check the email settings.');
        }
    }
    if (!$errors) {
        $preview = manual_onboarding_email($f['email'], $f['type'], $f['city'], $f['first_name'], $user, true);
    }
}

if ($mode === 'list') {
    $qs = db_one("SELECT COALESCE(SUM(status = 'waiting'), 0) waiting, COALESCE(SUM(status = 'sent'), 0) sent,
        COALESCE(SUM(status = 'sent' AND sent_at > NOW() - INTERVAL 24 HOUR), 0) sent24, COALESCE(SUM(status IN ('failed', 'skipped')), 0) notsent FROM onboarding_queue");
    $qRows = db_all("SELECT q.*, s.name AS sender FROM onboarding_queue q LEFT JOIN users s ON s.id = q.added_by WHERE q.status <> 'waiting'
        ORDER BY COALESCE(q.last_try_at, q.created_at) DESC, q.id DESC LIMIT 40");
    $qNext = db_all("SELECT * FROM onboarding_queue WHERE status = 'waiting' ORDER BY id LIMIT 5");
    $paused = meta_get('bulk_paused') === '1';
}
$recent = db_all('SELECT m.*, u.name, u.id AS uid, s.name AS sender, r.id AS rec_id FROM onboarding_emails m LEFT JOIN users u ON u.id = m.user_id
    LEFT JOIN users s ON s.id = m.sent_by LEFT JOIN member_records r ON r.email = m.email AND r.merged_user_id IS NULL ORDER BY m.id DESC LIMIT 30');

page_header('Send onboarding email');
admin_open('send');
?>
<?= admin_head('Send onboarding email', 'Send the Professional Dispatch or Walmart email yourself. Members get their personal upload link. Someone without an account gets a link to sign up, then goes straight to their documents.') ?>
<?php $waitingN = is_full_admin() ? (int) db_val("SELECT COUNT(*) FROM onboarding_queue WHERE status = 'waiting'") : 0; ?>
<?php if (is_full_admin()): ?>
<nav class="os-tabs" aria-label="How to send">
  <a href="<?= e(url('admin/send-onboarding.php')) ?>"<?= $mode === 'one' ? ' class="on" aria-current="page"' : '' ?>><?= icon('mail') ?>One email</a>
  <a href="<?= e(url('admin/send-onboarding.php?mode=list')) ?>"<?= $mode === 'list' ? ' class="on" aria-current="page"' : '' ?>><?= icon('users') ?>Send to a list<?= $waitingN ? ' <span class="os-tab-n">' . number_format($waitingN) . ' waiting</span>' : '' ?></a>
</nav>
<?php endif; ?>

<?php if ($mode === 'one'): ?>
<div class="os-layout">
<section class="card panel os-compose">
  <header class="panel-head"><h2><?= icon('send') ?>New email</h2></header>
  <form method="post" action="<?= e(url('admin/send-onboarding.php')) ?>" class="panel-body os-form" id="os-form" data-os-form novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <div><label for="os-email">Email address</label>
      <input id="os-email" name="email" type="email" maxlength="190" required value="<?= e($f['email']) ?>" placeholder="driver@email.com" autocomplete="off"<?= $f['email'] === '' ? ' autofocus' : '' ?>></div>
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
    <?php if (!empty($again)): ?><label class="check os-again"><input type="checkbox" name="again" value="1"> Send it again</label><?php endif; ?>
    <div class="os-acts">
      <button class="btn btn-ghost" type="submit" name="action" value="preview" data-busy="Loading preview…"><?= icon('eye') ?> Preview</button>
      <button class="btn btn-primary" type="submit" name="action" value="send"><?= icon('send') ?> Send email</button>
    </div>
    <?php $today = (int) db_val('SELECT COUNT(*) FROM onboarding_emails WHERE sent_at >= CURDATE()'); $pct = min(100, (int) round($mailSent / $mailLimit * 100)); ?>
    <div class="os-meter<?= $pct >= 85 ? ' is-high' : '' ?>">
      <p><span><b><?= number_format($today) ?></b> onboarding <?= $today === 1 ? 'email' : 'emails' ?> sent today</span>
        <span>Mailbox: <b><?= number_format($mailSent) ?></b> of <?= number_format($mailLimit) ?> in the last 24 hours</span></p>
      <span class="os-meter-bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></span>
      <p class="hint mb-0">Hostinger’s daily limit counts every email the website sends, including sign-up emails and reminders.</p>
    </div>
  </form>
</section>

<div class="os-side">
  <?php if ($preview): [$pSubject, , $pHtml] = $preview;
      $row = $user ? onboarding_row((int) $user['id']) : null;
      $body = preg_match('#<body[^>]*>(.*)</body>#s', $pHtml, $m) ? $m[1] : '';
      $body = str_replace(abs_url('assets/brand/email-logo.png'), url('assets/brand/email-logo.png'), $body); // the logo from this site ?>
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
<?php else: // Send to a list
    $perDay = bulk_per_day(); $waiting = (int) $qs['waiting'];
    $days = $waiting ? (int) ceil($waiting / $perDay) : 0;
    $reasons = $checked ? array_count_values(array_column($checked[1], 1)) : []; ?>
<div class="os-layout">
<section class="card panel os-compose">
  <header class="panel-head"><h2><?= icon('users') ?>Send to a list</h2></header>
  <form method="post" action="<?= e(url('admin/send-onboarding.php?mode=list')) ?>" class="panel-body os-form" id="os-list-form" data-os-form novalidate>
    <?= csrf_field() ?><input type="hidden" name="mode" value="list">
    <?php if ($listErrors): ?><ul class="errors"><?php foreach ($listErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <fieldset class="veh-pick os-types"><legend>Which email?</legend>
      <div class="veh-grid os-type-grid">
        <?php foreach (['dispatch' => ['truck', 'Box trucks, semis and vans'], 'walmart' => ['route', 'SUVs and other vehicles']] as $k => [$ic, $sub]): ?>
          <label class="veh-opt"><input type="radio" name="type" value="<?= e($k) ?>"<?= $L['type'] === $k ? ' checked' : '' ?> data-os-type>
            <span class="veh-card"><?= icon($ic, 'ic veh-ic') ?><span class="veh-name"><?= e(ONB_TRACKS[$k]) ?><small><?= e($sub) ?></small></span><span class="veh-tick" aria-hidden="true"><?= icon('check') ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div data-os-city<?= $L['type'] === 'walmart' ? '' : ' hidden' ?>><label for="os-lcity">Walmart route city</label>
      <select id="os-lcity" name="city"><option value="">Select a city…</option>
        <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"<?= $L['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="os-list">First names and emails</label>
      <textarea id="os-list" name="list" rows="12" spellcheck="false" placeholder="Marcus, marcus@gmail.com&#10;Ana Lopez, ana.lopez@yahoo.com&#10;tom.reed@gmail.com"><?= e($L['list']) ?></textarea>
      <p class="hint">One person per line: first name and email. You can also copy the two columns from Excel or Google Sheets. Up to 2,000 at a time.</p></div>
    <div class="os-acts os-acts-one">
      <button class="btn <?= $checked && $checked[0] ? 'btn-ghost' : 'btn-primary' ?>" type="submit" name="action" value="check" data-busy="Checking…"><?= icon('check') ?> Check list</button>
    </div>
  </form>
</section>

<div class="os-side">
  <?php if ($checked): [$ready, $skipped] = $checked; ?>
  <section class="card panel os-check" aria-labelledby="os-check-title">
    <header class="panel-head"><h2 id="os-check-title"><?= icon('clipboard') ?>Your list</h2><small><?= number_format(count($ready) + count($skipped)) ?> lines</small></header>
    <div class="panel-body">
      <div class="os-stats">
        <div class="is-ok"><b><?= number_format(count($ready)) ?></b><span>ready to send</span></div>
        <div><b><?= number_format(count($skipped)) ?></b><span>skipped</span></div>
      </div>
      <?php if ($reasons): ?><ul class="os-reasons"><?php foreach ($reasons as $why => $n): ?><li><span><?= e($why) ?></span><b><?= number_format($n) ?></b></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($ready): ?>
        <p class="os-plan"><?= icon('clock') ?><span>They get the <b><?= e(ONB_TRACKS[$L['type']]) ?></b> email<?= $L['city'] !== '' ? ' for ' . e($L['city']) : '' ?>, about <?= $perDay ?> a day between 9 am and 7 pm New York time<?= $waiting ? ', after the ' . number_format($waiting) . ' already waiting' : '' ?>.</span></p>
        <button class="btn btn-primary btn-block" type="submit" form="os-list-form" name="action" value="queue" data-busy="Adding…"><?= icon('send') ?> Send to <?= count($ready) === 1 ? '1 person' : number_format(count($ready)) . ' people' ?></button>
      <?php endif; ?>
      <?php if ($skipped): ?>
        <details class="os-skipped"><summary>See who’s skipped</summary>
          <ul><?php foreach (array_slice($skipped, 0, 200) as [$who, $why]): ?><li><span><?= e($who) ?></span><small><?= e($why) ?></small></li><?php endforeach; ?></ul></details>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="card panel os-queue" aria-labelledby="os-queue-title">
    <header class="panel-head"><h2 id="os-queue-title"><?= icon('send') ?>Sending list</h2>
      <span class="badge <?= $paused ? 'badge-reviewing' : ($waiting ? 'badge-open' : 'badge-draft') ?>"><?= $paused ? 'Paused' : ($waiting ? 'Sending' : 'Nothing waiting') ?></span></header>
    <div class="panel-body">
      <div class="os-stats os-stats-4">
        <div><b><?= number_format($waiting) ?></b><span>waiting</span></div>
        <div class="is-ok"><b><?= number_format((int) $qs['sent24']) ?></b><span>sent in 24 hours</span></div>
        <div><b><?= number_format((int) $qs['sent']) ?></b><span>sent in all</span></div>
        <div<?= (int) $qs['notsent'] ? ' class="is-warn"' : '' ?>><b><?= number_format((int) $qs['notsent']) ?></b><span>not sent</span></div>
      </div>
      <p class="os-plan"><?= icon('clock') ?><span><?php if ($paused): ?>Paused. Nobody else gets an email until you resume.
        <?php elseif ($waiting): ?>Up to <?= $perDay ?> a day, 9 am to 7 pm New York time. <?= $days <= 1 ? 'The rest should go out today or tomorrow.' : 'About ' . $days . ' days to finish.' ?>
        <?php else: ?>Paste a list on the left to start. Emails go out in the background, so you can close this page.<?php endif; ?></span></p>
      <div class="os-qacts">
        <form method="post" action="<?= e(url('admin/send-onboarding.php?mode=list')) ?>" class="os-perday"><?= csrf_field() ?><input type="hidden" name="mode" value="list"><input type="hidden" name="action" value="per_day">
          <label for="per_day">Emails a day</label><input id="per_day" name="per_day" type="number" min="25" max="900" step="25" value="<?= $perDay ?>"><button class="btn btn-ghost btn-sm" type="submit">Save</button></form>
        <?php if ($waiting || $paused): ?>
        <form method="post" action="<?= e(url('admin/send-onboarding.php?mode=list')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="mode" value="list"><input type="hidden" name="action" value="<?= $paused ? 'resume' : 'pause' ?>">
          <button class="btn <?= $paused ? 'btn-primary' : 'btn-ghost' ?> btn-sm" type="submit"><?= $paused ? 'Resume sending' : 'Pause' ?></button></form>
        <?php endif; ?>
        <?php if ($waiting): ?>
        <form method="post" action="<?= e(url('admin/send-onboarding.php?mode=list')) ?>" class="inline-form" data-confirm="Remove the <?= number_format($waiting) ?> people still waiting? They won’t get the email." data-confirm-ok="Remove them" data-confirm-danger><?= csrf_field() ?><input type="hidden" name="mode" value="list"><input type="hidden" name="action" value="clear">
          <button class="link-btn os-clear" type="submit">Remove the rest</button></form>
        <?php endif; ?>
      </div>
      <p class="hint mb-0">Your mailbox: <?= number_format($mailSent) ?> of <?= number_format($mailLimit) ?> emails in the last 24 hours. The list stops <?= BULK_KEEP_FREE ?> before the limit, so sign-up emails and the ones you send by hand always go out.</p>
    </div>
    <?php if ($qNext || $qRows): ?>
    <ul class="os-list os-qlist">
      <?php foreach ($qNext as $r): ?>
        <li class="is-next"><span class="os-when">Next<small><?= e(fmt_date((string) $r['created_at'], 'M j')) ?></small></span>
          <span class="os-to"><b><?= e($r['first_name'] !== '' ? (string) $r['first_name'] : (string) $r['email']) ?></b><small><?= e((string) $r['email']) ?></small></span>
          <span class="os-kind"><span class="mail-dot <?= e($r['type']) ?>"><?= e($r['type'] === 'walmart' ? 'Walmart' : 'Dispatch') ?></span><?php if ($r['city'] !== ''): ?><small><?= e((string) $r['city']) ?></small><?php endif; ?></span>
          <span class="os-state"><span class="muted">Waiting</span></span></li>
      <?php endforeach; ?>
      <?php foreach ($qRows as $r): $st = (string) $r['status']; ?>
        <li><span class="os-when"><?= e(fmt_date((string) ($r['last_try_at'] ?? $r['created_at']), 'M j')) ?><small><?= e(fmt_date((string) ($r['last_try_at'] ?? $r['created_at']), 'g:i a')) ?></small></span>
          <span class="os-to"><b><?= e($r['first_name'] !== '' ? (string) $r['first_name'] : (string) $r['email']) ?></b><small><?= e((string) $r['email']) ?></small></span>
          <span class="os-kind"><span class="mail-dot <?= e($r['type']) ?>"><?= e($r['type'] === 'walmart' ? 'Walmart' : 'Dispatch') ?></span><?php if ($r['city'] !== ''): ?><small><?= e((string) $r['city']) ?></small><?php endif; ?></span>
          <span class="os-state"><span class="q-<?= e($st) ?>"><?= e(['sent' => 'Sent', 'skipped' => 'Skipped', 'failed' => 'Couldn’t send', 'removed' => 'Removed'][$st] ?? ucfirst($st)) ?></span><?php if ($r['note'] !== '' && $st !== 'sent'): ?><small><?= e((string) $r['note']) ?></small><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
</div>
<?php endif; ?>
<?php dash_close(); page_footer();
