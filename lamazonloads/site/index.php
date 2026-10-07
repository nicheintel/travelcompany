<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$jobs = db_all("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC, id DESC LIMIT 3");
preload_photo('home_hero');
page_header('', 'home');
?>
<section class="hero has-photo">
  <?= bg_photo('home_hero', '70% center') ?>
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="pill"><span class="dot"><?= icon('truck') ?></span>Built by drivers, for drivers</span>
      <h1>Why wait?<span class="accent">Let's freight.</span></h1>
      <p class="lead">Freight dispatching, daily route opportunities and real driver support for cargo vans, Sprinter vans and box trucks. We keep independent drivers and owner-operators moving, and loaded.</p>
      <div class="hero-actions">
        <a class="btn btn-accent btn-lg" href="<?= e(url(current_user() ? 'account.php' : 'register.php')) ?>">Get loaded today <?= icon('arrow') ?></a>
        <a class="btn btn-outline-light btn-lg" href="<?= e(url('careers.php')) ?>">See open opportunities</a>
      </div>
      <div class="hero-trust">
        <span><?= icon('check') ?>Loads searched &amp; negotiated for you</span>
        <span><?= icon('check') ?>Daily &amp; dedicated routes</span>
        <span><?= icon('check') ?>Real people on support</span>
      </div>
    </div>
    <div class="dispatch-card" aria-label="How LamazonLoads dispatch works">
      <div class="dc-head"><h2>Your dispatch desk</h2><span class="live" data-live>Working for you</span></div>
      <div class="dc-bar" aria-hidden="true"><span></span></div>
      <ul class="dc-steps" data-dispatch>
        <li data-status="Finding freight…"><span class="ico"><?= icon('search', 'ic ic-main') ?><?= icon('check', 'ic ic-done') ?></span><div><b>We find the freight</b><small>Load boards, brokers &amp; contracts worked daily</small></div></li>
        <li data-status="Negotiating the rate…"><span class="ico"><?= icon('handshake', 'ic ic-main') ?><?= icon('check', 'ic ic-done') ?></span><div><b>We negotiate the rate</b><small>No more losing bids or hauling cheap freight</small></div></li>
        <li data-status="Sending your details…"><span class="ico"><?= icon('clipboard', 'ic ic-main') ?><?= icon('check', 'ic ic-done') ?></span><div><b>You get the details</b><small>Rate con, pickup &amp; drop-off sent to your phone</small></div></li>
        <li data-status="Tracking your payment…"><span class="ico"><?= icon('dollar', 'ic ic-main') ?><?= icon('check', 'ic ic-done') ?></span><div><b>We follow up on pay</b><small>Payment tracking until the money lands</small></div></li>
      </ul>
      <div class="dc-foot"><span>Cargo Van · Sprinter · Box Truck</span><b style="color:var(--blue)">Stay loaded</b></div>
    </div>
  </div>
  <div class="road" aria-hidden="true"><?= truck_svg('truck-svg road-truck') ?></div>
</section>

<section class="equip-strip">
  <div class="container equip-row">
    <span class="label">We dispatch</span>
    <div class="marquee"><div class="marquee-track">
      <div class="marquee-group"><span class="equip"><?= icon('van') ?>Cargo Vans</span><span class="equip"><?= icon('van') ?>Sprinter Vans</span><span class="equip"><?= icon('truck') ?>Box Trucks 16–26 ft</span><span class="equip"><?= icon('route') ?>Hotshot & other equipment</span><span class="equip"><?= icon('calendar') ?>Daily routes</span><span class="equip"><?= icon('clock') ?>Expedited freight</span><span class="equip"><?= icon('pin') ?>Local & last-mile</span><span class="equip"><?= icon('handshake') ?>Owner-operators</span></div>
      <div class="marquee-group" aria-hidden="true"><span class="equip"><?= icon('van') ?>Cargo Vans</span><span class="equip"><?= icon('van') ?>Sprinter Vans</span><span class="equip"><?= icon('truck') ?>Box Trucks 16–26 ft</span><span class="equip"><?= icon('route') ?>Hotshot & other equipment</span><span class="equip"><?= icon('calendar') ?>Daily routes</span><span class="equip"><?= icon('clock') ?>Expedited freight</span><span class="equip"><?= icon('pin') ?>Local & last-mile</span><span class="equip"><?= icon('handshake') ?>Owner-operators</span></div>
    </div></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">More than dispatching</span>
      <h2>A driver-support &amp; logistics network</h2>
      <p class="lead">Everything you need to stay on the road and profitable, under one roof.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ([
          ['truck', 'Freight dispatching', 'We search, negotiate and book loads for Cargo Vans, Sprinter Vans, Box Trucks and other qualified equipment.'],
          ['route', 'Daily route opportunities', 'Dedicated and local delivery routes when contracts are available, matched by ZIP code and equipment.'],
          ['clipboard', 'Driver onboarding', 'One simple onboarding: documents, vehicle info, ZIP code, availability, insurance and W-9, all in your dashboard.'],
          ['headset', 'Driver support', 'Dedicated support groups where you talk directly with LamazonLoads management and support representatives.'],
          ['calendar', 'Load coordination', 'Pickup and drop-off details, scheduling, route assignments, payment tracking and follow-ups handled for you.'],
          ['users', 'Community', 'Drivers, dispatchers and entrepreneurs helping each other find opportunities and stay productive on the road.'],
      ] as [$ic, $t, $d]): ?>
        <div class="card feature reveal"><div class="ico"><?= icon($ic) ?></div><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">Real people. Real routes.</span><h2>The network behind every delivery</h2><p class="lead">Drivers, dispatchers and support working together so freight keeps moving and drivers keep earning.</p></div>
    <?= photo_band([
        ['home_mosaic_1', 'Support on every route', 'A real person a call or message away.'],
        ['home_mosaic_2', 'Loads that fit your van', 'Matched to your equipment and your area.'],
        ['home_mosaic_3', 'Drivers of every kind', 'Owner-operators, route drivers and new drivers.'],
        ['home_mosaic_4', 'Box trucks welcome', '16 to 26 ft straight trucks.'],
        ['home_mosaic_5', 'Delivered with care', 'From pickup to proof of delivery.'],
    ], 'mosaic') ?>
  </div>
