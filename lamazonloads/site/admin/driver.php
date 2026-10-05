<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) {
    redirect('admin/drivers.php');
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'admin' && $id !== (int) $me['id']) {
        $make = !empty($_POST['make']) ? 1 : 0;
        db_run('UPDATE users SET is_admin = ?, session_version = session_version + 1 WHERE id = ?', [$make, $id]);
        flash('success', $make ? $u['name'] . ' is now staff.' : $u['name'] . ' is no longer staff.');
    } elseif ($action === 'password') {
        // For members who forgot their password: staff set a temporary one and share it by phone.
        $temp = substr(strtr(base64_encode(random_bytes(9)), '+/', 'xy'), 0, 10);
        db_run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
        flash('info', "Temporary password for {$u['name']}: $temp (share it privately; they can change it in Account settings).");
    } elseif ($action === 'delete' && $id !== (int) $me['id']) {
        foreach (db_all('SELECT stored_name FROM documents WHERE user_id = ?', [$id]) as $d) {
            @unlink(dirname(__DIR__) . '/uploads/' . basename($d['stored_name']));
        }
        chat_delete_for_user($id);
        db_run('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', 'Member deleted.');
        redirect('admin/drivers.php');
    }
    redirect('admin/driver.php?id=' . $id);
}

$p = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$id]);
$docs = db_all('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC', [$id]);
$apps = db_all('SELECT a.*, j.title FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.user_id = ? ORDER BY a.created_at DESC', [$id]);
$steps = onboarding_steps($id);
$done = count(array_filter($steps, fn ($s) => $s[1]));
$self = 'admin/driver.php?id=' . $id;

page_header($u['name']);
admin_open('drivers');
?>
<a href="<?= e(url('admin/drivers.php')) ?>">&larr; All members</a>
<h1 class="mt-0" style="margin-top:10px"><?= e($u['name']) ?></h1>
<p class="muted"><?= e(ACCOUNT_TYPES[$u['account_type']] ?? '') ?> · joined <?= e(fmt_date($u['created_at'])) ?> · onboarding <?= $done ?>/<?= count($steps) ?></p>

<div class="grid grid-2">
  <div class="card pad">
    <h3 class="mt-0">Contact</h3>
    <p class="mb-0"><?= icon('mail') ?> <a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a><br><?= icon('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $u['phone'])) ?>"><?= e($u['phone']) ?></a></p>
  </div>
  <div class="card pad">
    <h3 class="mt-0">Equipment &amp; area</h3>
    <?php if (!$p): ?><p class="muted mb-0">Profile not filled in yet.</p><?php else: ?>
      <p class="mb-0"><b><?= e(EQUIPMENT[$p['equipment']] ?? '—') ?></b><?= $p['vehicle'] ? ' · ' . e($p['vehicle']) : '' ?><br>
      ZIP <?= e($p['home_zip'] ?: '—') ?><?= $p['service_radius'] ? ' · runs ' . e($p['service_radius']) : '' ?><br>
      <?= e(AVAILABILITY[$p['availability']] ?? '') ?><?= $p['years_experience'] ? ' · ' . e($p['years_experience']) . ' yrs experience' : '' ?></p>
    <?php endif; ?>
  </div>
</div>
<?php if ($p): ?>
<div class="card pad">
  <h3 class="mt-0">Business &amp; insurance</h3>
  <div class="job-meta" style="margin:0">
    <div><small>Company</small><b><?= e($p['company_name'] ?: '—') ?></b></div>
    <div><small>MC #</small><b><?= e($p['mc_number'] ?: '—') ?></b></div>
    <div><small>USDOT #</small><b><?= e($p['dot_number'] ?: '—') ?></b></div>
    <div><small>Insurance</small><b><?= e($p['insurance_provider'] ?: '—') ?></b></div>
    <div><small>Insurance expires</small><b<?= $p['insurance_expires'] && $p['insurance_expires'] < date('Y-m-d') ? ' style="color:var(--red)"' : '' ?>><?= e($p['insurance_expires'] ? fmt_date($p['insurance_expires']) : '—') ?></b></div>
  </div>
  <?php if ($p['about']): ?><p class="mt mb-0"><b>Notes:</b> <?= nl2br(e($p['about'])) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card pad">
  <h3 class="mt-0">Documents</h3>
  <?php if (!$docs): ?><p class="muted mb-0">No documents uploaded.</p><?php else: ?>
  <div class="table-wrap"><table><tbody><?php foreach ($docs as $d): ?>
    <tr><td><b><?= e(DOC_KINDS[$d['kind']] ?? $d['kind']) ?></b></td><td><a href="<?= e(url('doc.php?id=' . (int) $d['id'])) ?>" target="_blank" rel="noopener"><?= e($d['original_name']) ?></a></td><td><?= e(fmt_date($d['created_at'])) ?></td>
      <td><a class="btn btn-ghost btn-sm" href="<?= e(url('doc.php?id=' . (int) $d['id'] . '&download=1')) ?>"><?= icon('download') ?> Download</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="card pad">
  <h3 class="mt-0">Applications</h3>
  <?php if (!$apps): ?><p class="muted mb-0">No applications yet.</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Opening</th><th>Message</th><th>Status &amp; note</th></tr></thead><tbody><?php foreach ($apps as $a): ?>
    <tr><td><?= e(applicant_label($a)) ?><br><span class="muted"><?= e(fmt_date($a['created_at'])) ?></span></td><td style="max-width:320px"><?= nl2br(e($a['message'] ?? '')) ?></td>
      <td><form method="post" action="<?= e(url('admin/applications.php')) ?>" style="display:grid;gap:6px;min-width:200px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
        <select name="status" aria-label="Status"><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <input type="text" name="admin_note" maxlength="2000" placeholder="Staff note (private)" value="<?= e($a['admin_note'] ?? '') ?>" aria-label="Staff note">
        <button class="btn btn-ghost btn-sm" type="submit">Save</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="card pad">
  <h3 class="mt-0">Account actions</h3>
  <div class="row-actions">
    <form method="post" action="<?= e(url($self)) ?>" class="inline-form" data-confirm="Create a new temporary password for this member?"><?= csrf_field() ?><input type="hidden" name="action" value="password"><button class="btn btn-ghost btn-sm" type="submit">Reset password</button></form>
    <?php if ($id !== (int) $me['id']): ?>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin"><input type="hidden" name="make" value="<?= $u['is_admin'] ? '' : '1' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_admin'] ? 'Remove staff access' : 'Make staff (admin)' ?></button></form>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form" data-confirm="Delete this member, their documents and applications? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete member</button></form>
    <?php endif; ?>
  </div>
</div>
<?php dash_close(); page_footer();
