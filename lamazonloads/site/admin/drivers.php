<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();

// Add member: create the account and email the sign-in details. If the email is already a member, just open their page.
$add = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'account_type' => 'driver', 'onboarded' => '', 'access' => ''];
$addErrors = [];
if (is_post() && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    foreach (['first_name' => 50, 'last_name' => 50, 'email' => 190, 'phone' => 25, 'account_type' => 30, 'onboarded' => 20, 'access' => 20] as $k => $max) {
        $add[$k] = post_line($k, $max);
    }
    $add['email'] = strtolower($add['email']);
    $add['phone'] = format_phone($add['phone']);
    if (!email_valid($add['email'])) {
        $addErrors[] = 'Please enter a valid email address.';
    } elseif ($have = db_one('SELECT id, name FROM users WHERE email = ?', [$add['email']])) {
        flash('info', $have['name'] . ' already has an account with ' . $add['email'] . '. No email was sent and their password wasn\'t changed. You can add their information and documents here.');
        redirect('admin/driver.php?id=' . (int) $have['id']);
    }
    if ($add['first_name'] === '') $addErrors[] = 'Please enter their first name.';
    if ($add['phone'] !== '' && !preg_match('/^[0-9+()\-. ]{7,25}$/', $add['phone'])) $addErrors[] = 'Please enter a valid phone number, or leave it empty.';
    if (!isset(ACCOUNT_TYPES[$add['account_type']])) $addErrors[] = 'Please choose what describes them best.';
    if (!isset(ONB_TRACKS[$add['onboarded']])) $add['onboarded'] = ''; // already onboarded with our team: the program
    if ($add['access'] !== 'moderator' || !is_full_admin()) $add['access'] = ''; // only admins add moderators
    if ($add['access'] !== '') $add['onboarded'] = ''; // staff don't onboard
    if (!$addErrors) {
        [$newId, $pass] = create_member($add, (int) $me['id']);
        if ($add['onboarded'] !== '') onboarding_mark_done($newId, $add['onboarded'], (int) $me['id']);
        if ($add['access'] === 'moderator') db_run("UPDATE users SET is_admin = 1, staff_role = 'moderator' WHERE id = ?", [$newId]);
        $new = db_one('SELECT * FROM users WHERE id = ?', [$newId]);
        if (send_member_welcome($new, $pass)) {
            flash('success', 'Account created for ' . $new['name'] . '. Their sign-in details were emailed to ' . $new['email'] . '.');
        } else {
            flash('error', "Account created for {$new['name']}, but the email couldn't be sent. Share this password with them privately: $pass (they'll choose their own when they sign in).", true);
        }
        redirect('admin/driver.php?id=' . $newId);
    }
}

$q = trim(as_str($_GET['q'] ?? ''));
$equip = as_str($_GET['equipment'] ?? '');
$onbF = as_str($_GET['onb'] ?? '');  // onboarded · not (yet) · progress · none
$loc = as_str($_GET['loc'] ?? '');   // "st:GA" (whole state) or "c:Atlanta, GA"
$type = as_str($_GET['type'] ?? ''); // what they told us they are, or "staff"
// "Drivers" is everyone who drives, owner-operators included; "Owner-operators" narrows it to those who own their vehicle
const TYPE_FILTERS = ['driver' => 'Drivers', 'owner_operator' => '· Owner-operators', 'dispatcher' => 'Dispatchers / support', 'recruiter' => 'Driver recruiters',
    'other' => 'Entrepreneurs / other', 'staff' => 'Staff (admins & moderators)'];
