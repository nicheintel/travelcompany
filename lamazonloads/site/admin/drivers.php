<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();

// Add member: create the account and email the sign-in details. If the email is already a member, just open their page.
$add = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'account_type' => 'driver'];
$addErrors = [];
if (is_post() && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    foreach (['first_name' => 50, 'last_name' => 50, 'email' => 190, 'phone' => 25, 'account_type' => 30] as $k => $max) {
        $add[$k] = post($k, $max);
    }
    $add['email'] = strtolower($add['email']);
    $add['phone'] = format_phone($add['phone']);
    if (!filter_var($add['email'], FILTER_VALIDATE_EMAIL)) {
        $addErrors[] = 'Please enter a valid email address.';
    } elseif ($have = db_one('SELECT id, name FROM users WHERE email = ?', [$add['email']])) {
        flash('info', $have['name'] . ' already has an account with ' . $add['email'] . '. No email was sent and their password wasn\'t changed. You can add their information and documents here.');
        redirect('admin/driver.php?id=' . (int) $have['id']);
    }
    if ($add['first_name'] === '') $addErrors[] = 'Please enter their first name.';
    if ($add['phone'] !== '' && !preg_match('/^[0-9+()\-. ]{7,25}$/', $add['phone'])) $addErrors[] = 'Please enter a valid phone number, or leave it empty.';
    if (!isset(ACCOUNT_TYPES[$add['account_type']])) $addErrors[] = 'Please choose what describes them best.';
    if (!$addErrors) {
        [$newId, $pass] = create_member($add, (int) $me['id']);
        $new = db_one('SELECT * FROM users WHERE id = ?', [$newId]);
        if (send_member_welcome($new, $pass)) {
            flash('success', 'Account created for ' . $new['name'] . '. Their sign-in details were emailed to ' . $new['email'] . '.');
        } else {
            flash('error', "Account created for {$new['name']}, but the email couldn't be sent. Share this password with them privately: $pass (they'll choose their own when they sign in).");
        }
        redirect('admin/driver.php?id=' . $newId);
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$equip = (string) ($_GET['equipment'] ?? '');
$where = [];
$args = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR p.home_zip LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "$q%"); }
if (isset(EQUIPMENT[$equip])) { $where[] = 'p.equipment = ?'; $args[] = $equip; }
$rows = db_all('SELECT u.*, p.equipment, p.home_zip, p.availability, (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) docs,
    (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) apps
    FROM users u LEFT JOIN driver_profiles p ON p.user_id = u.id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.created_at DESC LIMIT 500', $args);

page_header('Drivers & members');
admin_open('drivers');
?>
<?= admin_head('Drivers & members', 'Everyone with a LamazonLoads account. Tap a member to see their profile, documents and applications, or add someone yourself.',
    '<a class="btn btn-accent" href="#add-member" data-modal-open="add-member">' . icon('plus') . ' Add member</a>') ?>
<form method="get" action="<?= e(url('admin/drivers.php')) ?>" class="card pad mem-filters">
  <div class="mf-q"><label for="q">Search</label><input id="q" name="q" type="search" placeholder="Name, email, phone or ZIP" value="<?= e($q) ?>"></div>
  <div><label for="equipment">Equipment</label><select id="equipment" name="equipment"><option value="">Any equipment</option><?php foreach (EQUIPMENT as $k => $l): ?><option value="<?= e($k) ?>"<?= $equip === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="mf-go"><button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button></div>
</form>
<p class="muted app-count"><b><?= count($rows) ?></b> member<?= count($rows) === 1 ? '' : 's' ?><?= $where ? ' match · <a href="' . e(url('admin/drivers.php')) . '">Clear search</a>' : '' ?></p>
<?php if (!$rows): ?><div class="card empty">No members found.</div><?php else: ?>
<section class="card panel">
  <div class="panel-body flush ml">
    <div class="ml-row ml-head" aria-hidden="true"><span>Member</span><span>Type</span><span>Equipment &amp; area</span><span>Docs</span><span>Applied</span><span>Joined</span><span></span></div>
    <?php foreach ($rows as $r): $nm = trim((string) $r['name']); ?>
      <a class="ml-row" href="<?= e(url('admin/driver.php?id=' . (int) $r['id'])) ?>">
        <span class="ml-who"><span class="ov-av" aria-hidden="true"><?= e(strtoupper(mb_substr($nm, 0, 1))) ?></span>
          <span><b><?= e($nm) ?><?= $r['is_admin'] ? ' <span class="badge badge-draft">Staff</span>' : '' ?><?= $r['added_by'] ? ' <span class="badge badge-staff">Added by staff</span>' : '' ?><?= !$r['is_admin'] && empty($r['email_verified_at']) ? ' <span class="badge badge-reviewing">Email not confirmed</span>' : '' ?></b>
          <small><?= e($r['email']) ?><?= $r['phone'] !== '' ? ' · ' . e($r['phone']) : '' ?></small></span></span>
        <span class="ml-type"><?= e(explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? '')[0]) ?></span>
        <span class="ml-eq"><?= isset(EQUIPMENT[$r['equipment'] ?? '']) ? e(EQUIPMENT[$r['equipment']]) : '<span class="muted">No profile yet</span>' ?><?php if ($r['home_zip']): ?><small>ZIP <?= e($r['home_zip']) ?><?= isset(AVAILABILITY[$r['availability'] ?? '']) ? ' · ' . e(explode(' (', AVAILABILITY[$r['availability']])[0]) : '' ?></small><?php endif; ?></span>
        <span class="ml-n<?= (int) $r['docs'] ? ' has' : '' ?>" title="Documents"><?= icon('file') ?><?= (int) $r['docs'] ?></span>
        <span class="ml-n<?= (int) $r['apps'] ? ' has' : '' ?>" title="Applications"><?= icon('clipboard') ?><?= (int) $r['apps'] ?></span>
        <span class="ml-date"><?= e(fmt_date($r['created_at'], 'M j, Y')) ?></span>
        <span class="ar-go"><?= icon('arrow') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="modal<?= $addErrors ? ' is-open' : '' ?>" id="add-member" data-modal data-modal-param="add" role="dialog" aria-modal="true" aria-labelledby="add-title"<?= $addErrors ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>
  <div class="modal-panel modal-sm">
    <header class="modal-head">
      <div><span class="eyebrow">New member</span><h2 id="add-title">Add a member</h2></div>
      <a class="modal-x" href="#" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <p class="muted">We'll create their account and email them a password. They'll choose their own the first time they sign in. If the email is already registered, you'll go straight to their page instead.</p>
      <?php if ($addErrors): ?><ul class="errors"><?php foreach ($addErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url('admin/drivers.php')) ?>" class="form-grid" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div><label for="am-first">First name</label><input id="am-first" name="first_name" type="text" maxlength="50" required autocomplete="off" value="<?= e($add['first_name']) ?>"></div>
        <div><label for="am-last">Last name</label><input id="am-last" name="last_name" type="text" maxlength="50" autocomplete="off" value="<?= e($add['last_name']) ?>"></div>
        <div class="full"><label for="am-email">Email</label><input id="am-email" name="email" type="email" maxlength="190" required autocomplete="off" value="<?= e($add['email']) ?>"></div>
        <div><label for="am-phone">Phone <span class="opt">(optional)</span></label><input id="am-phone" name="phone" type="tel" maxlength="25" autocomplete="off" placeholder="(555) 123-4567" value="<?= e($add['phone']) ?>"></div>
        <div><label for="account_type">I am a…</label><?= select_html('account_type', ACCOUNT_TYPES, $add['account_type']) ?></div>
        <div class="full"><button class="btn btn-accent btn-block" type="submit"><?= icon('mail') ?> Create account &amp; email password</button></div>
      </form>
    </div>
  </div>
</div>
<?php dash_close(); page_footer();
