<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();

// Add a record: a driver your team knows who doesn't have an account yet. No account is made and nothing is emailed;
// when they sign up with this email and confirm it, the record moves onto their account (see record_merge()).
$rec = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'city' => '', 'account_type' => 'driver', 'vehicle' => '', 'vehicle_other' => '',
    'onboarded' => '', 'notes' => ''];
$recErrors = [];
$recDup = null;
// Add staff member: staff need a login, so this one creates the account and emails the sign-in details (admins only)
$staff = ['first_name' => '', 'last_name' => '', 'email' => '', 'role' => 'moderator'];
$staffErrors = [];
if (is_post() && in_array($_POST['action'] ?? '', ['add_record', 'add_staff'], true)) {
    csrf_check();
    if ($_POST['action'] === 'add_record') {
        [$recErrors, $recDup] = record_from_post($rec);
        if (!$recErrors && !$recDup) {
            db_run('INSERT INTO member_records (first_name, last_name, email, phone, city, account_type, vehicle, vehicle_other, onboarded, notes, added_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [$rec['first_name'], $rec['last_name'], $rec['email'], $rec['phone'], $rec['city'],
                $rec['account_type'], $rec['vehicle'], $rec['vehicle_other'], $rec['onboarded'], $rec['notes'], $me['id']]);
            $newId = (int) db()->lastInsertId();
            flash('success', trim($rec['first_name'] . ' ' . $rec['last_name']) . ' was added to your records. Nothing was emailed. When they sign up with ' . $rec['email'] . ', this record joins their account.');
            redirect('admin/record.php?id=' . $newId);
        }
    } else {
        require_full_admin_action('admin/drivers.php');
        foreach (['first_name' => 50, 'last_name' => 50, 'email' => 190, 'role' => 20] as $k => $max) {
            $staff[$k] = post_line($k, $max);
        }
        $staff['email'] = strtolower($staff['email']);
        if ($staff['first_name'] === '') $staffErrors[] = 'Please enter their first name.';
        if (!email_valid($staff['email'])) {
            $staffErrors[] = 'Please enter a valid email address.';
        } elseif ($have = db_one('SELECT id, name FROM users WHERE email = ?', [$staff['email']])) {
            $staffErrors[] = $have['name'] . ' already has an account with this email. Open their page and change “Staff access” there.';
        }
        if (!in_array($staff['role'], ['moderator', 'admin'], true)) $staffErrors[] = 'Please choose their access.';
        if (!$staffErrors) {
            [$newId, $pass] = create_staff($staff, (int) $me['id']);
            $new = db_one('SELECT * FROM users WHERE id = ?', [$newId]);
            if (send_member_welcome($new, $pass)) {
                flash('success', 'Staff account created for ' . $new['name'] . '. Their sign-in details were emailed to ' . $new['email'] . '.');
            } else {
                flash('error', "Staff account created for {$new['name']}, but the email couldn't be sent. Share this password with them privately: $pass (they'll choose their own when they sign in).", true);
            }
            redirect('admin/driver.php?id=' . $newId);
        }
    }
}

$q = trim(as_str($_GET['q'] ?? ''));
$equip = as_str($_GET['equipment'] ?? '');
$onbF = as_str($_GET['onb'] ?? '');  // onboarded · not (yet) · progress · none
$loc = as_str($_GET['loc'] ?? '');   // "st:GA" (whole state) or "c:Atlanta, GA"
$type = as_str($_GET['type'] ?? ''); // what they told us they are, or "staff"
$acct = as_str($_GET['acct'] ?? ''); // has an account · no account yet (your records)
const ACCT_FILTERS = ['yes' => 'Has an account', 'no' => 'No account yet'];
if (!isset(ACCT_FILTERS[$acct])) $acct = '';
// "Drivers" is everyone who drives, owner-operators included; "Owner-operators" narrows it to those who own their vehicle
const TYPE_FILTERS = ['driver' => 'Drivers', 'owner_operator' => '· Owner-operators', 'dispatcher' => 'Dispatchers / support', 'recruiter' => 'Driver recruiters',
    'other' => 'Entrepreneurs / other', 'staff' => 'Staff (admins & moderators)'];
