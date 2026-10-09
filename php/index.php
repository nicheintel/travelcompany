<?php
require __DIR__ . '/includes/bootstrap.php';

$memberPct = (int) round(member_discount_rate() * 100);
$me = current_user();
$whyUs = [
    ['tag', t('Low fares, no surprises'), t('We compare hundreds of airlines and hotels so you see the real price up front — taxes and fees included.')],
    ['package', t('Ready-made packages'), t('Flights and hotel in one booking at one price per person, put together by our travel assistants.')],
    ['headset', t('A real travel assistant'), t('Our team helps you plan, change or cancel — by chat or phone, whenever you need us.')],
    ['shield', t('Secure booking'), t('Your account and payment details are protected with industry-standard encryption.')],
];
$steps = [
    ['search', t('Search'), t('Tell us where and when. We find the cheapest flights, stays and bundles.')],
    ['tag', t('Compare'), t('Filter by price, stops, airline or hotel rating and pick what fits.')],
    ['user', t('Book with your account'), t('Your trips, confirmations and tickets stay together in one place — and checkout takes seconds.')],
];
$faq = array_slice(faq_items(), 0, 5);
$tabs = [['flights', t('Flights'), 'plane'], ['packages', t('Flight + Hotel'), 'package'], ['hotels', t('Hotels'), 'bed']];
$promos = active_packages();
// Top banner slideshow: photos in assets/hero/ (AVIF, with WebP for older browsers); the caption links to fares there.
$heroSlides = [];
foreach ([['maldives', 'Maldives', 'MLE'], ['santorini', 'Santorini, Greece', 'JTR'], ['krabi', 'Krabi, Thailand', 'KBV'],
          ['bali', 'Bali, Indonesia', 'DPS'], ['halong', 'Ha Long Bay, Vietnam', 'HAN']] as [$file, $place, $code]) {
    // Phones get the upright 1080 photo; wider screens the 1440 or 2000 one.
    $set = fn(string $ext) => asset("hero/$file-1440.$ext") . ' 1440w, ' . asset("hero/$file-2000.$ext") . ' 2000w';
    $heroSlides[] = ['place' => $place, 'href' => url('flights.php', ['to' => $code]), 'avif' => $set('avif'), 'webp' => $set('webp'),
        'phone_avif' => asset("hero/$file-1080.avif"), 'phone_webp' => asset("hero/$file-1080.webp"), 'src' => asset("hero/$file-1440.webp")];
}
require __DIR__ . '/includes/header.php';
?>
<section class="relative bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600" data-hero>
  <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
    <div class="absolute inset-0 isolate"><?php /* photos stay in their own layer, under the blue shade and the plane */ ?>
    <?php foreach ($heroSlides as $i => $s): $lazy = $i > 0 ? 'data-' : ''; ?>
      <picture class="hero-slide<?= $i === 0 ? ' on' : '' ?>" data-place="<?= e($s['place']) ?>" data-href="<?= e($s['href']) ?>">
        <source type="image/avif" media="(max-width: 767px)" <?= $lazy ?>srcset="<?= e($s['phone_avif']) ?>">
        <source type="image/avif" sizes="100vw" <?= $lazy ?>srcset="<?= e($s['avif']) ?>">
        <source type="image/webp" media="(max-width: 767px)" <?= $lazy ?>srcset="<?= e($s['phone_webp']) ?>">
        <img alt="" decoding="async" data-fade sizes="100vw" <?= $lazy ?>srcset="<?= e($s['webp']) ?>" <?= $lazy ?>src="<?= e($s['src']) ?>"<?= $i === 0 ? ' fetchpriority="high"' : '' ?>>
      </picture>
    <?php endforeach; ?>
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-brand-950/85 via-brand-950/45 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-brand-950/75 to-transparent"></div>
    <svg class="absolute inset-0 h-full w-full opacity-30" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice">
      <path class="hero-route" d="M-50 430 C 250 330, 520 470, 760 290 S 1080 70, 1300 120" fill="none" stroke="white" stroke-width="2" stroke-dasharray="10 12"/>
    </svg>
    <svg class="hero-flight absolute inset-0 h-full w-full" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice">
      <g><animateMotion dur="18s" repeatCount="indefinite" rotate="auto" path="M-50 430 C 250 330, 520 470, 760 290 S 1080 70, 1300 120"/>
        <g transform="rotate(45) translate(-12 -12) scale(1.6)" fill="#e9c46a" stroke="#e9c46a" stroke-width="1" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></g>
      </g>
    </svg>
  </div>
  <div class="relative z-20 mx-auto max-w-7xl px-4 pb-12 pt-16 sm:px-6 sm:pt-24 lg:pb-16 lg:pt-28">
    <div class="max-w-2xl text-white">
      <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium ring-1 ring-white/20"><span class="h-2 w-2 rounded-full bg-accent-400"></span><?= e(t('Your personal travel assistant')) ?></p>
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight drop-shadow-sm sm:text-5xl lg:text-6xl"><?= e(t('Fly further.')) ?> <span class="gold-text text-accent-400"><?= e(t('Pay less.')) ?></span></h1>
      <p class="mt-4 text-lg text-brand-100 sm:text-xl"><?= e(t('Affordable flights, hotels and Flight + Hotel packages — all in one place.')) ?></p>
    </div>
    <div class="hero-rise mt-10 rounded-2xl bg-white shadow-2xl shadow-brand-950/20" data-tabs>
      <div role="tablist" class="flex overflow-x-auto border-b border-slate-100 px-2 sm:px-4">
        <?php foreach ($tabs as $i => [$id, $label, $ic]): ?>
          <button type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-tab="<?= $id ?>"
            class="flex shrink-0 items-center gap-2 border-b-2 px-4 py-4 text-sm font-semibold transition border-transparent text-slate-500 hover:text-slate-800 aria-selected:border-brand-600 aria-selected:text-brand-700">
            <?= icon($ic, 18) ?><?= e($label) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="p-4 sm:p-6">
        <div role="tabpanel" data-panel="flights"><?= flight_search_form() ?></div>
        <div role="tabpanel" data-panel="packages" class="hidden"><?= package_search_form() ?></div>
        <div role="tabpanel" data-panel="hotels" class="hidden"><?= hotel_search_form() ?></div>
      </div>
    </div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-x-8 gap-y-4">
    <ul class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-brand-100">
      <li class="flex items-center gap-2"><?= icon('shield', 16) ?> <?= e(t('Secure booking')) ?></li>
      <li class="flex items-center gap-2"><?= icon('tag', 16) ?> <?= e(t('No hidden fees')) ?></li>
      <li class="flex items-center gap-2"><?= icon('headset', 16) ?> <?= e(t('24/7 travel support')) ?></li>
    </ul>
    <div class="flex items-center gap-3">
      <a href="<?= e($heroSlides[0]['href']) ?>" class="hero-caption" data-hero-caption><?= icon('pin', 15) ?><span data-hero-place><?= e($heroSlides[0]['place']) ?></span><span class="text-accent-400"><?= e(t('See fares →')) ?></span></a>
      <div class="flex" data-hero-dots>
        <?php foreach ($heroSlides as $i => $s): ?><button type="button" class="hero-dot" aria-label="<?= e($s['place']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button><?php endforeach; ?>
      </div>
    </div>
    </div>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3" data-reveal-stagger>
    <?php foreach ([
        ['packages.php', 'from-accent-500 to-accent-700', 'package', t('Packages'), t('Flight + Hotel'), t('Ready-made trips at one price per person.'), t('See promo packages →')],
        ['flights.php', 'from-sky-500 to-brand-700', 'plane', t('Flights'), t('Compare airlines'), t('Real fares from airlines worldwide.'), t('Search flights →')],
        $me
            ? ['account.php', 'from-emerald-500 to-teal-700', 'user', t('Your account'), t('Hi, {name}!', ['name' => explode(' ', $me['name'])[0]]), $memberPct > 0 ? t('Your {pct}% member discount is applied automatically on flights and hotels.', ['pct' => $memberPct]) : t('Your trips, confirmations and tickets in one place.'), t('My trips →')]
            : ['register.php', 'from-emerald-500 to-teal-700', 'user', t('Members'), $memberPct > 0 ? t('Extra {pct}% off', ['pct' => $memberPct]) : t('Save your trips'), $memberPct > 0 ? t('Member-only prices on flights and hotels.') : t('Faster booking, all your trips in one place.'), t('Create account') . ' →'],
    ] as $tileNo => [$href, $grad, $ic, $kicker, $heading, $text, $cta]): ?>
      <a href="<?= e(url($href)) ?>" class="group relative isolate overflow-hidden rounded-2xl bg-gradient-to-br <?= $grad ?> p-6 text-white shadow-lg transition hover:-translate-y-1 hover:shadow-xl">
        <?= photo_fill(['tile-packages', 'tile-flights', 'tile-members'][$tileNo]) ?>
        <div class="absolute inset-0 bg-gradient-to-br <?= $grad ?> opacity-80"></div>
        <?= icon($ic, 120, 'absolute -bottom-4 -right-4 text-white/20 transition duration-500 group-hover:-rotate-6 group-hover:scale-110') ?>
        <div class="relative">
          <p class="text-sm font-semibold uppercase tracking-wider text-white/80"><?= e($kicker) ?></p>
          <h2 class="mt-2 text-2xl font-bold drop-shadow-sm"><?= e($heading) ?></h2>
          <p class="mt-1 text-white/90"><?= e($text) ?></p>
          <span class="mt-4 inline-block font-semibold underline-offset-4 group-hover:underline"><?= e($cta) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6">
  <div class="mb-6 flex items-end justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('Popular destinations')) ?></h2>
      <p class="mt-1 text-slate-600"><?= e(t('Pick a destination, then choose where you\'re flying from.')) ?></p>
    </div>
    <a href="<?= e(url('flights.php')) ?>" class="hidden text-sm font-semibold text-brand-700 hover:underline sm:block"><?= e(t('Explore all flights →')) ?></a>
  </div>
  <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6" data-reveal-stagger>
    <?php foreach (POPULAR_DESTINATIONS as [$city, $country, $code, $grad, $unsplash]): [$photo, $isDefault] = destination_photo($code, $unsplash); ?>
      <a href="<?= e(url('flights.php', ['to' => $code])) ?>" class="group relative flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br <?= $grad ?> p-4 text-white shadow-md transition hover:-translate-y-1 hover:shadow-xl">
        <picture><?php if ($isDefault): ?><source type="image/avif" srcset="<?= e(stock_photo($code)['avif']) ?>"><?php endif; ?><img src="<?= e($photo) ?>" alt="<?= e($city) ?>" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"></picture><span class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent"></span>
        <span class="absolute right-3 top-3 rounded-md bg-black/20 px-2 py-0.5 font-mono text-xs font-semibold backdrop-blur"><?= $code ?></span>
        <p class="relative text-xs font-medium text-white/80"><?= e($country) ?></p>
        <p class="relative text-lg font-bold"><?= e($city) ?></p>
        <p class="relative mt-1 text-sm font-semibold"><?= e(t('See fares →')) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($promos): ?>
