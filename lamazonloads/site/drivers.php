<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

page_header('Drive with us', 'drivers', 'Join the LamazonLoads driver network: owner-operators, cargo van, Sprinter and box truck drivers. See what you need for onboarding.');
page_hero('Drive with LamazonLoads', 'Join the network. <span style="color:var(--sky)">Stay loaded.</span>', 'Owner-operators, van drivers and box truck drivers: onboard once and get access to loads, daily routes and new openings.');
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
    <div class="card pad reveal level-card">
      <h3>What you'll need for onboarding</h3>
      <p class="muted">Have these ready. You can upload them securely from your dashboard after you sign up.</p>
      <ul class="checklist">
        <?php foreach ([
            "Valid driver's license",
            'Qualified vehicle: cargo van, Sprinter, box truck or other',
            'Vehicle registration',
            'Certificate of insurance (commercial auto / cargo)',
            'Signed W-9',
            'MC / DOT authority (owner-operators, if you have it)',
            'Home ZIP code and your availability',
            'Smartphone for rate confirmations and updates',
        ] as $item): ?>
          <li><span class="tick"><?= icon('check') ?></span><span><?= e($item) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn-accent btn-block level-bottom" href="<?= e(url(current_user() ? 'profile.php' : 'register.php')) ?>"><?= current_user() ? 'Complete my onboarding' : 'Start onboarding' ?> <?= icon('arrow') ?></a>
    </div>
  </div>
</section>

<section class="section section-white">
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