const ONB_FILTERS = ['onboarded' => 'Onboarded', 'not' => 'Not onboarded yet', 'progress' => '· In onboarding now', 'none' => '· Not started'];
$where = [];
$args = [];
$ql = addcslashes($q, '%_\\');
// A phone number matches however it's typed: "8135550142" finds "(813) 555-0142"
$digits = preg_replace('/\D+/', '', $q);
$phoneDigits = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
$byPhone = strlen($digits) >= 4 && preg_match('/^[\d\s()+.\-]+$/', $q);
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ? OR p.home_zip LIKE ?' . ($byPhone ? ' OR ' . $phoneDigits('u.phone') . ' LIKE ?' : '') . ')';
    array_push($args, "%$ql%", "%$ql%", "%$ql%", "%$ql%", "$ql%"); if ($byPhone) $args[] = "%$digits%"; }
// Equipment: what's on their profile, or the vehicles they ticked when they signed up (new members have no profile yet)
const EQUIP_FILTERS = ['box_truck' => 'Box Truck (any size)', 'box_16' => '· Box Truck 16 ft', 'box_26' => '· Box Truck 20–26 ft', 'semi' => 'Semi Truck',
    'cargo_van' => 'Cargo Van', 'sprinter' => 'Sprinter Van', 'suv' => 'SUV', 'hotshot' => 'Hotshot / Pickup + Trailer', 'other' => 'Other'];
if ($equip === 'box_truck') { $where[] = "(p.equipment IN ('box_16', 'box_26') OR FIND_IN_SET('box_truck', u.vehicle))"; }
elseif (isset(EQUIP_FILTERS[$equip]) && isset(APPLY_VEHICLES[$equip])) { $where[] = '(p.equipment = ? OR FIND_IN_SET(?, u.vehicle))'; array_push($args, $equip, $equip); }
elseif (isset(EQUIP_FILTERS[$equip])) { $where[] = 'p.equipment = ?'; $args[] = $equip; }
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
if ($acct === 'no') $where[] = '0';
$where = array_values(array_filter($where, fn ($w) => $w !== '1'));
$filtered = $where || $acct !== '';
// Your records (drivers without an account yet), with the same filters. They have no profile, documents or applications.
$rw = ['r.merged_user_id IS NULL'];
$ra = [];
if ($q !== '') { $rw[] = "(CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR r.email LIKE ? OR r.phone LIKE ? OR r.city LIKE ?" . ($byPhone ? ' OR ' . $phoneDigits('r.phone') . ' LIKE ?' : '') . ')';
    array_push($ra, "%$ql%", "%$ql%", "%$ql%", "%$ql%"); if ($byPhone) $ra[] = "%$digits%"; }
if ($equip === 'box_truck') $rw[] = "FIND_IN_SET('box_truck', r.vehicle)";
elseif (isset(EQUIP_FILTERS[$equip]) && isset(APPLY_VEHICLES[$equip])) { $rw[] = 'FIND_IN_SET(?, r.vehicle)'; $ra[] = $equip; }
elseif (isset(EQUIP_FILTERS[$equip])) $rw[] = '0'; // sizes and hotshot come from a driver profile
if ($type === 'staff') $rw[] = '0';
elseif ($type === 'driver') $rw[] = "r.account_type IN ('driver', 'owner_operator')";
elseif (isset(ACCOUNT_TYPES[$type])) { $rw[] = 'r.account_type = ?'; $ra[] = $type; }
$rw[] = match ($onbF) { 'onboarded' => "r.onboarded <> ''", 'not', 'none' => "r.onboarded = ''", 'progress' => '0', default => '1' };
if (preg_match('/^st:([A-Z]{2})$/', $loc, $m) && isset(US_STATES[$m[1]])) { $rw[] = 'r.city LIKE ?'; $ra[] = '%, ' . $m[1]; }
elseif (str_starts_with($loc, 'c:')) { $rw[] = 'r.city = ?'; $ra[] = substr($loc, 2); }
if ($acct === 'yes') $rw[] = '0';
$records = db_all('SELECT r.* FROM member_records r WHERE ' . implode(' AND ', $rw) . ' ORDER BY r.created_at DESC LIMIT 500', $ra);
// Today's sign-ups (New York time, the site's clock) have their own panel above the table and join the table after midnight.
// The filters apply to both, so together they always show every match.
$from = ' FROM users u LEFT JOIN driver_profiles p ON p.user_id = u.id LEFT JOIN onboarding o ON o.user_id = u.id WHERE ';
$sqlWhere = fn (string ...$extra) => implode(' AND ', array_merge($where, $extra));
$rows = db_all('SELECT u.*, p.equipment, p.home_zip, p.availability, o.stage onb_stage, (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) docs,
    (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) apps'
    . $from . $sqlWhere('(u.is_admin = 1 OR u.created_at < CURDATE())') . ' ORDER BY u.created_at DESC LIMIT 500', $args);
