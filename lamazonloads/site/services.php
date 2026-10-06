<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

page_header('Services', 'services', 'Freight dispatching, daily routes, driver onboarding, support, load coordination and community for independent drivers and owner-operators.');
page_hero('What we do', 'Services built to keep you loaded', 'Dispatching is where we start. Support, routes and community are what keep you moving.');

$services = [
    ['truck', 'Freight dispatching', 'We search, negotiate and book loads so you can focus on driving.', ['Load board and broker searches every day', 'Rate negotiation on your behalf', 'Booking, rate confirmations and broker setup packets', 'Lanes planned around your home time']],
    ['route', 'Daily route opportunities', 'Dedicated and local delivery routes when contracts are available.', ['Local and last-mile delivery routes', 'Dedicated contract work', 'Matched by ZIP code, equipment and availability', 'Onboarded drivers are offered routes first']],
    ['clipboard', 'Driver onboarding', 'One onboarding, stored securely, ready when the next load or route opens.', ['Vehicle and equipment information', 'Home ZIP code and availability', 'Insurance and W-9 collection', "Driver's license, registration and authority"]],
    ['headset', 'Driver support', 'You are never on your own out there.', ['Dedicated driver support groups', 'Direct line to management and support representatives', 'Help with brokers, detention and issues on the road', 'Follow-ups so nothing falls through the cracks']],
    ['calendar', 'Load coordination', 'The details handled from pickup to payment.', ['Pickup and drop-off details', 'Driver scheduling and route assignments', 'Payment tracking', 'Proof of delivery and follow-ups']],
    ['users', 'Community', 'A network of people who want you to win.', ['Drivers, dispatchers and entrepreneurs', 'Opportunities shared across the network', 'Tips to stay productive and profitable', 'Grow from one truck to a fleet']],
];
?>
<section class="section">
  <div class="container grid grid-3">
    <?php foreach ($services as [$ic, $t, $d, $items]): ?>
      <div class="card feature reveal">
        <div class="ico"><?= icon($ic) ?></div>
        <h3><?= e($t) ?></h3>
        <p><?= e($d) ?></p>
        <ul><?php foreach ($items as $it): ?><li><?= icon('check') ?><span><?= e($it) ?></span></li><?php endforeach; ?></ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php fleet_showcase('Equipment', 'What we dispatch', 'Cargo vans, Sprinter vans and box trucks, plus hotshot and other qualified equipment. If it\'s qualified and insured, we want to talk.'); ?>

<?php cta_band(); page_footer();
