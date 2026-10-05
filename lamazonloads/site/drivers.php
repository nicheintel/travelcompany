<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

page_header('Drive with us', 'drivers', 'Join the LamazonLoads driver network: owner-operators, cargo van, Sprinter and box truck drivers. See what you need for onboarding.');
page_hero('Drive with LamazonLoads', 'Join the network. <span style="color:var(--sky)">Stay loaded.</span>', 'Owner-operators, van drivers and box truck drivers: onboard once and get access to loads, daily routes and new openings.');
?>
<section class="section">
  <div class="container story" style="align-items:start">
    <div class="reveal">
      <span class="eyebrow">Who we work with</span>
      <h2>Independent drivers &amp; owner-operators</h2>
      <p class="lead">Whether you run one van or a few box trucks, LamazonLoads works for you: we find and book the freight, coordinate the details and stay with you until you get paid.</p>
      <div class="grid grid-2 mt">
        <div class="card pad"><div class="ico-lg"><?= icon('briefcase') ?></div><h3>Owner-operators</h3><p class="muted mb-0">Your truck, your authority. We dispatch and support.</p></div>
        <div class="card pad"><div class="ico-lg"><?= icon('route') ?></div><h3>Route drivers</h3><p class="muted mb-0">Looking for daily and dedicated routes near home.</p></div>
        <div class="card pad"><div class="ico-lg"><?= icon('headset') ?></div><h3>Dispatchers</h3><p class="muted mb-0">Join the team that advocates for drivers.</p></div>
        <div class="card pad"><div class="ico-lg"><?= icon('star') ?></div><h3>Entrepreneurs</h3><p class="muted mb-0">Starting out in trucking? Grow with the network.</p></div>
      </div>
    </div>
    <div class="card pad reveal">
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
      <a class="btn btn-accent btn-block mt" href="<?= e(url(current_user() ? 'profile.php' : 'register.php')) ?>"><?= current_user() ? 'Complete my onboarding' : 'Start onboarding' ?> <?= icon('arrow') ?></a>
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
