<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

[$val, $errors] = partner_form_state('partners.php');

$phone = (string) config('contact_phone');
$email = (string) config('contact_email');

/** A line icon placed inside the route map at (x, y). */
function map_icon(string $name, float $x, float $y, float $size = 22): string
{
    return str_replace('<svg class="ic" ', '<svg x="' . ($x - $size / 2) . '" y="' . ($y - $size / 2) . '" width="' . $size . '" height="' . $size . '" ', icon($name));
}

$hub = [280, 205];
$stops = [ // icon, label, x, y, route from the hub
    ['building', 'Hospital', 85, 85, 'M280 205 Q170 180 85 85'],
    ['medical', 'Pharmacy', 430, 105, 'M280 205 Q380 180 430 105'],
    ['thermo', 'Lab', 505, 250, 'M280 205 Q400 220 505 250'],
    ['home', 'Homes', 405, 330, 'M280 205 Q350 270 405 330'],
    ['cart', 'Store', 160, 330, 'M280 205 Q210 270 160 330'],
    ['package', 'Warehouse', 55, 225, 'M280 205 Q160 210 55 225'],
];
$vans = [[0, '7s', '0s'], [1, '8s', '-2.5s'], [3, '6.5s', '-1s'], [4, '7.5s', '-4s'], [5, '9s', '-6s']];


page_header('Partner with us', 'partners', 'Partner with LamazonLoads for last-mile delivery, healthcare delivery and dedicated fleet & driver services. Trusted couriers, in-house developers and real people on support. Request a call.');
?>
<section class="hero partner-hero has-photo">
  <?= bg_photo('handshake', 'center 40%') ?>
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="pill"><span class="dot"><?= icon('handshake') ?></span>Partner with LamazonLoads</span>
      <h1>Your deliveries.<span class="accent">Our network.</span></h1>
      <p class="lead">Last-mile, healthcare and dedicated fleet delivery for businesses that can't afford a missed stop. Trusted couriers on the road, our own developers behind the scenes, and real people who pick up the phone.</p>
      <div class="hero-actions">
        <a class="btn btn-accent btn-lg" href="#request-call">Request a call <?= icon('arrow') ?></a>
        <a class="btn btn-outline-light btn-lg" href="#solutions">Explore solutions</a>
      </div>
      <div class="hero-trust">
        <span><?= icon('check') ?>Trusted, vetted couriers</span>
        <span><?= icon('check') ?>In-house developers</span>
        <span><?= icon('check') ?>Dedicated account support</span>
      </div>
    </div>
    <div class="pmap-card" aria-label="Map: LamazonLoads couriers delivering from our hub to hospitals, pharmacies, labs, homes, stores and warehouses">
      <svg class="pmap" viewBox="0 0 560 440" role="img" aria-hidden="true">
        <defs>
          <radialGradient id="pm-hub" cx="35%" cy="30%" r="80%"><stop offset="0" stop-color="#3B7BFF"/><stop offset="1" stop-color="#1550D0"/></radialGradient>
        </defs>
        <g class="pm-routes">
          <?php foreach ($stops as $i => [, , , , $d]): ?><path id="pm-r<?= $i ?>" d="<?= $d ?>"/><?php endforeach; ?>
        </g>
        <g class="pm-flow">
          <?php foreach ($stops as [, , , , $d]): ?><path d="<?= $d ?>"/><?php endforeach; ?>
        </g>
        <?php foreach ($stops as $i => [$ic, $label, $x, $y]): ?>
          <g class="pm-stop" style="animation-delay:<?= .6 + $i * .12 ?>s">
            <circle class="pm-ring" cx="<?= $x ?>" cy="<?= $y ?>" r="25" style="animation-delay:<?= $i * .7 ?>s"/>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="25" fill="#fff"/>
            <g color="#1E63E9"><?= map_icon($ic, $x, $y) ?></g>
            <text x="<?= $x ?>" y="<?= $y + 44 ?>" text-anchor="middle"><?= e($label) ?></text>
          </g>
        <?php endforeach; ?>
        <?php foreach ($vans as [$r, $dur, $begin]): ?>
          <g class="pm-van">
            <rect x="-15" y="-11" width="30" height="22" rx="7" fill="#fff"/>
            <g color="#0A2463"><?= map_icon('van', 0, 0, 18) ?></g>
            <animateMotion dur="<?= $dur ?>" begin="<?= $begin ?>" repeatCount="indefinite" keyPoints="0;1;1;0;0" keyTimes="0;.42;.5;.92;1" calcMode="linear"><mpath href="#pm-r<?= $r ?>"/></animateMotion>
          </g>
        <?php endforeach; ?>
        <g class="pm-hub">
          <circle class="pm-pulse" cx="<?= $hub[0] ?>" cy="<?= $hub[1] ?>" r="40"/>
          <circle class="pm-pulse two" cx="<?= $hub[0] ?>" cy="<?= $hub[1] ?>" r="40"/>
          <circle cx="<?= $hub[0] ?>" cy="<?= $hub[1] ?>" r="40" fill="url(#pm-hub)" stroke="#fff" stroke-width="4"/>
          <g color="#fff"><?= map_icon('truck', $hub[0], $hub[1], 34) ?></g>
          <text class="pm-hub-label" x="<?= $hub[0] ?>" y="<?= $hub[1] + 62 ?>" text-anchor="middle">LamazonLoads</text>
        </g>
      </svg>
      <div class="pchip pchip-1"><span class="pchip-ico ok"><?= icon('check') ?></span><span><b>Delivered</b><small>Photo + signature sent</small></span></div>
      <div class="pchip pchip-2"><span class="pchip-ico"><?= icon('medical') ?></span><span><b>Rx route · 14 stops</b><small>On schedule</small></span></div>
      <div class="pchip pchip-3"><span class="pchip-ico"><?= icon('code') ?></span><span><b>Live updates</b><small>Sent to your system</small></span></div>
    </div>
  </div>
  <div class="road" aria-hidden="true"><?= truck_svg('truck-svg road-truck') ?></div>
