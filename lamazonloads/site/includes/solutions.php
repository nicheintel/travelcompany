<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/* The three partner solutions: summary cards on partners.php and a full "Learn more" page for each. */

const SOLUTIONS = [
    'last_mile' => [
        'page' => 'last-mile-delivery.php',
        'hero_photo' => 'hero_last_mile',
        'card_photo' => 'card_last_mile',
        'overview_photo' => ['overview_last_mile', 'Courier sorting packages at the back of a van'],
        'icon' => 'package',
        'title' => 'Last-Mile Delivery',
        'summary' => 'Fast, friendly final-mile delivery from your store, warehouse or hub to your customer’s door.',
        'points' => ['Same-day, next-day and scheduled delivery windows', 'Multi-stop routes planned around your cut-off times', 'Photo and signature proof of delivery', 'Cargo vans, Sprinter vans and box trucks', 'Courteous couriers who represent your brand well'],
        'for' => ['Retail & e-commerce', 'Furniture & appliances', 'Distributors'],
        'meta' => 'Last-mile delivery for retailers, e-commerce, distributors and local businesses: same-day and scheduled delivery, photo and signature proof, cargo vans to box trucks.',
        'h1' => 'Last-mile delivery that makes <span class="accent">you look good.</span>',
        'lead' => 'The final mile is the part your customer sees. Our vetted couriers get it to the door on time, handle it with care and send proof the moment it’s delivered.',
        'tracker' => ['Order #4821', 'Out for delivery', [
            ['package', 'Picked up at your hub', '8:05 AM · 18 stops loaded'],
            ['route', 'Out for delivery', 'Stop 6 of 18 · on schedule'],
            ['check', 'Delivered', 'Photo + signature captured'],
            ['code', 'Update sent', 'To you and your customer'],
        ]],
        'highlights' => [['clock', 'Same-day & next-day', 'Plus scheduled windows'], ['check', 'Proof on every stop', 'Photo, signature, time'], ['van', 'The right vehicle', 'Cargo vans to box trucks'], ['code', 'Live updates', 'Sent to your system']],
        'overview_title' => 'Your brand, delivered to the door',
        'overview' => [
            'Your customer doesn’t see your warehouse, your software or your supply chain. They see the person who shows up at their door. That moment decides whether they order again.',
            'LamazonLoads gives you a team of vetted, courteous couriers and the right vehicles for what you ship, from small parcels in cargo vans to furniture and appliances in box trucks. We plan routes around your cut-off times, keep you updated through the day and confirm every delivery with proof.',
        ],
        'included' => ['Same-day, next-day and scheduled delivery windows', 'Multi-stop route planning around your cut-off times', 'Residential and business deliveries', 'Threshold or room-of-choice delivery on request', 'Photo, signature and time-stamp proof of delivery', 'Returns and exchanges picked up on the way', 'Delivery updates by email or straight to your system'],
        'benefits_title' => 'Why businesses choose LamazonLoads for the last mile',
        'benefits' => [
            ['clock', 'On time, on your terms', 'Routes are built around your delivery windows and cut-offs, not ours.'],
            ['users', 'Couriers who represent you', 'Vetted, courteous people who treat your customers the way you would.'],
            ['check', 'Proof on every stop', 'Photo, signature and time stamp on each delivery, so there’s never a “where is it?” mystery.'],
            ['truck', 'The right vehicle for the load', 'Cargo vans for parcels, Sprinters for volume, box trucks for large and bulky items.'],
            ['chart', 'Capacity for your peaks', 'Add vans for holidays, promotions and new markets, then scale back when it’s quiet.'],
            ['code', 'Technology that fits', 'Our in-house developers connect delivery updates to your store, system or customer emails.'],
        ],
        'steps' => [['Share your volumes', 'Tell us what you ship, where to and your delivery windows.'], ['We design the routes', 'We plan stops, vehicles and couriers around your cut-off times.'], ['Pilot week', 'Run real deliveries with us and see the proof and updates for yourself.'], ['Go live & grow', 'Add zones, vehicles and days as your volume grows.']],
        'industries' => [['cart', 'Retail & e-commerce'], ['home', 'Furniture & appliances'], ['layers', 'Wholesale & distributors'], ['wrench', 'Auto parts'], ['medical', 'Pharmacies'], ['building', 'Local businesses']],
        'faq' => [
            ['How fast can you deliver?', 'Same-day for orders ready by the cut-off time we agree with you, plus next-day and scheduled delivery windows.'],
            ['Can you deliver large or heavy items?', 'Yes. Tell us about the size and weight and we’ll plan the right vehicle and crew, including box trucks for furniture and appliances.'],
            ['How will my customers know where their delivery is?', 'We send delivery updates by email or straight to your system. Our developers can also set up a simple tracking page for your customers.'],
            ['What happens if nobody is home?', 'We follow your instructions: leave it in a safe place with a photo, try a neighbor, or bring it back for redelivery.'],
        ],
    ],
    'healthcare' => [
        'page' => 'healthcare-delivery.php',
        'hero_photo' => 'hero_healthcare',
        'card_photo' => 'card_healthcare',
        'overview_photo' => ['overview_healthcare', 'Healthcare worker caring for a patient'],
        'icon' => 'medical',
        'title' => 'Healthcare Delivery Solutions',
        'summary' => 'Careful, on-time transport for pharmacies, labs, clinics and hospitals, when it really matters.',
        'points' => ['STAT, same-day and scheduled routes', 'Prescription and pharmacy delivery', 'Lab specimens and medical supplies', 'Temperature-sensitive handling on request', 'Chain of custody and proof of delivery on every run', 'Couriers who respect patient privacy'],
        'for' => ['Pharmacies', 'Labs', 'Hospitals & clinics', 'Medical suppliers'],
        'meta' => 'Healthcare delivery for pharmacies, labs, clinics and hospitals: STAT and scheduled routes, prescriptions, lab specimens and medical supplies with chain of custody.',
        'h1' => 'Healthcare delivery, <span class="accent">handled with care.</span>',
        'lead' => 'Prescriptions, lab specimens and medical supplies run on tight timelines. Our couriers follow your handling rules, keep the chain of custody and confirm every hand-off.',
        'tracker' => ['STAT run · Lab specimens', 'In progress', [
            ['building', 'Picked up at the clinic', 'Signed by front desk · 10:12 AM'],
            ['shield', 'Sealed & logged', 'Chain of custody started'],
            ['thermo', 'In transit', 'Handled per your instructions'],
            ['check', 'Delivered to the lab', 'Signed on receipt · 10:41 AM'],
        ]],
        'highlights' => [['clock', 'STAT & scheduled', 'Same-day and daily routes'], ['shield', 'Chain of custody', 'Every hand-off signed'], ['thermo', 'Temperature-sensitive', 'Handled on request'], ['user', 'Privacy respected', 'Only what’s needed']],
        'overview_title' => 'A courier partner your patients never have to think about',
        'overview' => [
            'In healthcare, a late or mishandled delivery isn’t just an inconvenience. A prescription a patient is waiting for, a specimen that has to reach the lab in time, supplies a clinic needs today: each one matters to someone’s care.',
            'LamazonLoads runs STAT, same-day and scheduled routes for pharmacies, labs, clinics and hospitals. We agree on your handling rules up front: packaging, temperature, signatures and timing. Then our couriers follow them on every run and confirm each hand-off.',
        ],
        'included' => ['STAT, same-day and scheduled routes', 'Pharmacy and prescription home delivery', 'Lab specimen pickup and delivery', 'Medical supplies and equipment', 'Runs between clinics, labs and hospitals', 'Temperature-sensitive handling when you need it', 'Signatures and chain of custody on every hand-off'],
        'benefits_title' => 'Why healthcare teams choose LamazonLoads',
        'benefits' => [
            ['clock', 'Ready for time-critical runs', 'STAT pickups and tight delivery windows planned around your schedule.'],
            ['shield', 'Chain of custody', 'Every pickup and drop-off is signed and logged, so you always know who had it and when.'],
            ['thermo', 'Careful handling', 'We follow your packaging, temperature and handling instructions on every run.'],
            ['user', 'Privacy first', 'Couriers only see what they need to deliver. Packages stay sealed and details stay private.'],
            ['route', 'Reliable recurring routes', 'Daily runs between your locations, with the same courier wherever possible.'],
            ['code', 'Reports & integrations', 'Our developers can send delivery confirmations to your system and build the reports you need.'],
        ],
        'steps' => [['Tell us about your runs', 'What you send, between which locations, how often and how fast.'], ['Agree on a handling plan', 'Packaging, temperature, signatures and timing, written down and shared with your couriers.'], ['Pilot route', 'We run a trial route so your team can see the process and the proof.'], ['Go live', 'Daily, scheduled and STAT runs with one contact who knows your account.']],
        'industries' => [['medical', 'Pharmacies'], ['thermo', 'Labs'], ['building', 'Hospitals & health systems'], ['users', 'Clinics & physician offices'], ['package', 'Medical supply companies'], ['home', 'Home health providers']],
        'faq' => [
            ['Can you handle STAT deliveries?', 'Yes. Tell us your STAT needs and response times on the call and we’ll plan coverage around them, alongside your scheduled routes.'],
            ['Do you handle temperature-sensitive items?', 'Yes, on request. Tell us the required range and packaging, and we’ll plan the handling and timing with you before the first run.'],
            ['How do you protect patient privacy?', 'Couriers only see the information they need to complete the delivery. Packages stay sealed, and we don’t share your patients’ details with anyone.'],
            ['Can you run daily routes between our locations?', 'Yes. Recurring routes between pharmacies, clinics, labs and hospitals are a great fit, with the same courier wherever possible.'],
        ],
    ],
    'dedicated' => [
        'page' => 'dedicated-fleet.php',
        'hero_photo' => 'hero_dedicated',
        'card_photo' => 'card_dedicated',
        'overview_photo' => ['overview_dedicated', 'Two drivers unloading a delivery from a van'],
        'icon' => 'layers',
        'title' => 'Dedicated Fleet & Driver Services',
        'summary' => 'Your own vans and drivers on your schedule, without the hiring headaches.',
        'points' => ['Dedicated cargo vans, Sprinters and box trucks', 'Vetted drivers, onboarded and supported by us', 'Daily routes, recurring runs and peak-season capacity', 'Scheduling, coverage and replacement drivers handled', 'One point of contact for your account'],
        'for' => ['Distributors', 'Contractors', 'Growing businesses'],
        'meta' => 'Dedicated fleet and driver services: cargo vans, Sprinters and box trucks with vetted drivers on your schedule. We handle recruiting, onboarding, scheduling and coverage.',
        'h1' => 'Your own fleet and drivers, <span class="accent">without the headaches.</span>',
        'lead' => 'Get vans, trucks and drivers that work for your business, on your schedule and to your standards, while we handle recruiting, onboarding, scheduling and coverage.',
        'tracker' => ['Your fleet today', 'All routes covered', [
            ['van', 'Van 01 · Marcus', 'On route · 12 of 20 stops'],
            ['van', 'Sprinter 02 · Dana', 'Loading at your dock'],
            ['truck', 'Box truck 03 · Luis', 'Back at the hub'],
            ['users', 'Backup driver', 'Ready if anyone is out'],
        ]],
        'highlights' => [['truck', 'Dedicated vehicles', 'Vans to box trucks'], ['users', 'Vetted drivers', 'Recruited & onboarded by us'], ['calendar', 'Coverage handled', 'Time off & replacements'], ['headset', 'One contact', 'For your whole account']],
        'overview_title' => 'Replace or grow your private fleet',
        'overview' => [
            'Running your own delivery fleet means buying vehicles, hiring and keeping drivers, covering sick days and juggling schedules. That’s time and money that isn’t going into your actual business.',
            'With LamazonLoads Dedicated Fleet & Driver Services, you get vehicles and drivers assigned to your account, working your routes on your schedule and to your standards. We take care of the people side: recruiting, onboarding, scheduling, coverage and support.',
        ],
        'included' => ['Dedicated cargo vans, Sprinter vans and box trucks', 'Drivers recruited, vetted and onboarded by us', 'Driver scheduling, time off and replacement coverage', 'Daily routes, shuttles and recurring runs', 'Extra capacity for peak seasons', 'Driver support and performance follow-up', 'One account contact for everything'],
        'benefits_title' => 'Why businesses choose a dedicated fleet from LamazonLoads',
        'benefits' => [
            ['dollar', 'Predictable costs', 'Turn the cost of buying, insuring and staffing a fleet into one predictable service.'],
            ['briefcase', 'Focus on your business', 'Leave hiring, scheduling and driver issues to us and spend your time on customers.'],
            ['calendar', 'Always covered', 'Sick days, vacations and turnover are our problem. Backup drivers keep routes running.'],
            ['shield', 'Your standards', 'Drivers learn your routes, customers and procedures, and follow them every day.'],
            ['chart', 'Sized to fit', 'Start with one vehicle or a full fleet, and add or remove vehicles as your business changes.'],
            ['code', 'Clear visibility', 'Route updates, delivery confirmations and reports, built by our in-house developers.'],
        ],
        'steps' => [['Review your fleet', 'We look at your routes, volumes, vehicles and schedule.'], ['Plan vehicles & drivers', 'You get a clear plan for the right vehicles, drivers and backup coverage.'], ['Smooth transition', 'We start side by side with your current setup, so nothing gets missed.'], ['Run & improve', 'Regular check-ins to adjust routes, vehicles and drivers as you grow.']],
        'industries' => [['layers', 'Distributors & wholesalers'], ['wrench', 'Contractors & trade suppliers'], ['cart', 'Retail chains'], ['building', 'Manufacturers'], ['medical', 'Healthcare networks'], ['chart', 'Growing businesses']],
        'faq' => [
            ['Do the drivers work only for us?', 'Your routes get dedicated drivers assigned to your account, so they get to know your customers, routes and procedures.'],
            ['What if a driver is sick or on vacation?', 'We handle coverage with backup drivers who know your routes, so your deliveries keep running.'],
            ['Can you drive our own vehicles?', 'Yes. We can provide drivers for your vehicles, provide the vehicles as well, or mix both. Tell us what you have on the call.'],
            ['Can we start small?', 'Yes. Many partners start with a single vehicle or route and grow from there.'],
        ],
    ],
];

