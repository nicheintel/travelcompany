<?php
require __DIR__ . '/includes/bootstrap.php';

$memberPct = (int) round(member_discount_rate() * 100);
$whyUs = [
    ['tag', 'Low fares, no surprises', 'We compare hundreds of airlines and hotels so you see the real price up front — taxes and fees included.'],
    ['package', 'Bundle & save', 'Combine your flight, hotel and car in one booking and unlock package-only rates.'],
    ['headset', 'A real travel assistant', 'Our team helps you plan, change or cancel — by chat or phone, whenever you need us.'],
    ['shield', 'Secure booking', 'Your account and payment details are protected with industry-standard encryption.'],
];
$steps = [
    ['search', 'Search', 'Tell us where and when. We find the cheapest flights, stays and bundles.'],
    ['tag', 'Compare', 'Filter by price, stops, airline or hotel rating and pick what fits.'],
    ['user', 'Book with your account', 'Sign in to save trips, get price alerts and check out in seconds.'],
];
$faq = [
    ['How do you find affordable flights?', 'We search many airlines and booking sources at once and sort the results so the best-value options appear first. Flexible dates usually unlock even lower fares.'],
    ['What is included in a promo package?', 'Every package includes round-trip flights and a hotel stay. Many also include a rental car, airport transfers, breakfast or tours — check the package details.'],
    ['Do I need an account to book?', "You can search without an account. To book, save trips and receive member-only deals, you'll sign in or create a free account."],
    ['Can I change or cancel my booking?', 'It depends on the fare or hotel rules. Refundable options are clearly marked, and our assistants can help with changes.'],
];
$tabs = [['flights', 'Flights', 'plane'], ['packages', 'Flight + Hotel + Car', 'package'], ['hotels', 'Hotels', 'bed']];
require __DIR__ . '/includes/header.php';
?>
<section class="relative bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600">
  <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
  <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.12]" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path d="M-50 480 C 250 380, 450 560, 700 420 S 1100 220, 1300 300" fill="none" stroke="white" stroke-width="2" stroke-dasharray="10 12"/>
    <circle cx="980" cy="120" r="140" fill="white" opacity="0.4"/><circle cx="160" cy="80" r="60" fill="white" opacity="0.3"/>
  </svg>
  <?= icon('plane', 120, 'pointer-events-none absolute right-[8%] top-24 hidden rotate-12 text-white/20 lg:block', 1) ?>
  </div>
  <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-14 sm:px-6 sm:pt-20 lg:pb-24">
    <div class="max-w-2xl text-white">
      <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium ring-1 ring-white/20"><span class="h-2 w-2 rounded-full bg-accent-400"></span>Your personal travel assistant</p>
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">Fly further. <span class="text-accent-400">Pay less.</span></h1>
      <p class="mt-4 text-lg text-brand-100 sm:text-xl">Affordable flights, hand-picked hotels and money-saving Flight + Hotel + Car packages — all in one place.</p>
    </div>
    <div class="mt-10 rounded-2xl bg-white shadow-2xl shadow-brand-950/20" data-tabs>
      <div role="tablist" class="flex overflow-x-auto border-b border-slate-100 px-2 sm:px-4">
        <?php foreach ($tabs as $i => [$id, $label, $ic]): ?>
          <button type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-tab="<?= $id ?>"
            class="flex shrink-0 items-center gap-2 border-b-2 px-4 py-4 text-sm font-semibold transition border-transparent text-slate-500 hover:text-slate-800 aria-selected:border-brand-600 aria-selected:text-brand-700">
            <?= icon($ic, 18) ?><?= $label ?>
            <?php if ($id === 'packages'): ?><span class="rounded-full bg-accent-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Save</span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="p-4 sm:p-6">
        <div role="tabpanel" data-panel="flights"><?= flight_search_form() ?></div>
        <div role="tabpanel" data-panel="packages" class="hidden"><?= package_search_form() ?></div>
        <div role="tabpanel" data-panel="hotels" class="hidden"><?= hotel_search_form() ?></div>
      </div>
    </div>
    <ul class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-sm text-brand-100">
      <li class="flex items-center gap-2"><?= icon('shield', 16) ?> Secure booking</li>
      <li class="flex items-center gap-2"><?= icon('tag', 16) ?> No hidden fees</li>
      <li class="flex items-center gap-2"><?= icon('headset', 16) ?> 24/7 travel support</li>
    </ul>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ([
        ['packages.php', 'from-accent-400 to-accent-600', 'package', 'Packages', 'Flight + Hotel + Car', 'Save up to 40% when you bundle.', 'See promo packages →'],
        ['flights.php', 'from-sky-500 to-brand-700', 'plane', 'Flights', 'Weekend getaways', 'Round trips from $199.', 'Find cheap flights →'],
        ['register.php', 'from-emerald-500 to-teal-700', 'user', 'Members', $memberPct > 0 ? "Extra $memberPct% off" : 'Save your trips', $memberPct > 0 ? 'Free account, member-only prices.' : 'Free account, faster booking and price alerts.', 'Create free account →'],
    ] as [$href, $grad, $ic, $kicker, $heading, $text, $cta]): ?>
      <a href="<?= e(url($href)) ?>" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br <?= $grad ?> p-6 text-white shadow-lg">
        <?= icon($ic, 120, 'absolute -bottom-4 -right-4 text-white/20') ?>
        <p class="text-sm font-semibold uppercase tracking-wider text-white/80"><?= $kicker ?></p>
        <h3 class="mt-2 text-2xl font-bold"><?= e($heading) ?></h3>
        <p class="mt-1 text-white/90"><?= e($text) ?></p>
        <span class="mt-4 inline-block font-semibold underline-offset-4 group-hover:underline"><?= $cta ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6">
  <div class="mb-6 flex items-end justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Popular destinations</h2>
      <p class="mt-1 text-slate-600">Round-trip fares from New York this season.</p>
    </div>
    <a href="<?= e(url('flights.php')) ?>" class="hidden text-sm font-semibold text-brand-700 hover:underline sm:block">Explore all flights →</a>
  </div>
  <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
    <?php foreach (POPULAR_DESTINATIONS as [$city, $country, $code, $price, $grad]): ?>
      <a href="<?= e(url('flights.php', ['from' => 'JFK', 'to' => $code])) ?>" class="group relative flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br <?= $grad ?> p-4 text-white shadow-md transition hover:-translate-y-1 hover:shadow-xl">
        <span class="absolute right-3 top-3 rounded-md bg-black/20 px-2 py-0.5 font-mono text-xs font-semibold backdrop-blur"><?= $code ?></span>
        <p class="text-xs font-medium text-white/80"><?= e($country) ?></p>
        <p class="text-lg font-bold"><?= e($city) ?></p>
        <p class="mt-1 text-sm">from <span class="font-bold"><?= money($price) ?></span></p>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-white py-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <div class="mb-6 flex items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-accent-600">Promo packages</p>
        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">This week's best bundles</h2>
      </div>
      <a href="<?= e(url('packages.php')) ?>" class="text-sm font-semibold text-brand-700 hover:underline">View all packages →</a>
    </div>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach (array_slice(promo_packages(), 0, 4) as $p) echo package_card($p); ?>
    </div>
  </div>