</section>

<section class="partner-strip">
  <div class="container partner-strip-row">
    <div class="ps-item"><span class="ps-ico"><?= icon('shield') ?></span><span><b>Trusted couriers</b><small>Vetted, onboarded &amp; insured</small></span></div>
    <div class="ps-item"><span class="ps-ico"><?= icon('code') ?></span><span><b>In-house developers</b><small>Integrations built for you</small></span></div>
    <div class="ps-item"><span class="ps-ico"><?= icon('headset') ?></span><span><b>Real people</b><small>One team to call, every day</small></span></div>
    <div class="ps-item"><span class="ps-ico"><?= icon('van') ?></span><span><b>Right-sized fleet</b><small>Cargo vans to box trucks</small></span></div>
  </div>
</section>

<section class="section" id="solutions">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Solutions</span>
      <h2>Delivery your customers can count on</h2>
      <p class="lead">Three ways to put the LamazonLoads network to work for your business. Start with one, or mix them.</p>
    </div>
    <div class="sol-grid">
      <?php $n = 0; foreach (SOLUTIONS as $key => $sol): $n++; ?>
        <article class="card sol-card reveal" id="sol-<?= e($key) ?>">
          <a class="sol-top" href="<?= e(url($sol['page'])) ?>">
            <?= photo($sol['card_photo'], '', 'sol-top-photo', '(max-width: 1000px) 100vw, 400px') ?>
            <span class="sol-num">0<?= $n ?></span>
            <span class="sol-ico"><?= icon($sol['icon']) ?></span>
            <h3><?= e($sol['title']) ?></h3>
            <span class="sol-line" aria-hidden="true"></span>
          </a>
          <div class="sol-body">
            <p><?= e($sol['summary']) ?></p>
            <ul class="sol-list"><?php foreach ($sol['points'] as $pt): ?><li><?= icon('check') ?><span><?= e($pt) ?></span></li><?php endforeach; ?></ul>
            <div class="sol-for"><small>Ideal for</small><div class="tags"><?php foreach ($sol['for'] as $f): ?><span class="tag"><?= e($f) ?></span><?php endforeach; ?></div></div>
            <a class="btn btn-primary btn-block" href="<?= e(url($sol['page'])) ?>" aria-label="Learn more about <?= e($sol['title']) ?>">Learn more <?= icon('arrow') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-dark">
  <div class="container story level">
    <div class="reveal level-col">
      <span class="eyebrow">Why LamazonLoads</span>
      <h2>Trusted couriers on the road. Developers behind the scenes.</h2>
      <p>Most delivery partners give you drivers. We give you drivers <b>and</b> the technology team to plug them into the way you already work, plus a support team that knows your account by name.</p>
      <div class="dev-visual">
        <?= photo('developers', 'LamazonLoads developers at work', 'dev-photo') ?>
        <div class="code-card" aria-label="Example of a live delivery update sent to a partner's system">
          <div class="code-head"><span></span><span></span><span></span><small>delivery-update.json</small></div>