/** Request-a-call form values for $page (partners.php or a solution page) and any errors; handles the POST. */
function partner_form_state(string $page, string $preselect = ''): array
{
    $val = ['first_name' => '', 'last_name' => '', 'company' => '', 'job_title' => '', 'email' => '', 'phone' => '',
        'service' => '', 'location' => '', 'volume' => '', 'best_time' => '', 'message' => '', 'consent' => ''];
    $get = (string) ($_GET['service'] ?? '');
    $val['service'] = isset(PARTNER_SERVICES[$get]) ? $get : $preselect;
    $errors = [];
    if (is_post()) {
        csrf_check();
        foreach ($val as $k => $_) {
            $val[$k] = post($k, $k === 'message' ? 3000 : 190);
        }
        if (post('website') !== '') { // hidden field: only bots fill it in
            redirect($page . '?sent=1#request-call');
        }
        [$id, $errors] = partner_submit($val);
        if ($id) {
            redirect($page . '?sent=1#request-call');
        }
    }
    return [$val, $errors];
}

const PARTNER_FAQ = [
    ['Where do you deliver?', 'We work with businesses across the United States. Tell us your location in the form and we\'ll confirm coverage for your routes on the call.'],
    ['Is there a minimum volume?', 'No fixed minimum. Some partners start with a single daily route, others with a full dedicated fleet. A pilot run lets you see how we work before committing.'],
    ['Can you handle medical and pharmacy deliveries?', 'Yes. Healthcare deliveries get careful handling, chain of custody and proof of delivery. Tell us about any temperature or privacy requirements and we\'ll plan the route around them.'],
    ['Can you connect with our systems?', 'Yes. Our in-house developers can send delivery updates to your system, set up a tracking page for your customers or build the reports you need.'],
];