const ONB_FILTERS = ['onboarded' => 'Onboarded', 'not' => 'Not onboarded yet', 'progress' => '· In onboarding now', 'none' => '· Not started'];
$where = [];
$args = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ? OR p.home_zip LIKE ?)'; $ql = addcslashes($q, '%_\\'); array_push($args, "%$ql%", "%$ql%", "%$ql%", "%$ql%", "$ql%"); }
if (isset(EQUIPMENT[$equip])) { $where[] = 'p.equipment = ?'; $args[] = $equip; }
if ($type === 'staff') $where[] = 'u.is_admin = 1';
elseif ($type === 'driver') $where[] = "u.is_admin = 0 AND u.account_type IN ('driver', 'owner_operator')";
elseif (isset(ACCOUNT_TYPES[$type])) { $where[] = 'u.is_admin = 0 AND u.account_type = ?'; $args[] = $type; } // staff accounts also carry a type
$where[] = match ($onbF) {
    'onboarded' => "o.stage IN ('contract', 'done')",
    'not' => "(o.stage IS NULL OR o.stage IN ('documents', 'review', 'changes'))",
    'progress' => "o.stage IN ('documents', 'review', 'changes')",
    'none' => 'o.stage IS NULL',
    default => '1',
};
if (preg_match('/^st:([A-Z]{2})$/', $loc, $m) && isset(US_STATES[$m[1]])) { $where[] = 'u.city LIKE ?'; $args[] = '%, ' . $m[1]; }
elseif (str_starts_with($loc, 'c:')) { $where[] = 'u.city = ?'; $args[] = substr($loc, 2); }
if (isset(ONB_FILTERS[$onbF])) $where[] = 'u.is_admin = 0'; // staff don't onboard
$where = array_values(array_filter($where, fn ($w) => $w !== '1'));
$rows = db_all('SELECT u.*, p.equipment, p.home_zip, p.availability, o.stage onb_stage, (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) docs,
    (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) apps
    FROM users u LEFT JOIN driver_profiles p ON p.user_id = u.id LEFT JOIN onboarding o ON o.user_id = u.id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.created_at DESC LIMIT 500', $args);
// Location filter: the states and cities members actually live in, with counts
$byState = [];
foreach (db_all("SELECT city, COUNT(*) n FROM users WHERE city <> '' GROUP BY city ORDER BY city") as $c) {
    $st = substr((string) $c['city'], -2);
    if (isset(US_STATES[$st])) $byState[$st][] = $c;
}
ksort($byState);
$typeCounts = array_column(db_all("SELECT IF(is_admin = 1, 'staff', account_type) t, COUNT(*) n FROM users GROUP BY t"), 'n', 't');
$typeCounts['driver'] = ($typeCounts['driver'] ?? 0) + ($typeCounts['owner_operator'] ?? 0);
$onbCounts = db_one("SELECT SUM(o.stage IN ('contract', 'done')) yes, COUNT(*) - SUM(COALESCE(o.stage IN ('contract', 'done'), 0)) no FROM users u LEFT JOIN onboarding o ON o.user_id = u.id WHERE u.is_admin = 0");

page_header('Drivers & members');
admin_open('drivers');
?>
<?= admin_head('Drivers & members', 'Everyone with a LamazonLoads account. Tap a member to see their profile, documents and applications, or add someone yourself.',
    '<a class="btn btn-accent" href="#add-member" data-modal-open="add-member">' . icon('plus') . ' Add member</a>') ?>
<form method="get" action="<?= e(url('admin/drivers.php')) ?>" class="card pad mem-filters">
  <div class="mf-q"><label for="q">Search</label><div class="mf-qrow"><input id="q" name="q" type="search" placeholder="Name, email, phone, city or ZIP" value="<?= e($q) ?>"><button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button></div></div>
  <div><label for="type">Type</label><select id="type" name="type"><option value="">Everyone</option><?php foreach (TYPE_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?> (<?= (int) ($typeCounts[$k] ?? 0) ?>)</option><?php endforeach; ?></select></div>
  <div><label for="onb">Onboarding</label><select id="onb" name="onb"><option value="">Anyone</option><?php foreach (ONB_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $onbF === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label for="loc">Location</label><select id="loc" name="loc"><option value="">All locations</option>
    <?php foreach ($byState as $st => $cities): ?><optgroup label="<?= e(US_STATES[$st]) ?>"><option value="st:<?= e($st) ?>"<?= $loc === 'st:' . $st ? ' selected' : '' ?>>All of <?= e(US_STATES[$st]) ?> (<?= array_sum(array_column($cities, 'n')) ?>)</option>
      <?php foreach ($cities as $c): ?><option value="c:<?= e($c['city']) ?>"<?= $loc === 'c:' . $c['city'] ? ' selected' : '' ?>><?= e(substr((string) $c['city'], 0, -4)) ?> (<?= (int) $c['n'] ?>)</option><?php endforeach; ?></optgroup><?php endforeach; ?>
  </select></div>
  <div><label for="equipment">Equipment</label><select id="equipment" name="equipment"><option value="">Any equipment</option><?php foreach (EQUIPMENT as $k => $l): ?><option value="<?= e($k) ?>"<?= $equip === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
</form>
<p class="muted app-count"><b><?= count($rows) ?></b> member<?= count($rows) === 1 ? '' : 's' ?><?= $where ? ' match · <a href="' . e(url('admin/drivers.php')) . '">Clear filters</a>' : ' · <span class="onb-tally">' . icon('check') . (int) $onbCounts['yes'] . ' onboarded · ' . (int) $onbCounts['no'] . ' not yet</span>' ?></p>
<?php if (!$rows): ?><div class="card empty">No members found.</div><?php else: ?>
<section class="card panel">
  <div class="panel-body flush ml">
    <div class="ml-row ml-head" aria-hidden="true"><span>Member</span><span>Type &amp; city</span><span>Equipment</span><span>Onboarding</span><span class="ml-c">Docs</span><span class="ml-c">Applied</span><span class="ml-end">Joined</span></div>
    <?php foreach ($rows as $r): $nm = trim((string) $r['name']); ?>
      <a class="ml-row" href="<?= e(url('admin/driver.php?id=' . (int) $r['id'])) ?>">
        <span class="ml-who"><span class="ov-av" aria-hidden="true"><?= e(strtoupper(mb_substr($nm, 0, 1))) ?></span>
          <span><b><?= e($nm) ?><?= $r['is_admin'] ? ' <span class="badge badge-draft">' . e(STAFF_ROLES[staff_role($r)]) . '</span>' : '' ?><?= $r['added_by'] ? ' <span class="badge badge-staff">Added by staff</span>' : '' ?><?= !$r['is_admin'] && empty($r['email_verified_at']) ? ' <span class="badge badge-reviewing">Email not confirmed</span>' : '' ?></b>
          <small class="ml-contact"><span title="<?= e($r['email']) ?>"><?= e($r['email']) ?></span><?php if ($r['phone'] !== ''): ?><span><?= e($r['phone']) ?></span><?php endif; ?></small></span></span>
        <?php $tl = $r['is_admin'] ? STAFF_ROLES[staff_role($r)] : explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? '')[0]; ?>
        <span class="ml-type"><span class="ml-t<?= str_contains($tl, ' ') ? '' : ' nw' ?>"><?= e($tl) ?></span><small><?= $r['city'] !== '' ? icon('pin') . e($r['city']) : 'No city yet' ?></small></span>
        <span class="ml-eq"><?= isset(EQUIPMENT[$r['equipment'] ?? '']) ? e(EQUIPMENT[$r['equipment']]) : (($vl = user_vehicles_label($r)) !== '' ? '<span title="' . e($vl) . '">' . e($vl) . '</span>' : '<span class="muted">Not given</span>') ?><?php if ($r['home_zip']): ?><small>ZIP <?= e($r['home_zip']) ?><?= isset(AVAILABILITY[$r['availability'] ?? '']) ? ' · ' . e(explode(' (', AVAILABILITY[$r['availability']])[0]) : '' ?></small><?php endif; ?></span>
        <span class="ml-onb"><?= $r['is_admin'] ? '<span class="muted">Not needed</span>' : onboarding_badge($r['onb_stage']) ?></span>
        <span class="ml-n ml-docs<?= (int) $r['docs'] ? ' has' : '' ?>" title="Documents"><?= icon('file') ?><?= (int) $r['docs'] ?></span>
        <span class="ml-n ml-apps<?= (int) $r['apps'] ? ' has' : '' ?>" title="Applications"><?= icon('clipboard') ?><?= (int) $r['apps'] ?></span>
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
        <?php if (is_full_admin()): ?>
        <div class="full"><label for="am-access">Access</label><select id="am-access" name="access" data-access><option value="">Member: a driver or partner account</option><option value="moderator"<?= $add['access'] === 'moderator' ? ' selected' : '' ?>>Moderator: staff with day-to-day tools</option></select>
          <p class="hint">Moderators handle the inbox, applications, onboarding review, members and job posts. They can’t delete anything or change settings. To make someone an admin, open their page after you add them.</p></div>
        <?php endif; ?>
        <div class="full" data-onb-field><label for="am-onb">Onboarding</label><select id="am-onb" name="onboarded"><option value="">Not yet: they’ll onboard on the website</option>
          <?php foreach (ONB_TRACKS as $tk => $tl): ?><option value="<?= e($tk) ?>"<?= $add['onboarded'] === $tk ? ' selected' : '' ?>>Already onboarded: <?= e($tl) ?></option><?php endforeach; ?></select>
          <p class="hint">Choose “Already onboarded” for drivers who finished onboarding with our team.</p></div>
        <div class="full"><button class="btn btn-accent btn-block" type="submit"><?= icon('mail') ?> Create account &amp; email password</button></div>
      </form>
    </div>
  </div>
</div>
<?php dash_close(); page_footer();