<pre><code>{
  <span class="k">"route"</span>: <span class="s">"RX-14"</span>,
  <span class="k">"stop"</span>: <span class="n">9</span>,
  <span class="k">"status"</span>: <span class="s">"delivered"</span>,
  <span class="k">"proof"</span>: [<span class="s">"photo"</span>, <span class="s">"signature"</span>],
  <span class="k">"courier"</span>: <span class="s">"LamazonLoads"</span>
}<span class="cursor"></span></code></pre>
        </div>
      </div>
    </div>
    <div class="pain-list reveal">
      <div class="pain"><span class="ico-sm"><?= icon('shield') ?></span><div><b>Trusted, vetted couriers</b><span>Every courier goes through our onboarding: license, insurance, vehicle and documents checked before their first run.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('code') ?></span><div><b>Our own developers</b><span>Need delivery updates in your system, a tracking page or custom reports? Our in-house developers build it for your account.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('chart') ?></span><div><b>Capacity that flexes</b><span>Add vans and drivers for busy seasons and new territories, then scale back when things calm down.</span></div></div>
      <div class="pain"><span class="ico-sm"><?= icon('headset') ?></span><div><b>Real people, real answers</b><span>A dedicated contact who knows your routes, your customers and your standards. No call-center runaround.</span></div></div>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">How it starts</span>
      <h2>From first call to full speed</h2>
      <p class="lead">No long sales cycle. We learn your operation, prove it on a pilot, then grow with you.</p>
    </div>
    <div class="route-wrap">
    <div class="route-track" aria-hidden="true"><span class="route-line"></span><span class="route-truck"><?= truck_svg() ?></span></div>
    <div class="steps">
      <div class="card step reveal"><h3>Request a call</h3><p>Tell us what you deliver, where and how often. It takes about two minutes.</p></div>
      <div class="card step reveal"><h3>Discovery call</h3><p>We talk through your routes, volumes, service windows and any special handling.</p></div>
      <div class="card step reveal"><h3>Pilot run</h3><p>We start with a test route so you can see our couriers and our updates in action.</p></div>
      <div class="card step reveal"><h3>Launch &amp; scale</h3><p>Go live with a dedicated contact, then add routes, vehicles and markets as you grow.</p></div>
    </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Industries</span>
      <h2>Who we deliver for</h2>
    </div>
    <div class="ind-grid">
      <?php foreach ([
          ['medical', 'Pharmacies'], ['building', 'Hospitals & clinics'], ['thermo', 'Labs & medical supply'], ['cart', 'Retail & e-commerce'],
          ['package', 'Warehousing & 3PL'], ['layers', 'Wholesale & distribution'], ['wrench', 'Auto parts & contractors'], ['home', 'Furniture & appliances'],
      ] as [$ic, $t]): ?>
        <div class="ind reveal"><span class="ind-ico"><?= icon($ic) ?></span><b><?= e($t) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partner_call_section($val, $errors, 'partners.php'); partner_cta_band(); ?>
<?php page_footer();