/** The "Request a call" section: steps, promises, quick answers and the form. */
function partner_call_section(array $val, array $errors, string $page, string $heading = 'Let\'s talk about your deliveries', string $lead = 'Share a few details and our partnerships team will reach out to schedule a call at a time that suits you.', array $faq = PARTNER_FAQ): void
{
    $phone = (string) config('contact_phone');
    $email = (string) config('contact_email');
    ?>
<section class="section section-white rc-section" id="request-call">
  <div class="container story level rc-layout">
    <div class="reveal level-col">
      <span class="eyebrow">Request a call</span>
      <h2><?= e($heading) ?></h2>
      <p class="lead"><?= e($lead) ?></p>
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
        <?php foreach ($faq as [$q, $ans]): ?><details><summary><?= e($q) ?></summary><p><?= e($ans) ?></p></details><?php endforeach; ?>
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
        <form method="post" action="<?= e(url($page)) ?>#request-call" class="form-grid" novalidate>
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
                <label class="svc-opt"><input type="radio" name="service" value="<?= e($k) ?>"<?= $val['service'] === $k ? ' checked' : '' ?> required><span><?= icon(SOLUTIONS[$k]['icon'] ?? 'handshake') ?><?= e($l) ?></span></label>
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
<?php
}

/** Closing band on the partner pages. */
function partner_cta_band(string $title = 'Why wait? Let\'s partner.', string $text = 'Tell us about your deliveries and we\'ll show you what the LamazonLoads network can do.'): void
{
    $phone = (string) config('contact_phone');
    ?>
<section class="section-sm"><div class="container">
  <div class="cta-band reveal">
    <div>
      <h2><?= e($title) ?></h2>
      <p><?= e($text) ?></p>
    </div>
    <div class="btns">
      <a class="btn btn-accent btn-lg" href="#request-call">Request a call <?= icon('arrow') ?></a>
      <?php if ($phone !== ''): ?><a class="btn btn-outline-light btn-lg" href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?><?= e($phone) ?></a><?php endif; ?>
    </div>
  </div>
</div></section>
<?php
}

