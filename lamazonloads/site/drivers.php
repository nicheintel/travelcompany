<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

preload_photo('banner_drivers');
page_header('Drive with us', 'drivers', 'Join the LamazonLoads driver network: owner-operators, cargo van, Sprinter and box truck drivers. See open positions and Walmart daily routes.');
page_hero('Drive with LamazonLoads', 'Join the network. <span style="color:var(--sky)">Stay loaded.</span>', 'Owner-operators, van drivers and box truck drivers: onboard once and get access to loads, daily routes and new openings.', 'banner_drivers', 'center 30%');
$openJobs = (int) db_val("SELECT COUNT(*) FROM jobs WHERE status = 'open'") + (network_enabled() ? 1 : 0);
$wmCities = walmart_cities();
?>
<section class="section">
  <div class="container story level">
    <div class="reveal level-col">
      <span class="eyebrow">Who we work with</span>
      <h2>Independent drivers &amp; owner-operators</h2>
      <p class="lead">Whether you run one van or a few box trucks, LamazonLoads works for you: we find and book the freight, coordinate the details and stay with you until you get paid.</p>
      <div class="who-grid">
        <?php foreach ([
            ['briefcase', 'Owner-operators', 'Your truck, your authority. We dispatch and support.'],
            ['route', 'Route drivers', 'Looking for daily and dedicated routes near home.'],
            ['headset', 'Dispatchers', 'Join the team that advocates for drivers.'],
            ['star', 'Entrepreneurs', 'Starting out in trucking? Grow with the network.'],
        ] as [$ic, $t, $d]): ?>
          <div class="card who-card"><span class="who-ico"><?= icon($ic) ?></span><div><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card pad reveal level-card opp-card">
      <span class="eyebrow">Open opportunities</span>
      <h3>Find your next job on our Careers page</h3>
      <p class="muted">Browse every open position, then tap <b>View &amp; apply</b>. Applying takes about two minutes: your name, phone, location and vehicle.</p>
      <div class="opp-stats">
        <div><b><?= $openJobs ?></b><small>open position<?= $openJobs === 1 ? '' : 's' ?></small></div>
        <div><b><?= count($wmCities) ?></b><small>Walmart route cit<?= count($wmCities) === 1 ? 'y' : 'ies' ?></small></div>
      </div>
      <?php if ($wmCities): ?>
        <div class="opp-walmart">
          <p><?= icon('route') ?><b>Walmart daily routes</b><span class="opp-wm-sub"><?= e(walmart_headline()) ?></span></p>
          <div class="tags"><?php foreach (array_slice($wmCities, 0, 8) as $c): ?><span class="tag"><?= icon('pin') ?><?= e($c) ?></span><?php endforeach; ?>
            <?php if (count($wmCities) > 8): ?><span class="tag tag-solid">+<?= count($wmCities) - 8 ?> more</span><?php endif; ?></div>
        </div>
      <?php endif; ?>
      <a class="btn btn-accent btn-block level-bottom" href="<?= e(url('careers.php')) ?>">See open positions <?= icon('arrow') ?></a>
      <p class="hint center mb-0" style="margin-top:10px">Questions about routes, pay or onboarding? <a href="<?= e(url('faq.php')) ?>">Read the driver FAQ</a></p>
    </div>
  </div>
</section>

<section class="section-sm">
  <div class="container">
    <?= photo_band([
        ['drivers_band_1', 'Onboard once', 'Profile, vehicle and documents in one place.'],
        ['drivers_band_2', 'Get matched', 'Loads, daily routes and openings that fit.'],
        ['drivers_band_3', 'Stay loaded', 'Dispatch and support stay with you on the road.'],
    ]) ?>
  </div>
</section>

<?php fleet_showcase('What you can drive', 'Bring your van or truck', 'We dispatch cargo vans, Sprinter vans and box trucks. See where each one fits best.', 'section section-white'); ?>

<section class="section">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">Why drivers choose us</span><h2>We're in your corner</h2></div>
    <div class="grid grid-3">
      <div class="card feature reveal"><div class="ico"><?= icon('handshake') ?></div><h3>Driver advocates</h3><p>We negotiate for you like it's our own truck, because we've been there.</p></div>
      <div class="card feature reveal"><div class="ico"><?= icon('chat') ?></div><h3>Real support groups</h3><p>Talk directly with management and support, not a ticket queue.</p></div>
      <div class="card feature reveal"><div class="ico"><?= icon('shield') ?></div><h3>Your documents, secured</h3><p>Uploads are private: only you and LamazonLoads staff can open them.</p></div>
    </div>
  </div>
</section>

<?php cta_band(); page_footer();