<section class="bg-white py-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <div class="mb-6 flex items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-accent-700"><?= e(t('Promo packages')) ?></p>
        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('Current package deals')) ?></h2>
      </div>
      <a href="<?= e(url('packages.php')) ?>" class="text-sm font-semibold text-brand-700 hover:underline"><?= e(t('View all packages →')) ?></a>
    </div>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-reveal-stagger>
      <?php foreach (array_slice($promos, 0, 4) as $p) echo package_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="why-us" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
  <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('Why travelers book with us')) ?></h2>
  <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-reveal-stagger>
    <?php foreach ($whyUs as [$ic, $heading, $body]): ?>
      <div class="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-lg">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white"><?= icon($ic, 24) ?></span>
        <h3 class="mt-4 font-semibold text-slate-900"><?= e($heading) ?></h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($body) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-brand-50 py-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('How it works')) ?></h2>
    <ol class="mt-10 grid gap-6 md:grid-cols-3" data-reveal-stagger>
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
  <h2 class="text-center text-2xl font-bold text-slate-900 sm:text-3xl"><?= e(t('Frequently asked questions')) ?></h2>
  <div class="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white" data-reveal>
    <?php foreach ($faq as [$q, $a]): ?>
      <details class="group p-5">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900"><?= e($q) ?><span class="text-xl text-brand-600 transition group-open:rotate-45">+</span></summary>
        <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
  <p class="mt-6 text-center text-sm"><a href="<?= e(url('help.php#faq')) ?>" class="font-semibold text-brand-700 hover:underline"><?= e(t('More questions? Visit our Help center →')) ?></a></p>
