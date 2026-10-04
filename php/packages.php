<?php
require __DIR__ . '/includes/bootstrap.php';

$to = airport(param('to'));
$from = airport(param('from'));
$depart = date_param(param('depart'), earliest_date());
$return = date_param(param('return'), (string) $depart);
$withCar = param('car') === '1';
$adults = int_param('adults', 2, 1, 6);
$matches = [];
if ($to) {
    $matches = array_values(array_filter(promo_packages(), fn($p) => $p['code'] === $to['code']));
    if ($withCar) usort($matches, fn($a, $b) => (int) $b['car'] <=> (int) $a['car']);
}
$bookQuery = http_build_query(array_filter(['from' => $from['code'] ?? null, 'depart' => $depart, 'adults' => isset($_GET['adults']) ? $adults : null]));
$separate = [['plane', 'Flight', 620], ['bed', 'Hotel (5 nights)', 540], ['car', 'Car rental', 190]];
$separateTotal = array_sum(array_column($separate, 2));
$bundle = (int) round($separateTotal * 0.68);
$title = 'Promo packages — Flight + Hotel + Car';
require __DIR__ . '/includes/header.php';
?>
<section class="relative overflow-hidden bg-gradient-to-br from-accent-600 via-rose-500 to-brand-700">
  <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-15" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><circle cx="1050" cy="90" r="120" fill="white"/><path d="M0 420 Q300 340 600 400 T1200 380 V500 H0Z" fill="white"/></svg>
  <div class="relative mx-auto max-w-7xl px-4 pb-14 pt-12 sm:px-6 sm:pt-16">
    <div class="max-w-2xl text-white">
      <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-semibold ring-1 ring-white/25">Limited-time promo</p>
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">Flight + Hotel + Car.<br><span class="text-yellow-200">One booking. Bigger savings.</span></h1>
      <p class="mt-4 text-lg text-white/90">Our travel assistants bundle airfare, hand-picked hotels and rental cars into packages that cost less than booking each one separately.</p>
    </div>
    <div class="mt-8 rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
      <?= package_search_form(['from' => $from['code'] ?? 'JFK', 'to' => $to['code'] ?? null, 'depart' => (string) $depart, 'return' => (string) $return, 'car' => $to ? $withCar : true, 'adults' => $adults]) ?>
    </div>
  </div>
</section>

<?php if ($to): ?>
<section id="results" class="mx-auto max-w-7xl scroll-mt-20 px-4 pt-12 sm:px-6">
  <div class="rounded-2xl border border-brand-200 bg-brand-50 p-6">
    <h2 class="text-xl font-bold text-slate-900">Packages to <?= e($to['city']) ?><?= $from ? ' from ' . e($from['city']) : '' ?></h2>
    <?php if ($depart && $return): ?><p class="mt-1 text-sm text-slate-600"><?= e(fmt_date($depart)) ?> – <?= e(fmt_date($return)) ?><?= $withCar ? ' · with rental car' : '' ?></p><?php endif; ?>
    <?php if ($matches): ?>
      <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><?php foreach ($matches as $m) echo package_card($m, $bookQuery); ?></div>
    <?php else: ?>
      <p class="mt-4 text-slate-700">We don't have a ready-made promo to <?= e($to['city']) ?> right now — but our travel assistants can build a custom bundle for you. Browse the current promos below, or create an account and we'll alert you when a <?= e($to['city']) ?> deal drops.</p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6" data-results>
  <div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">All promo packages</h2>
    <p class="mt-1 text-slate-600">Prices are per person, based on two travelers sharing a room, including taxes.</p>
  </div>
  <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-wrap gap-2">
      <?php foreach (array_merge(['All'], PACKAGE_CATEGORIES) as $c): ?>
        <label class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium transition bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-slate-300 has-[:checked]:bg-slate-900 has-[:checked]:text-white has-[:checked]:ring-slate-900">
          <input type="radio" name="category" value="<?= $c ?>"<?= $c === 'All' ? ' checked' : '' ?> data-filter-chip="category" class="sr-only"><?= $c ?></label>
      <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap items-center gap-4 text-sm">
      <label class="flex cursor-pointer items-center gap-2 text-slate-700"><input type="checkbox" data-filter-flag="car" class="h-4 w-4 accent-brand-600"> Includes rental car</label>
      <label class="flex items-center gap-2 text-slate-700">Sort by
        <select class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium" data-sort-select>
          <option value="">Recommended</option><option value="price">Lowest price</option><option value="-savings">Biggest savings</option><option value="-rating">Guest rating</option>
        </select>
      </label>
    </div>
  </div>
  <p class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600" data-empty>No packages match these filters yet. Try another category.</p>
  <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-list><?php foreach (promo_packages() as $pkg) echo package_card($pkg); ?></div>
</section>

<section class="bg-white py-14">
  <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2">
    <div>
      <p class="text-sm font-semibold uppercase tracking-wider text-accent-600">Why bundle?</p>
      <h2 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">Package rates you can't get separately</h2>
      <p class="mt-3 text-slate-600">Airlines, hotels and car companies give us special rates when they're sold together. That discount goes straight to you.</p>
      <ul class="mt-6 space-y-3 text-slate-700">
        <?php foreach (['One itinerary, one payment, one confirmation', 'Free airport-to-hotel planning help', 'Change support from a real travel assistant'] as $t): ?>
          <li class="flex items-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-600"><?= icon('check', 14) ?></span><?= e($t) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
      <p class="text-sm font-semibold text-slate-500">Example: 5 nights in Cancún</p>
      <ul class="mt-4 space-y-3">
        <?php foreach ($separate as [$ic, $item, $price]): ?>
          <li class="flex items-center justify-between"><span class="flex items-center gap-3 text-slate-700"><?= icon($ic, 18, 'text-brand-600') ?><?= $item ?></span><span class="text-slate-500"><?= money($price) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4"><span class="text-slate-600">Booked separately</span><span class="font-semibold text-slate-500 line-through"><?= money($separateTotal) ?></span></div>
      <div class="mt-2 flex items-center justify-between rounded-xl bg-emerald-50 p-4"><span class="font-semibold text-emerald-800">Booked as a package</span><span class="text-2xl font-extrabold text-emerald-700"><?= money($bundle) ?></span></div>
      <p class="mt-2 text-right text-sm font-semibold text-emerald-700">You save <?= money($separateTotal - $bundle) ?></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
