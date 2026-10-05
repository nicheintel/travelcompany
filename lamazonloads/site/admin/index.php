<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$stats = [
    'New applications' => (int) db_val("SELECT COUNT(*) FROM applications WHERE status = 'new'"),
    'Members' => (int) db_val('SELECT COUNT(*) FROM users'),
    'Open job posts' => (int) db_val("SELECT COUNT(*) FROM jobs WHERE status = 'open'"),
    'Unread messages' => (int) db_val('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
];
$latest = db_all('SELECT a.*, u.name, u.email, j.title FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN jobs j ON j.id = a.job_id ORDER BY a.created_at DESC LIMIT 8');
$byEquip = db_all("SELECT equipment, COUNT(*) n FROM driver_profiles WHERE equipment <> '' GROUP BY equipment ORDER BY n DESC");

page_header('Admin');
admin_open('overview');
?>
<h1>Admin overview</h1>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?><div class="card stat"><small><?= e($label) ?></small><b><?= $n ?></b></div><?php endforeach; ?>
</div>
<div class="card pad">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><h3 class="mt-0 mb-0">Latest applications</h3><a href="<?= e(url('admin/applications.php')) ?>">See all</a></div>
  <?php if (!$latest): ?><div class="empty">No applications yet. Share your Careers page to start recruiting.</div><?php else: ?>
  <div class="table-wrap mt"><table>
    <thead><tr><th>Applicant</th><th>Opening</th><th>Applied</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($latest as $a): ?>
      <tr><td><a href="<?= e(url('admin/driver.php?id=' . (int) $a['user_id'])) ?>"><b><?= e($a['name']) ?></b></a><br><span class="muted"><?= e($a['email']) ?></span></td>
        <td><?= e(applicant_label($a)) ?></td><td><?= e(fmt_date($a['created_at'])) ?></td><td><?= status_badge($a['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php if ($byEquip): ?>
<div class="card pad">
  <h3 class="mt-0">Drivers by equipment</h3>
  <div class="tags"><?php foreach ($byEquip as $r): ?><span class="tag"><?= icon('truck') ?><?= e(EQUIPMENT[$r['equipment']] ?? $r['equipment']) ?>: <?= (int) $r['n'] ?></span><?php endforeach; ?></div>
</div>
<?php endif; ?>
<?php dash_close(); page_footer();