</section>

<section id="newsletter" class="scroll-mt-20 px-4 pb-16 sm:px-6">
  <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-r from-brand-700 to-brand-500 px-6 py-12 text-white sm:px-12" data-reveal>
    <?= icon('car', 200, 'absolute -right-6 -top-6 text-white/10') ?>
    <div class="relative grid items-center gap-8 lg:grid-cols-2">
      <?php if ($me): ?>
        <div>
          <h2 class="text-3xl font-bold"><?= e(t('Welcome back, {name}!', ['name' => explode(' ', $me['name'])[0]])) ?></h2>
          <p class="mt-2 text-brand-100"><?= e($memberPct > 0 ? t('Your {pct}% member discount is applied automatically when you book flights and hotels.', ['pct' => $memberPct]) : t('Ready for your next trip? Your bookings and tickets are waiting in My trips.')) ?></p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row lg:justify-end">
          <a href="<?= e(url('flights.php')) ?>" class="rounded-xl bg-accent-500 px-6 py-3 text-center font-bold text-white shadow-lg hover:bg-accent-600"><?= e(t('Search flights')) ?></a>
          <a href="<?= e(url('account.php')) ?>" class="rounded-xl bg-white/10 px-6 py-3 text-center font-semibold text-white ring-1 ring-white/30 hover:bg-white/20"><?= e(t('My trips')) ?></a>
        </div>
      <?php else: ?>
        <div>
          <h2 class="text-3xl font-bold"><?= e(t('Get member-only deals')) ?></h2>
          <p class="mt-2 text-brand-100"><?= e(t('Create an account to book faster, keep all your trips in one place and get member prices.')) ?></p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row lg:justify-end">
          <a href="<?= e(url('register.php')) ?>" class="rounded-xl bg-accent-500 px-6 py-3 text-center font-bold text-white shadow-lg hover:bg-accent-600"><?= e(t('Create account')) ?></a>
          <a href="<?= e(url('signin.php')) ?>" class="rounded-xl bg-white/10 px-6 py-3 text-center font-semibold text-white ring-1 ring-white/30 hover:bg-white/20"><?= e(t('I already have an account')) ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
