<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'settings') {
        meta_set('walmart_on', empty($_POST['on']) ? '0' : '1');
        meta_set('walmart_start', post('start', 40));
        meta_set('walmart_rate', post('rate', 40));
        flash('success', 'Walmart route settings saved.');
    } elseif ($action === 'add') {
        $city = preg_replace('/\s+/', ' ', post('city', 80));
        if (mb_strlen($city) < 2) {
            flash('error', 'Please type a city, for example “Tampa, FL”.');
        } elseif (db_val('SELECT 1 FROM walmart_routes WHERE city = ?', [$city])) {
            flash('error', $city . ' is already on the list.');
        } else {
            db_run('INSERT INTO walmart_routes (city, active, created_at) VALUES (?, 1, NOW())', [$city]);
            flash('success', $city . ' added. Drivers can choose it now.');
        }
    } elseif ($action === 'toggle') {
        db_run('UPDATE walmart_routes SET active = 1 - active WHERE id = ?', [$id]);
    } elseif ($action === 'rename') {
        $city = preg_replace('/\s+/', ' ', post('city', 80));
        if (mb_strlen($city) >= 2) {
            db_run('UPDATE walmart_routes SET city = ? WHERE id = ?', [$city, $id]);
            flash('success', 'City updated.');
        }
    } elseif ($action === 'delete') {
        db_run('DELETE FROM walmart_routes WHERE id = ?', [$id]);
        flash('success', 'City removed. Applications that already chose it keep it.');
    }
    redirect('admin/walmart.php');
}
$s = walmart_settings();
$routes = db_all('SELECT r.*, (SELECT COUNT(*) FROM applications a WHERE a.walmart = 1 AND a.walmart_city = r.city) AS applicants FROM walmart_routes r ORDER BY r.active DESC, r.city');
$active = count(array_filter($routes, fn ($r) => (int) $r['active']));

page_header('Walmart routes');
admin_open('walmart');
?>
<h1>Walmart daily routes</h1>
<p class="muted">Drivers who tick “Interested in the Walmart daily route” when they apply choose one of these cities. SUV and other-vehicle drivers get the Walmart welcome email for their city.</p>

<div class="card pad">
  <h2 style="font-size:1.2rem">Program</h2>
  <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="form-grid wm-settings">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div><label for="start">Routes start in</label><input id="start" name="start" type="text" maxlength="40" value="<?= e($s['start']) ?>" placeholder="November"></div>
    <div><label for="rate">Pay rate</label><input id="rate" name="rate" type="text" maxlength="40" value="<?= e($s['rate']) ?>" placeholder="$275 per day"></div>
    <div class="full"><label class="check"><input type="checkbox" name="on" value="1"<?= $s['on'] ? ' checked' : '' ?>> Show the Walmart daily route on the application form</label></div>
    <div class="full wm-preview"><small>Drivers see:</small> <b><?= e(walmart_headline()) ?></b></div>
    <div class="full"><button class="btn btn-primary" type="submit">Save</button></div>
  </form>
</div>

<div class="card pad">
  <h2 style="font-size:1.2rem">Cities <span class="muted" style="font-size:.9rem;font-weight:500">(<?= $active ?> open<?= count($routes) > $active ? ', ' . (count($routes) - $active) . ' hidden' : '' ?>)</span></h2>
  <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="wm-add">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <input name="city" type="text" maxlength="80" required placeholder="City, State (e.g. Tampa, FL)" aria-label="New city">
    <button class="btn btn-primary" type="submit"><?= icon('route') ?> Add city</button>
  </form>
  <?php if (!$routes): ?><div class="empty">No cities yet. Add your first one above.</div><?php else: ?>
  <div class="wm-list">
    <?php foreach ($routes as $r): $on = (int) $r['active']; ?>
      <div class="wm-row<?= $on ? '' : ' off' ?>">
        <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="wm-name">
          <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input name="city" type="text" maxlength="80" value="<?= e($r['city']) ?>" aria-label="City name">
          <button class="btn btn-ghost btn-sm" type="submit">Save</button>
        </form>
        <span class="badge <?= $on ? 'badge-open' : 'badge-closed' ?>"><?= $on ? 'Open' : 'Hidden' ?></span>
        <a class="wm-apps" href="<?= e(url('admin/applications.php?city=' . rawurlencode($r['city']))) ?>"><?= (int) $r['applicants'] ?> applicant<?= (int) $r['applicants'] === 1 ? '' : 's' ?></a>
        <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= $on ? 'Hide' : 'Show' ?></button>
        </form>
        <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="inline-form" data-confirm="Remove <?= e($r['city']) ?> from the list?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" aria-label="Remove <?= e($r['city']) ?>"><?= icon('trash') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
