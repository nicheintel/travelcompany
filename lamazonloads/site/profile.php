<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_verified();
$fields = ['company_name' => 120, 'mc_number' => 20, 'dot_number' => 20, 'equipment' => 30, 'vehicle' => 120, 'home_zip' => 10,
    'service_radius' => 40, 'availability' => 30, 'years_experience' => 10, 'insurance_provider' => 120, 'insurance_expires' => 10, 'about' => 2000];
$p = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$u['id']]) ?? array_fill_keys(array_keys($fields), '');
$errors = [];

if (is_post()) {
    csrf_check();
    foreach ($fields as $k => $max) {
        $p[$k] = post($k, $max);
    }
    if ($p['equipment'] !== '' && !isset(EQUIPMENT[$p['equipment']])) $errors[] = 'Please choose your equipment from the list.';
    if ($p['availability'] !== '' && !isset(AVAILABILITY[$p['availability']])) $errors[] = 'Please choose your availability from the list.';
    if ($p['home_zip'] !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $p['home_zip'])) $errors[] = 'Please enter a 5-digit ZIP code.';
    if ($p['mc_number'] !== '' && !preg_match('/^(MC-?)?\d{1,8}$/i', $p['mc_number'])) $errors[] = 'MC number should be digits only (e.g. 123456).';
    if ($p['dot_number'] !== '' && !preg_match('/^\d{1,9}$/', $p['dot_number'])) $errors[] = 'USDOT number should be digits only.';
    if ($p['insurance_expires'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['insurance_expires'])) $errors[] = 'Please pick a valid insurance expiry date.';
    if (!$errors) {
        $vals = array_map(fn ($k) => $k === 'insurance_expires' && $p[$k] === '' ? null : $p[$k], array_keys($fields));
        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_fill(0, count($fields), '?'));
        $upd = implode(', ', array_map(fn ($k) => "$k = VALUES($k)", array_keys($fields)));
        db_run("INSERT INTO driver_profiles (user_id, $cols, updated_at) VALUES (?, $marks, NOW()) ON DUPLICATE KEY UPDATE $upd, updated_at = NOW()",
            array_merge([$u['id']], $vals));
        auto_review_user((int) $u['id']);
        flash('success', 'Driver profile saved.');
        redirect('profile.php');
    }
}

function sel(string $name, array $opts, string $cur): string
{
    $h = '<select id="' . $name . '" name="' . $name . '"><option value="">Choose…</option>';
    foreach ($opts as $k => $l) {
        $h .= '<option value="' . e($k) . '"' . ($cur === $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h . '</select>';
}

page_header('Driver profile');
dash_open('profile');
?>
<h1>Driver profile</h1>
<p class="muted">This is what our dispatch team uses to match you with loads, routes and openings.</p>
<?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
<form method="post" action="<?= e(url('profile.php')) ?>" class="card form-card" novalidate>
  <?= csrf_field() ?>
  <h3 class="mt-0">Equipment &amp; area</h3>
  <div class="form-grid">
    <div><label for="equipment">Equipment</label><?= sel('equipment', EQUIPMENT, (string) $p['equipment']) ?></div>
    <div><label for="vehicle">Year, make &amp; model <span class="opt">(optional)</span></label><input id="vehicle" name="vehicle" type="text" maxlength="120" placeholder="2021 Ford Transit 250 High Roof" value="<?= e($p['vehicle']) ?>"></div>
    <div><label for="home_zip">Home ZIP code</label><input id="home_zip" name="home_zip" type="text" inputmode="numeric" maxlength="10" placeholder="30301" value="<?= e($p['home_zip']) ?>"></div>
    <div><label for="service_radius">How far will you run? <span class="opt">(optional)</span></label><input id="service_radius" name="service_radius" type="text" maxlength="40" placeholder="Local, 300 mi, OTR…" value="<?= e($p['service_radius']) ?>"></div>
    <div><label for="availability">Availability</label><?= sel('availability', AVAILABILITY, (string) $p['availability']) ?></div>
    <div><label for="years_experience">Years of experience <span class="opt">(optional)</span></label><input id="years_experience" name="years_experience" type="text" maxlength="10" value="<?= e($p['years_experience']) ?>"></div>
  </div>
  <h3 class="mt">Business &amp; insurance</h3>
  <div class="form-grid">
    <div class="full"><label for="company_name">Company name <span class="opt">(if you have one)</span></label><input id="company_name" name="company_name" type="text" maxlength="120" value="<?= e($p['company_name']) ?>"></div>
    <div><label for="mc_number">MC number <span class="opt">(optional)</span></label><input id="mc_number" name="mc_number" type="text" maxlength="20" value="<?= e($p['mc_number']) ?>"></div>
    <div><label for="dot_number">USDOT number <span class="opt">(optional)</span></label><input id="dot_number" name="dot_number" type="text" inputmode="numeric" maxlength="20" value="<?= e($p['dot_number']) ?>"></div>
    <div><label for="insurance_provider">Insurance company</label><input id="insurance_provider" name="insurance_provider" type="text" maxlength="120" value="<?= e($p['insurance_provider']) ?>"></div>
    <div><label for="insurance_expires">Insurance expires</label><input id="insurance_expires" name="insurance_expires" type="date" min="2000-01-01" max="2099-12-31" value="<?= e($p['insurance_expires'] ?? '') ?>"></div>
    <div class="full"><label for="about">Anything else dispatch should know? <span class="opt">(optional)</span></label><textarea id="about" name="about" maxlength="2000" placeholder="Preferred lanes, home time, hazmat / TWIC, liftgate, pallet jack…"><?= e($p['about'] ?? '') ?></textarea></div>
  </div>
  <div class="row-actions mt">
    <button class="btn btn-accent" type="submit">Save profile</button>
    <a class="btn btn-ghost" href="<?= e(url('documents.php')) ?>">Next: upload documents <?= icon('arrow') ?></a>
  </div>
</form>
<?php dash_close(); page_footer();
