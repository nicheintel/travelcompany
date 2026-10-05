<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $status = post('status', 20);
    if (isset(APP_STATUSES[$status])) {
        $appId = (int) ($_POST['id'] ?? 0);
        db_run('UPDATE applications SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?', [$status, post('admin_note', 2000), $appId]);
        $closed = auto_close_job((int) db_val('SELECT job_id FROM applications WHERE id = ?', [$appId]));
        flash('success', 'Application updated.' . ($closed ? ' You have approved enough people, so the job post was closed automatically.' : ''));
    }
    $back = safe_next(post('back', 300));
    redirect($back === 'account.php' ? 'admin/applications.php' : $back);
}

$status = (string) ($_GET['status'] ?? '');
$job = (string) ($_GET['job'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$where = [];
$args = [];
if (isset(APP_STATUSES[$status])) { $where[] = 'a.status = ?'; $args[] = $status; }
if ($job !== '' && ctype_digit($job)) { $where[] = 'a.job_id = ?'; $args[] = (int) $job; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR p.home_zip LIKE ?)'; array_push($args, "%$q%", "%$q%", "$q%"); }
$apps = db_all('SELECT a.*, u.name, u.email, u.phone, j.title, p.equipment, p.home_zip, p.availability
    FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN jobs j ON j.id = a.job_id LEFT JOIN driver_profiles p ON p.user_id = a.user_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.created_at DESC LIMIT 300', $args);
$jobs = db_all('SELECT id, title FROM jobs ORDER BY title');
$self = 'admin/applications.php' . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . $_SERVER['QUERY_STRING'] : '');

page_header('Applications');
admin_open('applications');
?>
<h1>Applications</h1>
<form method="get" action="<?= e(url('admin/applications.php')) ?>" class="card pad form-grid" style="grid-template-columns:2fr 1fr 1.5fr auto;align-items:end">
  <div><label for="q">Search name, email or ZIP</label><input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">Any</option><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label for="job">Opening</label><select id="job" name="job"><option value="">Any</option><option value="0"<?= $job === '0' ? ' selected' : '' ?>>Driver network (general)</option><?php foreach ($jobs as $j): ?><option value="<?= (int) $j['id'] ?>"<?= $job === (string) $j['id'] ? ' selected' : '' ?>><?= e($j['title']) ?></option><?php endforeach; ?></select></div>
  <div><button class="btn btn-primary" type="submit">Filter</button></div>
</form>
<div class="card pad">
  <?php if (!$apps): ?><div class="empty">No applications match.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Applicant</th><th>Opening</th><th>Equipment · ZIP</th><th>Applied</th><th>Status &amp; note</th></tr></thead>
    <tbody><?php foreach ($apps as $a): ?>
      <tr>
        <td><a href="<?= e(url('admin/driver.php?id=' . (int) $a['user_id'])) ?>"><b><?= e($a['name']) ?></b></a><br><span class="muted"><?= e($a['email']) ?><br><?= e($a['phone']) ?></span>
          <?php if ($a['message']): ?><p class="hint" style="max-width:260px">“<?= e(mb_strimwidth($a['message'], 0, 160, '…')) ?>”</p><?php endif; ?>
          <?php if ($a['resume_doc_id']): ?><a class="hint" href="<?= e(url('doc.php?id=' . (int) $a['resume_doc_id'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?> Resume</a><?php endif; ?>
          <?php if ($a['auto_note'] !== ''): ?><p class="hint">⚡ <?= e($a['auto_note']) ?></p><?php endif; ?></td>
        <td><?= e(applicant_label($a)) ?></td>
        <td><?= e(EQUIPMENT[$a['equipment'] ?? ''] ?? '—') ?><br><span class="muted"><?= e($a['home_zip'] ?: '') ?></span></td>
        <td><?= e(fmt_date($a['created_at'])) ?></td>
        <td>
          <form method="post" action="<?= e(url('admin/applications.php')) ?>" style="display:grid;gap:6px;min-width:200px">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
            <select name="status" aria-label="Status"><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <input type="text" name="admin_note" maxlength="2000" placeholder="Staff note (private)" value="<?= e($a['admin_note'] ?? '') ?>" aria-label="Staff note">
            <button class="btn btn-ghost btn-sm" type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