</section>

<section id="why-us" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
  <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl">Why travelers book with us</h2>
  <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($whyUs as [$ic, $heading, $body]): ?>
      <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-600"><?= icon($ic, 24) ?></span>
        <h3 class="mt-4 font-semibold text-slate-900"><?= e($heading) ?></h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($body) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-brand-50 py-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl">How it works</h2>
    <ol class="mt-10 grid gap-6 md:grid-cols-3">
      <?php foreach ($steps as $i => [$ic, $heading, $body]): ?>
        <li class="relative rounded-2xl bg-white p-6 shadow-sm">
          <span class="absolute right-5 top-4 text-5xl font-black text-brand-100"><?= $i + 1 ?></span>
          <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white"><?= icon($ic, 22) ?></span>
          <h3 class="mt-4 text-lg font-semibold text-slate-900"><?= e($heading) ?></h3>
          <p class="mt-2 text-sm text-slate-600"><?= e($body) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-16 sm:px-6">
  <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl">Frequently asked questions</h2>
  <div class="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
    <?php foreach ($faq as [$q, $a]): ?>
      <details class="group p-5">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900"><?= e($q) ?><span class="text-xl text-brand-600 transition group-open:rotate-45">+</span></summary>
        <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<section id="newsletter" class="scroll-mt-20 px-4 pb-16 sm:px-6">
  <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-r from-brand-700 to-brand-500 px-6 py-12 text-white sm:px-12">
    <?= icon('car', 200, 'absolute -right-6 -top-6 text-white/10') ?>
    <div class="relative grid items-center gap-8 lg:grid-cols-2">
      <div>
        <h2 class="text-3xl font-bold">Get member-only deals</h2>
        <p class="mt-2 text-brand-100">Create a free account to save searches, track prices and unlock secret fares.</p>
      </div>
      <div class="flex flex-col gap-3 sm:flex-row lg:justify-end">
        <a href="<?= e(url('register.php')) ?>" class="rounded-xl bg-accent-500 px-6 py-3 text-center font-bold text-white shadow-lg hover:bg-accent-600">Create free account</a>
        <a href="<?= e(url('signin.php')) ?>" class="rounded-xl bg-white/10 px-6 py-3 text-center font-semibold text-white ring-1 ring-white/30 hover:bg-white/20">I already have an account</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