/** Full "Learn more" page for one solution. */
function solution_page(string $key): void
{
    $s = SOLUTIONS[$key];
    [$val, $errors] = partner_form_state($s['page'], $key);
    [$trTitle, $trStatus, $trSteps] = $s['tracker'];
    preload_photo($s['hero_photo']);
    page_header($s['title'], 'partners', $s['meta']);
    ?>
<section class="hero sol-hero has-photo">
  <?= bg_photo($s['hero_photo'], 'center 35%') ?>
  <nav class="container crumbs" aria-label="Breadcrumb"><a href="<?= e(url('partners.php')) ?>">Partners</a><span>/</span><a href="<?= e(url('partners.php')) ?>#solutions">Solutions</a><span>/</span><b><?= e($s['title']) ?></b></nav>
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="pill"><span class="dot"><?= icon($s['icon']) ?></span><?= e($s['title']) ?></span>
      <h1><?= $s['h1'] ?></h1>
      <p class="lead"><?= e($s['lead']) ?></p>
      <div class="hero-actions">
        <a class="btn btn-accent btn-lg" href="#request-call">Request a call <?= icon('arrow') ?></a>
        <a class="btn btn-outline-light btn-lg" href="#overview">How it works</a>
      </div>
    </div>
    <div class="tracker-card" aria-label="Example: <?= e($trTitle) ?>">
      <div class="dc-head"><h3><?= e($trTitle) ?></h3><span class="live"><?= e($trStatus) ?></span></div>
      <ol class="tracker">
        <?php foreach ($trSteps as $i => [$ic, $t, $d]): ?>
          <li style="animation-delay:<?= .35 + $i * .3 ?>s"><span class="tr-ico"><?= icon($ic) ?></span><div><b><?= e($t) ?></b><small><?= e($d) ?></small></div></li>
        <?php endforeach; ?>
      </ol>
      <div class="dc-foot"><span>LamazonLoads · <?= e($s['title']) ?></span><b style="color:var(--blue)">Live</b></div>
    </div>
  </div>
  <div class="road" aria-hidden="true"><?= truck_svg('truck-svg road-truck') ?></div>
</section>

<section class="partner-strip">
  <div class="container partner-strip-row">
    <?php foreach ($s['highlights'] as [$ic, $t, $d]): ?>
      <div class="ps-item"><span class="ps-ico"><?= icon($ic) ?></span><span><b><?= e($t) ?></b><small><?= e($d) ?></small></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section" id="overview">
  <div class="container story level">
    <div class="reveal level-col">
      <span class="eyebrow">Overview</span>
      <h2><?= e($s['overview_title']) ?></h2>
      <?php foreach ($s['overview'] as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
      <div class="level-bottom"><a class="btn btn-primary" href="#request-call">Talk to our team <?= icon('arrow') ?></a></div>
    </div>
    <figure class="sol-photo reveal"><?= photo($s['overview_photo'][0], $s['overview_photo'][1]) ?></figure>
  </div>
  <div class="container">
    <div class="card pad sol-included reveal">
      <h3><?= icon('clipboard') ?>What’s included</h3>
      <ul class="checklist">
        <?php foreach ($s['included'] as $it): ?><li><span class="tick"><?= icon('check') ?></span><span><?= e($it) ?></span></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">Benefits</span><h2><?= e($s['benefits_title']) ?></h2></div>
    <div class="grid grid-3">
      <?php foreach ($s['benefits'] as [$ic, $t, $d]): ?>
        <div class="card feature reveal"><div class="ico"><?= icon($ic) ?></div><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($key === 'dedicated') fleet_showcase('Your fleet', 'Vehicles we can run for you', 'Pick the mix that fits your routes. We supply the drivers, and the vehicles too if you need them.', 'section'); ?>

<section class="section section-dark">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">How it works</span><h2>Up and running in four steps</h2></div>
    <div class="steps steps-dark">
      <?php foreach ($s['steps'] as [$t, $d]): ?>
        <div class="step reveal"><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">Who it’s for</span><h2>Built for businesses like yours</h2></div>
    <div class="ind-grid ind-grid-3">
      <?php foreach ($s['industries'] as [$ic, $t]): ?>
        <div class="ind reveal"><span class="ind-ico"><?= icon($ic) ?></span><b><?= e($t) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partner_call_section($val, $errors, $s['page'], 'Talk to us about ' . $s['title'], 'Share a few details and our partnerships team will reach out to schedule a call. ' . $s['title'] . ' is already selected for you.', $s['faq']); ?>

<section class="section">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">More solutions</span><h2>Explore our other solutions</h2></div>
    <div class="more-sol">
      <?php foreach (SOLUTIONS as $k => $o): if ($k === $key) continue; ?>
        <a class="card more-sol-card reveal" href="<?= e(url($o['page'])) ?>">
          <span class="sol-ico"><?= icon($o['icon']) ?></span>
          <span><b><?= e($o['title']) ?></b><small><?= e($o['summary']) ?></small></span>
          <span class="more-link">Learn more <?= icon('arrow') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partner_cta_band(); page_footer();
}
