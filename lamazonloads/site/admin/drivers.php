<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
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
<h1>Drivers &amp; members</h1>
<form method="get" action="<?= e(url('admin/drivers.php')) ?>" class="card pad form-grid" style="grid-template-columns:2fr 1.5fr auto;align-items:end">
  <div><label for="q">Search name, email, phone or ZIP</label><input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
  <div><label for="equipment">Equipment</label><select id="equipment" name="equipment"><option value="">Any</option><?php foreach (EQUIPMENT as $k => $l): ?><option value="<?= e($k) ?>"<?= $equip === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><button class="btn btn-primary" type="submit">Search</button></div>
</form>
<div class="card pad">
  <?php if (!$rows): ?><div class="empty">No members found.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Type</th><th>Equipment</th><th>ZIP</th><th>Availability</th><th>Docs</th><th>Apps</th><th>Joined</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('admin/driver.php?id=' . (int) $r['id'])) ?>"><b><?= e($r['name']) ?></b></a><?= $r['is_admin'] ? ' <span class="badge badge-draft">Staff</span>' : '' ?><br><span class="muted"><?= e($r['email']) ?> · <?= e($r['phone']) ?></span></td>
        <td><?= e(explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? '')[0]) ?></td>
        <td><?= e(EQUIPMENT[$r['equipment'] ?? ''] ?? '—') ?></td>
        <td><?= e($r['home_zip'] ?? '') ?></td>
        <td><?= e(AVAILABILITY[$r['availability'] ?? ''] ?? '—') ?></td>
        <td><?= (int) $r['docs'] ?></td>
        <td><?= (int) $r['apps'] ?></td>
        <td><?= e(fmt_date($r['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
