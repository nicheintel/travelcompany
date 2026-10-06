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
<?= admin_head('Walmart daily routes', 'Set the start month and pay, and choose which cities drivers can pick on the application form. SUV and other-vehicle drivers get the Walmart welcome email for their city.') ?>

<div class="wm-layout">
<section class="card panel wm-program">
  <header class="panel-head"><h2><?= icon('route') ?>Program settings</h2></header>
  <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="panel-body wm-settings">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div><label for="start">Routes start in</label><input id="start" name="start" type="text" maxlength="40" value="<?= e($s['start']) ?>" placeholder="November"></div>
    <div><label for="rate">Pay rate</label><input id="rate" name="rate" type="text" maxlength="40" value="<?= e($s['rate']) ?>" placeholder="$275 per day"></div>
    <label class="check"><input type="checkbox" name="on" value="1"<?= $s['on'] ? ' checked' : '' ?>> Show the Walmart daily route on the application form</label>
    <div class="wm-preview"><small>Drivers see</small><b><?= e(walmart_headline()) ?></b></div>
    <button class="btn btn-primary btn-block" type="submit">Save settings</button>
  </form>
</section>

<section class="card panel">
  <header class="panel-head"><h2><?= icon('pin') ?>Cities</h2><small><?= $active ?> open<?= count($routes) > $active ? ' · ' . (count($routes) - $active) . ' hidden' : '' ?></small></header>
  <div class="panel-body">
    <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="wm-add">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <input name="city" type="text" maxlength="80" required placeholder="Add a city: City, State (e.g. Tampa, FL)" aria-label="New city">
      <button class="btn btn-accent" type="submit"><?= icon('plus') ?> Add city</button>
    </form>
    <?php if (!$routes): ?><div class="empty">No cities yet. Add your first one above.</div><?php else: ?>
    <div class="wm-list">
      <div class="wm-row wm-head" aria-hidden="true"><span>City</span><span>Status</span><span>Applicants</span><span></span></div>
      <?php foreach ($routes as $r): $on = (int) $r['active']; ?>
        <div class="wm-row<?= $on ? '' : ' off' ?>">
          <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="wm-name" data-inline-edit>
            <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input name="city" type="text" maxlength="80" value="<?= e($r['city']) ?>" aria-label="City name (click to rename)" title="Click to rename">
            <button class="btn btn-primary btn-sm" type="submit" data-save hidden>Save</button>
          </form>
          <span><span class="badge <?= $on ? 'badge-open' : 'badge-closed' ?>"><?= $on ? 'Open' : 'Hidden' ?></span></span>
          <a class="wm-apps" href="<?= e(url('admin/applications.php?city=' . rawurlencode($r['city']))) ?>"><?= (int) $r['applicants'] ?> <span>applicant<?= (int) $r['applicants'] === 1 ? '' : 's' ?></span></a>
          <div class="wm-actions">
            <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon($on ? 'eye' : 'eye') ?> <?= $on ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="post" action="<?= e(url('admin/walmart.php')) ?>" class="inline-form" data-confirm="Remove <?= e($r['city']) ?> from the list?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-danger btn-sm btn-icon" type="submit" aria-label="Remove <?= e($r['city']) ?>" title="Remove"><?= icon('trash') ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
</div>
<?php dash_close(); page_footer();
