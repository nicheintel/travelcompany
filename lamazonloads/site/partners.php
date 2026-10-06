<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$val = ['first_name' => '', 'last_name' => '', 'company' => '', 'job_title' => '', 'email' => '', 'phone' => '',
    'service' => '', 'location' => '', 'volume' => '', 'best_time' => '', 'message' => '', 'consent' => ''];
if (isset(PARTNER_SERVICES[(string) ($_GET['service'] ?? '')])) {
    $val['service'] = (string) $_GET['service'];
}

if (is_post()) {
    csrf_check();
    foreach ($val as $k => $_) {
        $val[$k] = post($k, $k === 'message' ? 3000 : 190);
    }
    if (post('website') !== '') { // hidden field: only bots fill it in
        redirect('partners.php?sent=1#request-call');
    }
    [$id, $errors] = partner_submit($val);
    if ($id) {
        redirect('partners.php?sent=1#request-call');
    }
}

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

$solutions = [
    'last_mile' => ['package', 'Last-Mile Delivery',
        'Fast, friendly final-mile delivery from your store, warehouse or hub to your customer’s door.',
        ['Same-day, next-day and scheduled delivery windows', 'Multi-stop routes planned around your cut-off times', 'Photo and signature proof of delivery', 'Cargo vans, Sprinter vans and box trucks', 'Courteous couriers who represent your brand well'],
        ['Retail & e-commerce', 'Furniture & appliances', 'Distributors']],
    'healthcare' => ['medical', 'Healthcare Delivery Solutions',
        'Careful, on-time transport for pharmacies, labs, clinics and hospitals, when it really matters.',
        ['STAT, same-day and scheduled routes', 'Prescription and pharmacy delivery', 'Lab specimens and medical supplies', 'Temperature-sensitive handling on request', 'Chain of custody and proof of delivery on every run', 'Couriers who respect patient privacy'],
        ['Pharmacies', 'Labs', 'Hospitals & clinics', 'Medical suppliers']],
    'dedicated' => ['layers', 'Dedicated Fleet & Driver Services',
        'Your own vans and drivers on your schedule, without the hiring headaches.',
        ['Dedicated cargo vans, Sprinters and box trucks', 'Vetted drivers, onboarded and supported by us', 'Daily routes, recurring runs and peak-season capacity', 'Scheduling, coverage and replacement drivers handled', 'One point of contact for your account'],
        ['Distributors', 'Contractors', 'Growing businesses']],
];

page_header('Partner with us', 'partners', 'Partner with LamazonLoads for last-mile delivery, healthcare delivery and dedicated fleet & driver services. Trusted couriers, in-house developers and real people on support. Request a call.');
?>
<section class="hero partner-hero">
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
      <?php $n = 0; foreach ($solutions as $key => [$ic, $title, $desc, $points, $for]): $n++; ?>
        <article class="card sol-card reveal" id="sol-<?= e($key) ?>">
          <div class="sol-top">
            <span class="sol-num">0<?= $n ?></span>
            <span class="sol-ico"><?= icon($ic) ?></span>
            <h3><?= e($title) ?></h3>
            <span class="sol-line" aria-hidden="true"></span>
          </div>
          <div class="sol-body">
            <p><?= e($desc) ?></p>
            <ul class="sol-list"><?php foreach ($points as $pt): ?><li><?= icon('check') ?><span><?= e($pt) ?></span></li><?php endforeach; ?></ul>
            <div class="sol-for"><small>Ideal for</small><div class="tags"><?php foreach ($for as $f): ?><span class="tag"><?= e($f) ?></span><?php endforeach; ?></div></div>
            <a class="btn btn-primary btn-block" href="<?= e(url('partners.php?service=' . $key)) ?>#request-call">Request a call about this <?= icon('arrow') ?></a>
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

