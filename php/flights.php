<?php
require __DIR__ . '/includes/bootstrap.php';

$p = parse_flight_params($_GET);
$s = $p['search'];
$result = $s ? search_flights($s) : null;
$offers = $result['items'] ?? [];
$people = $p['adults'] + $p['children'] + $p['infants'];
$dur = fn($o) => $o['outbound']['duration'] + ($o['inbound']['duration'] ?? 0);

$summary = null;
if ($offers) {
    $minPrice = min(array_column($offers, 'total'));
    $minDur = min(array_map($dur, $offers));
    foreach ($offers as &$o) {
        $o['best'] = $o['total'] / $minPrice + $dur($o) / $minDur * 0.7;
    }
    unset($o);
    $pick = fn(callable $key) => array_reduce($offers, fn($c, $o) => $c === null || $key($o) < $key($c) ? $o : $c);
    $summary = ['best' => $pick(fn($o) => $o['best']), 'cheapest' => $pick(fn($o) => $o['total']), 'fastest' => $pick($dur)];
    $airlines = [];
    foreach ($offers as $o) {
        $c = $o['airline']['code'] ?: $o['airline']['name'];
        $airlines[$c] = ['name' => $o['airline']['name'], 'min' => min($airlines[$c]['min'] ?? PHP_INT_MAX, $o['total'])];
    }
    asort($airlines);
    $priceMax = max(array_column($offers, 'total'));
}
$popular = [['JFK', 'LHR'], ['LAX', 'NRT'], ['JFK', 'CUN'], ['SFO', 'MNL'], ['MIA', 'BCN'], ['ORD', 'DXB']];
$description = 'Compare live fares from airlines worldwide and book with help from a real travel assistant.';
$title = $s ? t('{from} to {to} flights', ['from' => $s['from']['city'], 'to' => $s['to']['city']]) : 'Cheap flights';
require __DIR__ . '/includes/header.php';
?>
<section class="relative overflow-hidden bg-gradient-to-br from-brand-900 to-brand-700 pb-10 pt-10">
  <?= photo_backdrop('flights', 'bg-gradient-to-r from-brand-950/85 via-brand-900/60 to-brand-900/25') ?>
  <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
    <div class="mb-5 text-white">
      <?php if ($s): ?>
        <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold sm:text-3xl"><?= e($s['from']['city']) ?> <?= icon('plane', 20, 'rotate-45 text-accent-400') ?> <?= e($s['to']['city']) ?></h1>
        <p class="mt-1 text-brand-100"><?= e(fmt_date($p['depart'])) ?><?= $p['return'] ? ' – ' . e(fmt_date($p['return'])) : ' · ' . e(t('One way')) ?> · <?= e(tn($people, '{n} traveler', '{n} travelers')) ?> · <?= e(t(CABIN_LABELS[$p['cabin']])) // i18n-keys: 'Economy', 'Premium Economy', 'Business', 'First' ?></p>
      <?php else: ?>
        <h1 class="text-3xl font-bold sm:text-4xl"><?= e(t('Find cheap flights')) ?></h1>
        <p class="mt-1 text-brand-100"><?= e(t("Choose your departure, destination and dates — we'll compare airlines for you.")) ?></p>
      <?php endif; ?>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-xl sm:p-6">
      <?= flight_search_form(['from' => $p['from']['code'] ?? null, 'to' => $p['to']['code'] ?? null, 'depart' => $s ? $p['depart'] : '', 'return' => $s ? (string) $p['return'] : '', 'trip' => $p['trip'], 'adults' => $p['adults'], 'children' => $p['children'], 'infants' => $p['infants'], 'cabin' => $p['cabin']]) ?>
    </div>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php if ($result): ?>
  <?= results_notice($result, 'flights') ?>
