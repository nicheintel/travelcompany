<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        db_run('DELETE FROM partner_requests WHERE id = ?', [$id]);
        flash('success', 'Partner request deleted.');
        redirect('admin/partners.php');
    }
    $status = (string) ($_POST['status'] ?? '');
    if (isset(PARTNER_STATUSES[$status])) {
        db_run('UPDATE partner_requests SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?', [$status, post('admin_note', 3000), $id]);
        flash('success', 'Partner request updated.');
    }
    redirect('admin/partners.php?id=' . $id);
}

$id = (int) ($_GET['id'] ?? 0);
$req = $id ? db_one('SELECT * FROM partner_requests WHERE id = ?', [$id]) : null;
$filter = (string) ($_GET['status'] ?? '');
if (!isset(PARTNER_STATUSES[$filter])) {
    $filter = '';
}
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS n FROM partner_requests GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}

page_header('Partner requests');
admin_open('partners');

$badge = fn (string $s): string => '<span class="badge badge-' . e($s) . '">' . e(PARTNER_STATUSES[$s] ?? $s) . '</span>';

if ($req):
    $name = $req['first_name'] . ' ' . $req['last_name'];
    ?>
  <p><a href="<?= e(url('admin/partners.php')) ?>">&larr; All partner requests</a></p>
  <h1><?= e($req['company']) ?></h1>
  <p class="muted"><?= $badge($req['status']) ?> · Received <?= e(fmt_date($req['created_at'], 'M j, Y g:i a')) ?></p>
  <div class="pr-detail">
    <div class="card pad">
      <h2 style="font-size:1.2rem">Request</h2>
      <div class="table-wrap"><table class="kv">
        <tr><th>Contact</th><td><?= e($name) ?><?= $req['job_title'] !== '' ? ', ' . e($req['job_title']) : '' ?></td></tr>
        <tr><th>Phone</th><td><a href="<?= e(tel_href($req['phone'])) ?>"><?= e($req['phone']) ?></a></td></tr>
        <tr><th>Email</th><td><a href="mailto:<?= e($req['email']) ?>?subject=<?= e(rawurlencode('Your LamazonLoads partnership request')) ?>"><?= e($req['email']) ?></a></td></tr>
        <tr><th>Interested in</th><td><?= e(PARTNER_SERVICES[$req['service']] ?? $req['service']) ?></td></tr>
        <tr><th>Best time to call</th><td><?= e(PARTNER_TIMES[$req['best_time']] ?? 'Not given') ?></td></tr>
        <tr><th>Location</th><td><?= e($req['location'] !== '' ? $req['location'] : 'Not given') ?></td></tr>
        <tr><th>Deliveries per week</th><td><?= e(PARTNER_VOLUMES[$req['volume']] ?? 'Not given') ?></td></tr>
      </table></div>
      <h3 class="mt">Message</h3>
      <p class="mb-0"><?= $req['message'] ? nl2br(e($req['message'])) : '<span class="muted">No message.</span>' ?></p>
      <div class="row-actions mt">
        <a class="btn btn-primary btn-sm" href="<?= e(tel_href($req['phone'])) ?>"><?= icon('phone') ?> Call</a>
        <a class="btn btn-ghost btn-sm" href="mailto:<?= e($req['email']) ?>?subject=<?= e(rawurlencode('Your LamazonLoads partnership request')) ?>"><?= icon('mail') ?> Email</a>
      </div>
    </div>
    <div class="card pad">
      <h2 style="font-size:1.2rem">Follow-up</h2>
      <form method="post" action="<?= e(url('admin/partners.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $req['id'] ?>">
        <label for="status">Status</label>
        <select id="status" name="status"><?php foreach (PARTNER_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $req['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <label for="admin_note" class="mt">Private note <span class="opt">(only staff see this)</span></label>
        <textarea id="admin_note" name="admin_note" maxlength="3000" placeholder="Call notes, next steps, pricing…"><?= e((string) $req['admin_note']) ?></textarea>
        <button class="btn btn-primary btn-block mt" type="submit">Save</button>
      </form>
      <form method="post" action="<?= e(url('admin/partners.php')) ?>" data-confirm="Delete this partner request?" class="mt">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $req['id'] ?>"><input type="hidden" name="action" value="delete">
        <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Delete request</button>
      </form>
    </div>
  </div>
<?php else:
    $rows = $filter === ''
        ? db_all("SELECT * FROM partner_requests ORDER BY status = 'new' DESC, created_at DESC LIMIT 300")
        : db_all('SELECT * FROM partner_requests WHERE status = ? ORDER BY created_at DESC LIMIT 300', [$filter]);
    ?>
  <h1>Partner requests</h1>
  <p class="muted">Businesses that asked for a call on the <a href="<?= e(url('partners.php')) ?>">Partner with us</a> page. Each one is also emailed to you.</p>
  <div class="filters">
    <a href="<?= e(url('admin/partners.php')) ?>"<?= $filter === '' ? ' class="on"' : '' ?>>All (<?= array_sum($counts) ?>)</a>
    <?php foreach (PARTNER_STATUSES as $k => $l): ?><a href="<?= e(url('admin/partners.php?status=' . $k)) ?>"<?= $filter === $k ? ' class="on"' : '' ?>><?= e($l) ?> (<?= $counts[$k] ?? 0 ?>)</a><?php endforeach; ?>
  </div>
  <?php if (!$rows): ?>
    <div class="card empty"><?= $filter === '' ? 'No partner requests yet. They will show up here when a business asks for a call.' : 'Nothing here.' ?></div>
  <?php else: ?>
    <div class="pr-list">
      <?php foreach ($rows as $r): ?>
        <a class="card pr-row<?= $r['status'] === 'new' ? ' is-new' : '' ?>" href="<?= e(url('admin/partners.php?id=' . (int) $r['id'])) ?>">
          <span><b><?= e($r['company']) ?></b> · <?= e($r['first_name'] . ' ' . $r['last_name']) ?>
            <small><?= e(PARTNER_SERVICES[$r['service']] ?? $r['service']) ?><?= $r['location'] !== '' ? ' · ' . e($r['location']) : '' ?> · <?= e(fmt_date($r['created_at'], 'M j, g:i a')) ?></small></span>
          <?= $badge($r['status']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php dash_close(); page_footer();