</section>

<?php fleet_showcase('Meet the fleet', 'Cargo vans, Sprinters and box trucks', 'The vehicles that keep the LamazonLoads network moving. Tap one to take a closer look.', 'section'); ?>

<section class="section section-dark">
  <div class="container story">
    <div class="reveal">
      <span class="eyebrow">Built from the driver's seat</span>
      <h2>We know what it feels like to sit and wait for freight.</h2>
      <p>LamazonLoads was created from a driver's perspective. We've deadheaded the empty miles, lost the bids and watched the day go by without a load. So we built a company that works the way drivers need it to.</p>
      <p class="quote">"Our job is simple: advocate for drivers and keep them loaded."</p>
      <a class="btn btn-accent" href="<?= e(url('about.php')) ?>">Our story <?= icon('arrow') ?></a>
    </div>
    <div class="pain-list reveal">
      <div class="pain"><span class="ico-sm"><?= icon('clock') ?></span><div><b>No more waiting on freight</b><span>We're working the boards and our broker network while you drive.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('route') ?></span><div><b>Less deadhead</b><span>We plan your next load around where your last one drops.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('handshake') ?></span><div><b>Stop losing bids</b><span>Experienced dispatchers negotiate so you don't haul for less than you're worth.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('chart') ?></span><div><b>Stay profitable</b><span>Rates, routes and payment follow-ups focused on what you actually take home.</span></div></div>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">How it works</span>
      <h2>From sign-up to rolling in four steps</h2>
    </div>
    <div class="route-wrap">
    <div class="route-track" aria-hidden="true"><span class="route-line"></span><span class="route-truck"><?= truck_svg() ?></span></div>
    <div class="steps">
      <div class="card step reveal"><h3>Create your account</h3><p>Sign up in about two minutes. Tell us if you're an owner-operator, driver or dispatcher.</p></div>
      <div class="card step reveal"><h3>Finish onboarding</h3><p>Add your equipment, home ZIP code and availability, then upload your W-9, insurance and license.</p></div>
      <div class="card step reveal"><h3>Get matched</h3><p>We match you with loads, daily routes and job openings that fit your truck and your schedule.</p></div>
      <div class="card step reveal"><h3>Stay loaded</h3><p>Dispatch and support stay with you on the road, from pickup to payment.</p></div>
    </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head left reveal" style="display:flex;justify-content:space-between;align-items:end;gap:20px;max-width:none;flex-wrap:wrap">
      <div><span class="eyebrow">Now hiring &amp; onboarding</span><h2 class="mb-0">Open opportunities</h2></div>
      <a class="btn btn-ghost" href="<?= e(url('careers.php')) ?>">All openings <?= icon('arrow') ?></a>
    </div>
    <?php if ($jobs): ?>
      <div class="jc-grid"><?php foreach ($jobs as $j) echo job_card($j); ?></div>
    <?php else: ?>
      <div class="card empty">New openings are posted here first. <a href="<?= e(url('register.php')) ?>">Create an account</a> to be ready when they open.</div>
    <?php endif; ?>
  </div>
</section>

<section class="section-sm">
  <div class="container">
    <div class="biz-band reveal">
      <div>
        <span class="eyebrow">For businesses</span>
        <h2>Need deliveries handled? Partner with LamazonLoads.</h2>
        <p class="lead">Trusted couriers, our own developers and a support team that knows your account. Tell us what you deliver and we'll build the route around it.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('partners.php')) ?>#request-call">Request a call <?= icon('arrow') ?></a>
      </div>
      <div class="biz-list">
        <a class="biz-item" href="<?= e(url('last-mile-delivery.php')) ?>"><span class="who-ico"><?= icon('package') ?></span>Last-Mile Delivery<?= icon('arrow', 'ic ic-go') ?></a>
        <a class="biz-item" href="<?= e(url('healthcare-delivery.php')) ?>"><span class="who-ico"><?= icon('medical') ?></span>Healthcare Delivery Solutions<?= icon('arrow', 'ic ic-go') ?></a>
        <a class="biz-item" href="<?= e(url('dedicated-fleet.php')) ?>"><span class="who-ico"><?= icon('layers') ?></span>Dedicated Fleet &amp; Driver Services<?= icon('arrow', 'ic ic-go') ?></a>
      </div>
    </div>
  </div>
</section>

<?php gallery_section('section'); ?>

<section class="section section-white" id="faq">
  <div class="container narrow">
    <div class="section-head reveal"><span class="eyebrow">Questions</span><h2>Frequently asked</h2></div>
    <div class="faq reveal">
      <?php foreach (['what', 'vehicle', 'start', 'route-pay', 'fee', 'paid-when'] as $k): [$q, $ans] = faq_item($k); ?>
        <details><summary><?= e($q) ?></summary><div class="faq-a"><?= faq_answer_html($ans) ?></div></details>
      <?php endforeach; ?>
    </div>
    <p class="faq-more reveal"><a class="btn btn-ghost" href="<?= e(url('faq.php')) ?>">See all driver questions <?= icon('arrow') ?></a></p>
  </div>
</section>

<?php cta_band(); page_footer();