$showToday = $type !== 'staff' && $acct !== 'no'; // staff and records aren't sign-ups
$newToday = $showToday ? db_all('SELECT u.*, p.equipment' . $from . $sqlWhere('u.is_admin = 0', 'u.created_at >= CURDATE()') . ' ORDER BY u.created_at DESC', $args) : [];
$todayAll = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND created_at >= CURDATE()');
$perDay = $showToday ? array_column(db_all('SELECT DATE(u.created_at) d, COUNT(*) n' . $from . $sqlWhere('u.is_admin = 0', 'u.created_at >= CURDATE() - INTERVAL 13 DAY') . ' GROUP BY d', $args), 'n', 'd') : [];
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = (int) ($perDay[$d] ?? 0);
}
// Location filter: the states and cities members actually live in, with counts
$byState = [];
foreach (db_all("SELECT city, COUNT(*) n FROM (SELECT city FROM users UNION ALL SELECT city FROM member_records WHERE merged_user_id IS NULL) x
    WHERE city <> '' GROUP BY city ORDER BY city") as $c) {
    $st = substr((string) $c['city'], -2);
    if (isset(US_STATES[$st])) $byState[$st][] = $c;
}
ksort($byState);
$typeCounts = array_column(db_all("SELECT IF(is_admin = 1, 'staff', account_type) t, COUNT(*) n FROM users GROUP BY t"), 'n', 't');
foreach (db_all('SELECT account_type t, COUNT(*) n FROM member_records WHERE merged_user_id IS NULL GROUP BY t') as $c) {
    $typeCounts[$c['t']] = ($typeCounts[$c['t']] ?? 0) + (int) $c['n'];
}
$typeCounts['driver'] = ($typeCounts['driver'] ?? 0) + ($typeCounts['owner_operator'] ?? 0);
$onbCounts = db_one("SELECT SUM(o.stage IN ('contract', 'done')) yes, COUNT(*) - SUM(COALESCE(o.stage IN ('contract', 'done'), 0)) no FROM users u LEFT JOIN onboarding o ON o.user_id = u.id WHERE u.is_admin = 0 AND u.created_at < CURDATE()");
$recCounts = db_one("SELECT COUNT(*) n, COALESCE(SUM(onboarded <> ''), 0) yes FROM member_records WHERE merged_user_id IS NULL");
$onbCounts['yes'] = (int) $onbCounts['yes'] + (int) $recCounts['yes'];
$onbCounts['no'] = (int) $onbCounts['no'] + (int) $recCounts['n'] - (int) $recCounts['yes'];
// One list: accounts and records together, newest first
$list = array_merge(array_map(fn ($r) => $r + ['kind' => 'member'], $rows), array_map(fn ($r) => $r + ['kind' => 'record'], $records));
usort($list, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
$list = array_slice($list, 0, 500);

page_header('Drivers & members');
admin_open('drivers');
?>
<?= admin_head('Drivers & members', 'Everyone with a LamazonLoads account, plus your records of drivers who haven’t signed up yet. Tap someone to see their details.',
    (is_full_admin() ? '<a class="btn btn-ghost" href="#add-staff" data-modal-open="add-staff">' . icon('shield') . ' Add staff member</a>' : '')
    . '<a class="btn btn-accent" href="#add-record" data-modal-open="add-record">' . icon('plus') . ' Add a record</a>') ?>
<form method="get" action="<?= e(url('admin/drivers.php')) ?>" class="card pad mem-filters">
  <div class="mf-q"><label for="q">Search</label><div class="mf-qrow"><input id="q" name="q" type="search" placeholder="Name, email, phone, city or ZIP" value="<?= e($q) ?>"><button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button></div></div>
  <div><label for="acct">Account</label><select id="acct" name="acct"><option value="">Everyone</option><?php foreach (ACCT_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $acct === $k ? ' selected' : '' ?>><?= e($l) ?><?= $k === 'no' ? ' (' . (int) $recCounts['n'] . ')' : '' ?></option><?php endforeach; ?></select></div>
  <div><label for="type">Type</label><select id="type" name="type"><option value="">Everyone</option><?php foreach (TYPE_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?> (<?= (int) ($typeCounts[$k] ?? 0) ?>)</option><?php endforeach; ?></select></div>
  <div><label for="onb">Onboarding</label><select id="onb" name="onb"><option value="">Anyone</option><?php foreach (ONB_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $onbF === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label for="loc">Location</label><select id="loc" name="loc"><option value="">All locations</option>
    <?php foreach ($byState as $st => $cities): ?><optgroup label="<?= e(US_STATES[$st]) ?>"><option value="st:<?= e($st) ?>"<?= $loc === 'st:' . $st ? ' selected' : '' ?>>All of <?= e(US_STATES[$st]) ?> (<?= array_sum(array_column($cities, 'n')) ?>)</option>
      <?php foreach ($cities as $c): ?><option value="c:<?= e($c['city']) ?>"<?= $loc === 'c:' . $c['city'] ? ' selected' : '' ?>><?= e(substr((string) $c['city'], 0, -4)) ?> (<?= (int) $c['n'] ?>)</option><?php endforeach; ?></optgroup><?php endforeach; ?>
  </select></div>
  <div><label for="equipment">Equipment</label><select id="equipment" name="equipment"><option value="">Any equipment</option><?php foreach (EQUIP_FILTERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $equip === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
</form>
<?php if ($showToday): $todayN = count($newToday); $peak = max($days); $total14 = array_sum($days); $todayKey = date('Y-m-d'); $peakDay = $peak > 0 ? array_search($peak, $days, true) : null; ?>
<section class="card nt-panel" aria-labelledby="nt-title">
  <div class="nt-main">
    <header class="nt-head">
      <h2 id="nt-title">New today <span class="nt-count<?= $todayN ? '' : ' is-zero' ?>"><?= $todayN ?></span></h2>
      <p><?= e(date('l, F j')) ?> · New York time. <?= $filtered && $newToday ? '<b>' . $todayN . ' of ' . $todayAll . '</b> sign-ups today match your filters.' : 'Today\'s sign-ups move to the table below after midnight.' ?></p>
    </header>
    <?php if (!$newToday): ?>
      <p class="nt-empty"><?= icon('clock') ?><span><?= $filtered && $todayAll ? ($todayAll === 1 ? 'Today\'s one sign-up doesn\'t match your filters.' : 'None of today\'s ' . $todayAll . ' sign-ups match your filters.') : 'No new sign-ups yet today.' ?></span></p>
    <?php else: ?>
      <div class="nt-list">
        <?php foreach ($newToday as $r): $vl = isset(EQUIPMENT[$r['equipment'] ?? '']) ? EQUIPMENT[$r['equipment']] : user_vehicles_label($r); ?>
          <a class="nt-row" href="<?= e(url('admin/driver.php?id=' . (int) $r['id'])) ?>">
            <span class="nt-time"><?= e(date('g:i a', strtotime((string) $r['created_at']))) ?></span>
            <span class="nt-who"><b><?= e(trim((string) $r['name'])) ?><?= $r['added_by'] ? ' <span class="badge badge-staff">Added by staff</span>' : '' ?><?= empty($r['email_verified_at']) ? ' <span class="badge badge-reviewing">Email not confirmed</span>' : '' ?></b>
              <small class="nt-contact"><span title="<?= e($r['email']) ?>"><?= e($r['email']) ?></span><?php if ($r['phone'] !== ''): ?><span><?= e($r['phone']) ?></span><?php endif; ?></small></span>
            <span class="nt-type"><span><?= e(explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? '')[0]) ?></span><small><?= $r['city'] !== '' ? e($r['city']) : 'No city yet' ?></small></span>
            <span class="nt-veh"><?= $vl !== '' ? implode(', ', array_map(fn ($v) => '<span>' . e($v) . '</span>', explode(', ', $vl))) : '<span class="muted">No vehicle given</span>' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <figure class="nt-chart" aria-labelledby="nt-chart-title">
    <figcaption id="nt-chart-title"><b>Sign-ups per day</b><small><?= $total14 ?> in the last 14 days</small><?= $filtered ? '<small class="nt-filt">Matching your filters</small>' : '' ?></figcaption>
    <div class="nt-plot">
      <?php foreach ($days as $d => $n): $label = date('D, M j', strtotime($d)) . ': ' . $n . ' sign-up' . ($n === 1 ? '' : 's'); ?>
        <div class="nt-day<?= $d === $todayKey ? ' is-today' : '' ?>" title="<?= e($label) ?>">
          <?php if ($n > 0 && ($d === $todayKey || $d === $peakDay)): ?><span class="nt-val" aria-hidden="true"><?= $n ?></span><?php endif; ?>
          <span class="nt-bar" style="height: <?= $peak > 0 ? round($n / $peak * 100, 1) : 0 ?>%"></span>
          <span class="sr-only"><?= e($label) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="nt-axis" aria-hidden="true"><?php foreach ($days as $d => $n): ?><span<?= $d === $todayKey ? ' class="is-today"' : '' ?>><?= e(date('j', strtotime($d))) ?></span><?php endforeach; ?></div>
    <div class="nt-range" aria-hidden="true"><span><?= e(date('M j', strtotime(array_key_first($days)))) ?></span><span>Today</span></div>
  </figure>
</section>
<?php endif; ?>

<?php $nMem = count($rows); $nRec = count($records); $plural = fn (int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many); ?>
<p class="muted app-count"><?php if ($filtered): ?>Showing <b><?= e($plural($nMem, 'member', 'members')) ?></b><?= $nRec ? ' and <b>' . e($plural($nRec, 'record', 'records')) . '</b>' : '' ?> · <a href="<?= e(url('admin/drivers.php')) ?>">Clear filters</a><?= $newToday ? ' · ' . count($newToday) . ' more in New today above' : '' ?>
  <?php else: ?><span class="nowrap"><b><?= e($plural($nMem, 'member', 'members')) ?></b></span><?= $nRec ? ' · <span class="nowrap"><b>' . e($plural($nRec, 'record', 'records')) . '</b> with no account yet</span>' : '' ?> · <span class="onb-tally nowrap"><?= icon('check') ?><?= (int) $onbCounts['yes'] ?> onboarded · <?= (int) $onbCounts['no'] ?> not yet</span><?php endif; ?></p>
<?php if (!$list): ?><div class="card empty"><?= !$newToday ? ($filtered ? 'Nobody matches your filters.' : 'No members yet.') : ($filtered ? 'No members from before today match your filters.' : 'No members from before today yet.') ?></div><?php else: ?>
<section class="card panel">
  <div class="panel-body flush ml">
    <div class="ml-row ml-head" aria-hidden="true"><span>Member</span><span>Type &amp; city</span><span>Equipment</span><span>Onboarding</span><span class="ml-c">Docs</span><span class="ml-c">Applied</span><span class="ml-end">Joined</span></div>
    <?php foreach ($list as $r): if ($r['kind'] === 'record'): $nm = trim($r['first_name'] . ' ' . $r['last_name']); $vl = user_vehicles_label($r); ?>
      <a class="ml-row is-record" href="<?= e(url('admin/record.php?id=' . (int) $r['id'])) ?>">
        <span class="ml-who"><span class="ov-av is-rec" aria-hidden="true"><?= e(strtoupper(mb_substr($nm, 0, 1))) ?></span>
          <span><b><?= e($nm) ?> <span class="badge badge-noacct">No account yet</span></b>
          <small class="ml-contact"><span title="<?= e($r['email']) ?>"><?= e($r['email']) ?></span><?php if ($r['phone'] !== ''): ?><span><?= e($r['phone']) ?></span><?php endif; ?></small></span></span>
        <?php $tl = explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? 'Member')[0]; ?>
        <span class="ml-type"><span class="ml-t<?= str_contains($tl, ' ') ? '' : ' nw' ?>"><?= e($tl) ?></span><small><?= $r['city'] !== '' ? icon('pin') . e($r['city']) : 'No city yet' ?></small></span>
        <span class="ml-eq"><?= $vl !== '' ? '<span title="' . e($vl) . '">' . e($vl) . '</span>' : '<span class="muted">Not given</span>' ?></span>
        <span class="ml-onb"><?= onboarding_badge($r['onboarded'] !== '' ? 'done' : null) ?><?= $r['onboarded'] !== '' ? '<small>With our team</small>' : '' ?></span>
        <span class="ml-n ml-docs" title="Documents"><?= icon('file') ?>0</span>
        <span class="ml-n ml-apps" title="Applications"><?= icon('clipboard') ?>0</span>
        <span class="ml-date"><?= e(fmt_date($r['created_at'], 'M j, Y')) ?><small>Record added</small></span>
        <span class="ar-go"><?= icon('arrow') ?></span>
      </a>
    <?php else: $nm = trim((string) $r['name']); ?>
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
    <?php endif; endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="modal<?= $recErrors || $recDup ? ' is-open' : '' ?>" id="add-record" data-modal data-modal-param="add" role="dialog" aria-modal="true" aria-labelledby="add-title"<?= $recErrors || $recDup ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>
  <div class="modal-panel">
    <header class="modal-head">
      <div><span class="eyebrow">Your records</span><h2 id="add-title">Add a record</h2></div>
      <a class="modal-x" href="#" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <p class="muted">For drivers you already know. This saves their information only: no account is made and nothing is emailed. When they sign up with this email, the record joins their account.</p>
      <?php if ($recDup): ?><p class="rec-dup"><?= icon('info') ?><span><?= record_dup_html($recDup) ?></span></p><?php endif; ?>
      <?php if ($recErrors): ?><ul class="errors"><?php foreach ($recErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url('admin/drivers.php')) ?>" class="form-grid" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_record">
        <?= record_fields_html($rec, 'ar') ?>
        <div class="full"><button class="btn btn-accent btn-block" type="submit"><?= icon('plus') ?> Save record</button></div>
      </form>
    </div>
  </div>
</div>
<?php if (is_full_admin()): ?>
<div class="modal<?= $staffErrors ? ' is-open' : '' ?>" id="add-staff" data-modal data-modal-param="staff" role="dialog" aria-modal="true" aria-labelledby="staff-title"<?= $staffErrors ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>
  <div class="modal-panel">
    <header class="modal-head">
      <div><span class="eyebrow">Your team</span><h2 id="staff-title">Add a staff member</h2></div>
      <a class="modal-x" href="#" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <p class="muted">We’ll create their staff account and email them a temporary password. They choose their own the first time they sign in.</p>
      <?php if ($staffErrors): ?><ul class="errors"><?php foreach ($staffErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url('admin/drivers.php')) ?>" class="form-grid" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_staff">
        <div><label for="as-first">First name</label><input id="as-first" name="first_name" type="text" maxlength="50" required autocomplete="off" value="<?= e($staff['first_name']) ?>"></div>
        <div><label for="as-last">Last name</label><input id="as-last" name="last_name" type="text" maxlength="50" autocomplete="off" value="<?= e($staff['last_name']) ?>"></div>
        <div class="full"><label for="as-email">Email</label><input id="as-email" name="email" type="email" maxlength="190" required autocomplete="off" value="<?= e($staff['email']) ?>"></div>
        <div class="full"><label for="as-role">Access</label><select id="as-role" name="role">
          <option value="moderator"<?= $staff['role'] === 'moderator' ? ' selected' : '' ?>>Moderator: day-to-day tools</option>
          <option value="admin"<?= $staff['role'] === 'admin' ? ' selected' : '' ?>>Admin: everything, including settings</option></select>
          <p class="hint">Moderators handle the inbox, applications, onboarding review, members and job posts. They can’t delete anything or change settings.</p></div>
        <div class="full"><button class="btn btn-accent btn-block" type="submit"><?= icon('mail') ?> Create account &amp; email password</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php dash_close(); page_footer();