<section class="section section-white rc-section" id="request-call">
  <div class="container story level rc-layout">
    <div class="reveal level-col">
      <span class="eyebrow">Request a call</span>
      <h2>Let's talk about your deliveries</h2>
      <p class="lead">Share a few details and our partnerships team will reach out to schedule a call at a time that suits you.</p>
      <ol class="rc-steps">
        <li><span><?= icon('phone-call') ?></span><div><b>We call you</b><small>At the time you choose below</small></div></li>
        <li><span><?= icon('clipboard') ?></span><div><b>We map your needs</b><small>Routes, volumes, windows and handling</small></div></li>
        <li><span><?= icon('route') ?></span><div><b>You get a plan</b><small>Clear pricing and a pilot route to start</small></div></li>
      </ol>
      <ul class="rc-promise">
        <li><?= icon('user') ?>A real person reviews every request</li>
        <li><?= icon('handshake') ?>No obligation: the call is free</li>
        <li><?= icon('route') ?>A pilot route before you commit</li>
        <li><?= icon('shield') ?>Your details stay private</li>
      </ul>
      <h3 class="rc-faq-title">Quick answers</h3>
    <div class="faq rc-faq">
        <details><summary>Where do you deliver?</summary><p>We work with businesses across the United States. Tell us your location in the form and we'll confirm coverage for your routes on the call.</p></details>
        <details><summary>Is there a minimum volume?</summary><p>No fixed minimum. Some partners start with a single daily route, others with a full dedicated fleet. A pilot run lets you see how we work before committing.</p></details>
        <details><summary>Can you handle medical and pharmacy deliveries?</summary><p>Yes. Healthcare deliveries get careful handling, chain of custody and proof of delivery. Tell us about any temperature or privacy requirements and we'll plan the route around them.</p></details>
        <details><summary>Can you connect with our systems?</summary><p>Yes. Our in-house developers can send delivery updates to your system, set up a tracking page for your customers or build the reports you need.</p></details>
      </div>
      <div class="rc-direct card pad">
        <b>Prefer to talk now?</b>
        <?php if ($phone !== ''): ?><a href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?><?= e($phone) ?></a><?php endif; ?>
        <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>?subject=Partnership"><?= icon('mail') ?><?= e($email) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="card form-card rc-card reveal">
      <?php if (isset($_GET['sent'])): ?>
        <div class="center rc-done">
          <div class="ico-lg" style="margin:0 auto 16px;background:linear-gradient(135deg,#12A150,#0B6B35)"><?= icon('check') ?></div>
          <h2>Request received</h2>
          <p class="muted">Thank you for reaching out. We sent a confirmation to your email, and our partnerships team will contact you soon to schedule your call.</p>
          <?php if ($phone !== ''): ?><p class="muted">Need us sooner? Call <a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a>.</p><?php endif; ?>
          <a class="btn btn-primary" href="<?= e(url('partners.php')) ?>#solutions">Back to solutions</a>
        </div>
      <?php else: ?>
        <h3 class="rc-title"><?= icon('phone-call') ?>Request a call</h3>
        <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= e(url('partners.php')) ?>#request-call" class="form-grid" novalidate>
          <?= csrf_field() ?>
          <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <div><label for="first_name">First name</label><input id="first_name" name="first_name" type="text" required maxlength="60" value="<?= e($val['first_name']) ?>" autocomplete="given-name"></div>
          <div><label for="last_name">Last name</label><input id="last_name" name="last_name" type="text" required maxlength="60" value="<?= e($val['last_name']) ?>" autocomplete="family-name"></div>
          <div><label for="company">Company</label><input id="company" name="company" type="text" required maxlength="120" value="<?= e($val['company']) ?>" autocomplete="organization"></div>
          <div><label for="job_title">Job title <span class="opt">(optional)</span></label><input id="job_title" name="job_title" type="text" maxlength="100" value="<?= e($val['job_title']) ?>" autocomplete="organization-title"></div>
          <div><label for="email">Business email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= e($val['email']) ?>" autocomplete="email"></div>
          <div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" required maxlength="30" value="<?= e($val['phone']) ?>" autocomplete="tel"></div>
          <div class="full">
            <span class="label">I'm interested in</span>
            <div class="svc-pick">
              <?php foreach (PARTNER_SERVICES as $k => $l): ?>
                <label class="svc-opt"><input type="radio" name="service" value="<?= e($k) ?>"<?= $val['service'] === $k ? ' checked' : '' ?> required><span><?= icon($solutions[$k][0] ?? 'handshake') ?><?= e($l) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div><label for="location">City, state or area <span class="opt">(optional)</span></label><input id="location" name="location" type="text" maxlength="120" value="<?= e($val['location']) ?>" placeholder="e.g. Atlanta, GA"></div>
          <div><label for="volume">Deliveries per week <span class="opt">(optional)</span></label><select id="volume" name="volume"><option value="">Choose…</option><?php foreach (PARTNER_VOLUMES as $k => $l): ?><option value="<?= e($k) ?>"<?= $val['volume'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="full"><label for="best_time">Best time to call <span class="opt">(Eastern time)</span></label><select id="best_time" name="best_time"><option value="">Choose…</option><?php foreach (PARTNER_TIMES as $k => $l): ?><option value="<?= e($k) ?>"<?= $val['best_time'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="full"><label for="message">Tell us about your deliveries <span class="opt">(optional)</span></label><textarea id="message" name="message" maxlength="3000" placeholder="What you deliver, where to, how often, and anything special (time windows, temperature, signatures…)"><?= e($val['message']) ?></textarea></div>
          <div class="full"><label class="check"><input type="checkbox" name="consent" value="1"<?= $val['consent'] !== '' ? ' checked' : '' ?> required><span>LamazonLoads may contact me by phone or email about this request. See our <a href="<?= e(url('privacy.php')) ?>">privacy policy</a>.</span></label></div>
          <div class="full"><button class="btn btn-accent btn-lg btn-block" type="submit">Request my call <?= icon('arrow') ?></button></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section-sm"><div class="container">
  <div class="cta-band reveal">
    <div>
      <h2>Why wait? Let's partner.</h2>
      <p>Tell us about your deliveries and we'll show you what the LamazonLoads network can do.</p>
    </div>
    <div class="btns">
      <a class="btn btn-accent btn-lg" href="#request-call">Request a call <?= icon('arrow') ?></a>
      <?php if ($phone !== ''): ?><a class="btn btn-outline-light btn-lg" href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?><?= e($phone) ?></a><?php endif; ?>
    </div>
  </div>
</div></section>
<?php page_footer();