<?php endif; ?>
<?php if ($s && $offers): ?>
  <div class="grid gap-6 lg:grid-cols-[260px_1fr]" data-results <?= currency_attrs() ?>>
    <aside class="h-fit space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
      <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-900"><?= e(t('Filters')) ?></h2><button type="button" class="text-sm font-medium text-brand-700 hover:underline" data-reset><?= e(t('Reset')) ?></button></div>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Stops')) ?></legend>
        <?php foreach (['0' => t('Nonstop'), '1' => t('1 stop'), '2' => t('2+ stops')] as $v => $label): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="checkbox" value="<?= $v ?>" data-filter="stops" class="h-4 w-4 accent-brand-600"><?= e($label) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Max price')) ?> <span class="font-normal text-slate-500">· <span data-range-label="price"><?= e(price($priceMax)) ?></span></span></legend>
        <input type="range" min="<?= min(array_column($offers, 'total')) ?>" max="<?= $priceMax ?>" value="<?= $priceMax ?>" step="1" class="w-full accent-brand-600" data-filter-max="price">
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Departure time')) ?></legend>
        <?php foreach (['morning' => [t('Morning'), t('5am – 12pm')], 'afternoon' => [t('Afternoon'), t('12pm – 6pm')], 'evening' => [t('Evening'), t('6pm – 11pm')]] as $v => [$label, $hint]): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="checkbox" value="<?= $v ?>" data-filter="time" class="h-4 w-4 accent-brand-600"><?= e($label) ?> <span class="text-xs text-slate-400"><?= e($hint) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Airlines')) ?></legend>
        <?php foreach ($airlines as $code => $a): ?>
          <label class="flex cursor-pointer items-center justify-between gap-2 py-1 text-sm text-slate-700"><span class="flex items-center gap-2"><input type="checkbox" value="<?= e($code) ?>" data-filter="airline" class="h-4 w-4 accent-brand-600"><?= e($a['name']) ?></span><span class="text-xs text-slate-500"><?= e(price($a['min'])) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    </aside>

    <div class="space-y-4">
      <div class="grid grid-cols-3 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <?php foreach (['best' => t('Best'), 'cheapest' => t('Cheapest'), 'fastest' => t('Fastest')] as $key => $label): $o = $summary[$key]; ?>
          <button type="button" data-sort-by="<?= $key === 'cheapest' ? 'price' : ($key === 'fastest' ? 'duration' : 'best') ?>" aria-pressed="<?= $key === 'best' ? 'true' : 'false' ?>"
            class="border-b-4 px-3 py-3 text-left transition border-transparent hover:bg-slate-50 aria-pressed:border-brand-600 aria-pressed:bg-brand-50 sm:px-5">
            <p class="text-sm font-semibold text-slate-900"><?= e($label) ?></p>
            <p class="text-lg font-bold text-slate-900"><?= e(price($o['total'])) ?></p>
            <p class="text-xs text-slate-500"><?= e(t('{duration} total', ['duration' => fmt_duration($dur($o))])) ?></p>
          </button>
        <?php endforeach; ?>
      </div>
      <p class="text-sm text-slate-600"><?= th('Showing {visible} of {total} flights · prices for {travelers}, taxes included', ['total' => count($offers), 'travelers' => tn($people, '{n} traveler', '{n} travelers')], ['visible' => '<span class="font-semibold" data-visible-count>' . count($offers) . '</span>']) ?></p>
      <p class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600" data-empty><?= e(t('No flights match your filters. Try removing some.')) ?></p>
      <div class="space-y-4" data-list data-reveal-stagger>
      <?php foreach ($offers as $o):
          $t = $o['outbound']['depart'];
          $time = $t >= 300 && $t < 720 ? 'morning' : ($t >= 720 && $t < 1080 ? 'afternoon' : ($t >= 1080 ? 'evening' : 'night'));
          $book = url('book.php') . '?kind=flight&' . $p['query'] . '&offer=' . rawurlencode($o['id']); ?>
        <article class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md md:grid-cols-[1fr_auto]"
          data-item data-price="<?= $o['total'] ?>" data-duration="<?= $dur($o) ?>" data-best="<?= round($o['best'], 4) ?>"
          data-stops="<?= min($o['outbound']['stops'], 2) ?>" data-time="<?= $time ?>" data-airline="<?= e($o['airline']['code'] ?: $o['airline']['name']) ?>">
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <?php if ($o['airline']['logo']): ?>
                <img src="<?= e($o['airline']['logo']) ?>" alt="" class="h-9 w-9 rounded-lg object-contain">
              <?php else: ?>
                <span class="grid h-9 w-9 place-items-center rounded-lg text-xs font-bold text-white" style="background-color: <?= e($o['airline']['color']) ?>"><?= e($o['airline']['code']) ?></span>
              <?php endif; ?>
              <span class="font-medium text-slate-900"><?= e($o['airline']['name']) ?></span>
              <?php if ($o['refundable']): ?><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><?= e(t('Refundable')) ?></span><?php endif; ?>
              <?php if (!empty($o['baggage'])): ?><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($o['baggage']['checked'] ? t('Checked bag included') : ($o['baggage']['carry_on'] ? t('Carry-on only') : t('No bags included'))) ?></span><?php endif; ?>
              <?php if ($o['seats_left'] !== null && $o['seats_left'] <= 3): ?><span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600"><?= e(tn($o['seats_left'], '{n} seat left', '{n} seats left')) ?></span><?php endif; ?>
            </div>
            <?= leg_row($o['outbound'], t('Depart')) ?>
            <?= $o['inbound'] ? leg_row($o['inbound'], t('Return')) : '' ?>
            <?= admin_cost_line($o['cost'] ?? null, $o['total']) ?>
          </div>
          <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-4 md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-6 md:pt-0">
            <div class="md:text-right">
              <p class="text-2xl font-extrabold text-slate-900"><?= e(price($o['total'])) ?></p>
              <p class="text-xs text-slate-500"><?= e($people > 1 ? t('Total for {n} · ≈{price} each', ['n' => $people, 'price' => price(ceil($o['total'] / $people))]) : t('per traveler')) ?></p>
            </div>
            <a href="<?= e($book) ?>" class="rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-bold text-white hover:bg-accent-600"><?= e(t('Select')) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php elseif (!$s): ?>
  <?php if ($p['from'] && $p['to']): ?><div class="mb-6"><?= alert_box(t('Origin and destination must be different.')) ?></div><?php endif; ?>
  <h2 class="text-xl font-bold text-slate-900"><?= e(t('Popular routes')) ?></h2>
  <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($popular as [$a, $b]): $fa = airport($a); $fb = airport($b); ?>
      <a href="<?= e(url('flights.php', ['from' => $a, 'to' => $b])) ?>" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300 hover:shadow-md">
        <span class="font-medium text-slate-900"><?= e($fa['city']) ?> → <?= e($fb['city']) ?></span><span class="font-mono text-xs text-slate-500"><?= $a ?>–<?= $b ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
