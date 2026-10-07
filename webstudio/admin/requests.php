<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$status = param('status');
if ($status !== '' && !isset(STATUSES[$status])) $status = '';
$q = mb_substr(param('q'), 0, 100);
$page = max(1, (int) param('page', '1'));
$perPage = 20;

$where = [];
$args = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $args[] = $status;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR business_name LIKE ? OR ref LIKE ? OR message LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like, $like, $like, $like);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) db_value("SELECT COUNT(*) FROM requests $sqlWhere", $args);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all("SELECT * FROM requests $sqlWhere ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $args);

$counts = array_fill_keys(array_keys(STATUSES), 0);
foreach (db_all('SELECT status, COUNT(*) AS n FROM requests GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
$all = array_sum($counts);

$export = '<a class="btn btn-ghost" href="' . e(url('admin/export.php')) . '">' . icon('note') . ' Download CSV</a>';
admin_start('Requests', 'requests', $all ? $export : '');
?>
<div class="toolbar">
  <nav class="tabs" aria-label="Filter by status">
    <a href="<?= e(url('admin/requests.php', ['q' => $q])) ?>" class="<?= $status === '' ? 'is-active' : '' ?>">All <span><?= $all ?></span></a>
    <?php foreach (STATUSES as $key => $s): ?>
      <a href="<?= e(url('admin/requests.php', ['status' => $key, 'q' => $q])) ?>" class="<?= $status === $key ? 'is-active' : '' ?>"><?= e($s['label']) ?> <span><?= $counts[$key] ?></span></a>
    <?php endforeach ?>
  </nav>
  <form class="search" method="get" role="search">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
    <?= icon('search') ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, business, reference…" aria-label="Search requests">
  </form>
</div>

<?php if (!$rows): ?>
  <div class="card empty">
    <span class="empty-icon"><?= icon('inbox') ?></span>
    <?php if ($all === 0): ?>
      <h2>No requests yet</h2>
      <p class="muted">When someone fills in the "Request your website" form, it lands here.<br>Try it yourself to see how it works.</p>
      <a class="btn btn-primary" href="<?= e(url()) ?>#quote" target="_blank" rel="noopener"><?= icon('external') ?> Open the form</a>
    <?php else: ?>
      <h2>No matching requests</h2>
      <p class="muted">Try a different search or status.</p>
      <a class="btn btn-ghost" href="<?= e(url('admin/requests.php')) ?>">Clear filters</a>
    <?php endif ?>
  </div>
<?php else: ?>
  <div class="card list-card">
    <table class="table table-requests">
      <thead>
        <tr><th>Client</th><th>Business</th><th>Package</th><th>Budget</th><th>Received</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $href = url('admin/request.php', ['id' => $r['id']]); ?>
          <tr class="<?= $r['status'] === 'new' ? 'is-new' : '' ?>" data-href="<?= e($href) ?>">
            <td>
              <a class="client-cell" href="<?= e($href) ?>">
                <span class="avatar avatar-sm"><?= e(initials($r['name'])) ?></span>
                <span><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></span>
              </a>
            </td>
            <td><?= e($r['business_name'] ?: '—') ?><?php if ($r['business_type'] !== ''): ?><small class="cell-sub"><?= e($r['business_type']) ?></small><?php endif ?></td>
            <td><?= e($r['package_name'] ?: '—') ?></td>
            <td><?= $r['quoted_amount'] !== null ? '<b>' . e(money($r['quoted_amount'])) . '</b><small class="cell-sub">quoted</small>' : e($r['budget'] ?: '—') ?></td>
            <td><span title="<?= e(fmt_dt($r['created_at'])) ?>"><?= e(time_ago($r['created_at'])) ?></span><small class="cell-sub"><?= e($r['ref']) ?></small></td>
            <td><?= status_badge($r['status']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/requests.php', ['status' => $status, 'q' => $q, 'page' => $page - 1])) ?>"><?= icon('arrow-left') ?> Newer</a><?php endif ?>
      <span>Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/requests.php', ['status' => $status, 'q' => $q, 'page' => $page + 1])) ?>">Older <?= icon('arrow-right') ?></a><?php endif ?>
    </nav>
  <?php endif ?>
<?php endif ?>
<?php admin_end();
