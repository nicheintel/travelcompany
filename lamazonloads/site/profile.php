<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_verified();
$p = profile_row((int) $u['id']);
$errors = [];

if (is_post()) {
    csrf_check();
    $errors = profile_from_post($p);
    if (!$errors) {
        profile_save((int) $u['id'], $p);
        flash('success', 'Driver profile saved.');
        redirect('profile.php');
    }
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
    <div><label for="equipment">Equipment</label><?= select_html('equipment', EQUIPMENT, (string) $p['equipment']) ?></div>
    <div><label for="vehicle">Year, make &amp; model <span class="opt">(optional)</span></label><input id="vehicle" name="vehicle" type="text" maxlength="120" placeholder="2021 Ford Transit 250 High Roof" value="<?= e($p['vehicle']) ?>"></div>
    <div><label for="home_zip">Home ZIP code</label><input id="home_zip" name="home_zip" type="text" inputmode="numeric" maxlength="10" placeholder="30301" value="<?= e($p['home_zip']) ?>"></div>
    <div><label for="service_radius">How far will you run? <span class="opt">(optional)</span></label><input id="service_radius" name="service_radius" type="text" maxlength="40" placeholder="Local, 300 mi, OTR…" value="<?= e($p['service_radius']) ?>"></div>
    <div><label for="availability">Availability</label><?= select_html('availability', AVAILABILITY, (string) $p['availability']) ?></div>
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
  </div>
</form>
<?php dash_close(); page_footer();
