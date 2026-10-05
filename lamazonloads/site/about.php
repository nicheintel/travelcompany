<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

page_header('About us', 'about', "LamazonLoads was created from a driver's perspective to advocate for independent drivers and owner-operators and keep them loaded.");
page_hero('About LamazonLoads', "Created from the driver's seat", 'LamazonLoads is a transportation and logistics company focused on helping independent drivers and owner-operators stay moving and get access to freight opportunities.');
?>
<section class="section">
  <div class="container story" style="align-items:start">
    <div class="reveal prose">
      <span class="eyebrow">Our story</span>
      <h2>We understand the wait. That's why we built this.</h2>
      <p>Sitting and waiting for freight. Deadheading empty miles. Losing bids. Fighting to stay profitable. Those aren't stories we heard. We lived them.</p>
      <p>LamazonLoads was built to change that. It's more than a dispatch service: it's a driver-support and logistics network that connects drivers with available loads, daily routes, dispatchers and other opportunities.</p>
      <p>We're building a serious logistics operation, with strong driver recruiting, organized dispatch and support teams, daily-route contracts and freight booking, and we're growing it with drivers at the center.</p>
    </div>
    <div class="card pad reveal" style="background:linear-gradient(160deg,#0A2463,#071A4A);color:#C9D6F5;border:0">
      <img src="<?= e(asset('brand/mark.svg')) ?>" alt="" width="72" height="72" style="border-radius:18px;margin-bottom:20px">
      <span class="eyebrow">Our motto</span>
      <p class="quote" style="margin-top:0">Why wait?<br>Let's freight.</p>
      <p class="mb-0">One word, one mission: <b style="color:#fff">LamazonLoads</b> keeps drivers moving.</p>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">What we stand for</span><h2>Our values</h2></div>
    <div class="grid grid-4">
      <div class="card feature reveal"><div class="ico"><?= icon('handshake') ?></div><h3>Drivers first</h3><p>Every decision starts with what keeps drivers loaded and profitable.</p></div>
      <div class="card feature reveal"><div class="ico"><?= icon('clock') ?></div><h3>No wasted time</h3><p>Less waiting, less deadhead, more miles that pay.</p></div>
      <div class="card feature reveal"><div class="ico"><?= icon('shield') ?></div><h3>Straight talk</h3><p>Clear rates, clear details, clear follow-ups.</p></div>
      <div class="card feature reveal"><div class="ico"><?= icon('users') ?></div><h3>Community</h3><p>We grow by helping each other find opportunities.</p></div>
    </div>
  </div>
</section>

<?php cta_band(); page_footer();
