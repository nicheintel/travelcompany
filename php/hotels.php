<?php
require __DIR__ . '/includes/bootstrap.php';

$p = parse_hotel_params($_GET);
$s = $p['search'];
$result = $s ? search_hotels($s) : null;
$hotels = $result['items'] ?? [];
$popular = ['CDG', 'NRT', 'DPS', 'BKK', 'LHR', 'CUN', 'DXB', 'CEB'];
$description = 'Find hotels worldwide at live rates and book them with help from a real travel assistant.';
$title = $s ? t('Hotels in {city}', ['city' => $s['city']['city']]) : 'Hotels';
require __DIR__ . '/includes/header.php';
?>
<section class="relative bg-gradient-to-br from-teal-700 via-brand-800 to-brand-900 pb-10 pt-10">
  <?= photo_backdrop('hotels', 'bg-gradient-to-r from-brand-950/85 via-brand-950/55 to-teal-900/20') ?>
  <div class="relative z-20 mx-auto max-w-7xl px-4 sm:px-6">
    <div class="mb-5 text-white">
      <?php if ($s): ?>
        <h1 class="text-2xl font-bold sm:text-3xl"><?= e(t('Hotels in {city}', ['city' => $s['city']['city']])) ?></h1>
        <p class="mt-1 text-brand-100"><?= e(fmt_date($s['checkin'])) ?> – <?= e(fmt_date($s['checkout'])) ?> · <?= e(tn($s['nights'], '{n} night', '{n} nights')) ?> · <?= e(tn($s['adults'] + $s['children'], '{n} guest', '{n} guests')) ?> · <?= e(tn($s['rooms'], '{n} room', '{n} rooms')) ?></p>
      <?php else: ?>
        <h1 class="text-3xl font-bold sm:text-4xl"><?= e(t('Find your perfect stay')) ?></h1>
        <p class="mt-1 text-brand-100"><?= e(t('Hand-picked hotels, resorts and apartments — many with free cancellation.')) ?></p>
      <?php endif; ?>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-xl sm:p-6">
      <?= hotel_search_form(['to' => $p['city']['code'] ?? null, 'checkin' => $s ? $s['checkin'] : '', 'checkout' => $s ? $s['checkout'] : '', 'adults' => $p['adults'], 'children' => $p['children'], 'rooms' => $p['rooms']]) ?>
    </div>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php if ($result): ?><?= results_notice($result, 'hotels') ?><?php endif; ?>
<?php if ($s && $hotels):
    $nightly = array_column($hotels, 'nightly'); ?>
  <div class="grid gap-6 lg:grid-cols-[260px_1fr]" data-results <?= currency_attrs() ?>>
    <aside class="h-fit space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
      <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-900"><?= e(t('Filters')) ?></h2><button type="button" class="text-sm font-medium text-brand-700 hover:underline" data-reset><?= e(t('Reset')) ?></button></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" data-filter-flag="free" class="h-4 w-4 accent-brand-600"> <?= e(t('Free cancellation')) ?></label>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Price per night')) ?> <span class="font-normal text-slate-500">· <?= th('up to {price}', [], ['price' => '<span data-range-label="price">' . e(price(max($nightly))) . '</span>']) ?></span></legend>
        <input type="range" min="<?= min($nightly) ?>" max="<?= max($nightly) ?>" value="<?= max($nightly) ?>" class="w-full accent-brand-600" data-filter-max="price">
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Star rating')) ?></legend>
        <div class="flex flex-wrap gap-2">
          <?php foreach ([5, 4, 3, 2] as $st): ?>
            <label class="flex cursor-pointer items-center gap-1 rounded-lg px-3 py-1.5 text-sm ring-1 text-slate-700 ring-slate-200 hover:ring-slate-300 has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:ring-brand-600">
              <input type="checkbox" value="<?= $st ?>" data-filter="stars" class="sr-only"><?= $st ?> <?= star_icons(1, 12) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Guest rating')) ?></legend>
        <?php foreach (['0' => t('Any'), '9' => t('Exceptional 9+'), '8' => t('Very good 8+'), '7' => t('Good 7+')] as $v => $label): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="radio" name="minRating" value="<?= $v ?>"<?= $v === '0' ? ' checked' : '' ?> data-filter-min="rating" class="h-4 w-4 accent-brand-600"><?= e($label) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700"><?= e(t('Amenities')) ?></legend>
        <?php // i18n-keys: 'Free Wi-Fi', 'Pool', 'Breakfast included', 'Fitness center', 'Spa', 'Free parking', 'Airport shuttle'
        foreach (['breakfast', 'pool', 'parking', 'shuttle', 'spa', 'gym'] as $am): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="checkbox" value="<?= $am ?>" data-filter-has="amenities" class="h-4 w-4 accent-brand-600"><?= e(t(AMENITY_LABELS[$am])) ?></label>
        <?php endforeach; ?>
      </fieldset>
    </aside>
    <div class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600"><?= th('{visible} of {total} properties · prices for {nights}, {rooms}', ['total' => count($hotels), 'nights' => tn($s['nights'], '{n} night', '{n} nights'), 'rooms' => tn($s['rooms'], '{n} room', '{n} rooms')], ['visible' => '<span class="font-semibold" data-visible-count>' . count($hotels) . '</span>']) ?></p>
        <label class="flex items-center gap-2 text-sm text-slate-700"><?= e(t('Sort by')) ?>
          <select class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium" data-sort-select>
            <option value="score"><?= e(t('Recommended')) ?></option><option value="price"><?= e(t('Lowest price')) ?></option><option value="-rating"><?= e(t('Guest rating')) ?></option><option value="-stars"><?= e(t('Star rating')) ?></option>
          </select>
        </label>
      </div>
      <p class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600" data-empty><?= e(t('No hotels match your filters. Try removing some.')) ?></p>
      <div class="space-y-4" data-list data-reveal-stagger>
      <?php foreach ($hotels as $h):
          $total = $h['stay_total'] ?? $h['nightly'] * $s['nights'] * $s['rooms'];
          $score = ($h['rating'] ?? 7) * 10 + $h['stars'] * 3 - $h['nightly'] / 20 + ($h['free_cancel'] ? 5 : 0);
          $book = url('book.php') . '?kind=hotel&' . $p['query'] . '&hotel=' . rawurlencode($h['id']); ?>
        <article class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md sm:grid-cols-[220px_1fr]"
          data-item data-price="<?= $h['nightly'] ?>" data-stars="<?= $h['stars'] ?>" data-rating="<?= $h['rating'] ?? 0 ?>" data-score="<?= -$score ?>"
          data-amenities="<?= e(implode(' ', $h['amenities'])) ?>" data-free="<?= $h['free_cancel'] ? '1' : '0' ?>">
          <div class="relative flex min-h-40 items-end overflow-hidden bg-gradient-to-br <?= $h['gradient'] ?> p-4 text-white">
            <?php if ($h['photo']): ?>
              <img src="<?= e($h['photo']) ?>" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover"><span class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></span>
            <?php else: ?>
              <?= icon('bed', 48, 'absolute right-4 top-4 text-white/30') ?>
            <?php endif; ?>
            <?php if ($h['original']): ?><span class="absolute left-3 top-3 z-10 rounded-full bg-accent-700 px-2.5 py-1 text-xs font-bold text-white"><?= e(t('Deal −{percent}%', ['percent' => round((1 - $h['nightly'] / $h['original']) * 100)])) ?></span><?php endif; ?>
            <span class="relative text-xs font-medium"><?= e($h['room']) ?></span>
          </div>
          <div class="flex flex-col gap-4 p-5 md:flex-row md:justify-between">
            <div class="min-w-0 space-y-2">
              <?php if ($h['stars']): ?><div class="flex text-amber-400" aria-label="<?= e(tn((int) $h['stars'], '{n} star', '{n} stars')) ?>"><?= star_icons($h['stars'], 14) ?></div><?php endif; ?>
              <h3 class="text-lg font-semibold text-slate-900"><?= e($h['name']) ?></h3>
              <p class="flex items-center gap-1 text-sm text-slate-500"><?= icon('pin', 14, 'shrink-0') ?><span class="truncate"><?= e($h['area']) ?><?= $h['distance'] !== null ? ' · ' . e(t('{km} km from center', ['km' => $h['distance']])) : '' ?></span></p>
              <?php if ($h['rating'] !== null): ?>
                <div class="flex items-center gap-2">
                  <span class="rounded-lg bg-brand-700 px-2 py-1 text-sm font-bold text-white"><?= number_format($h['rating'], 1) ?></span>
                  <span class="text-sm font-semibold text-slate-800"><?= e(t(rating_label($h['rating']))) // i18n-keys: 'Exceptional', 'Excellent', 'Very good', 'Good', 'Pleasant' ?></span>
                  <?php if ($h['reviews']): ?><span class="text-xs text-slate-500"><?= e(t('{n} reviews', ['n' => number_format($h['reviews'])])) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
              <ul class="flex flex-wrap gap-1.5 text-xs">
                <?php foreach (array_slice($h['amenities'], 0, 4) as $am): ?><li class="rounded-md bg-slate-100 px-2 py-1 text-slate-700"><?= e(t(AMENITY_LABELS[$am])) // i18n-keys: 'Free Wi-Fi', 'Pool', 'Breakfast included', 'Fitness center', 'Spa', 'Free parking', 'Airport shuttle' ?></li><?php endforeach; ?>
              </ul>
              <?php if ($h['free_cancel']): ?><p class="flex items-center gap-1 text-sm font-medium text-emerald-700"><?= icon('check', 14) ?> <?= e(t('Free cancellation')) ?></p><?php endif; ?>
              <?= admin_cost_line($h['cost'] ?? null, $total) ?>
            </div>
            <div class="flex shrink-0 flex-row items-end justify-between gap-3 md:flex-col md:text-right">
              <div>
                <?php if ($h['original']): ?><p class="text-sm text-slate-500 line-through"><?= e(price($h['original'])) ?></p><?php endif; ?>
                <p class="text-2xl font-extrabold text-slate-900"><?= e(price($h['nightly'])) ?></p>
                <p class="text-xs text-slate-500"><?= e(t('per night')) ?></p>
                <p class="mt-1 text-xs text-slate-600"><?= e($h['stay_total'] ? t('{price} total incl. taxes', ['price' => price($total)]) : t('{price} total + taxes', ['price' => price($total)])) ?></p>
                <?php if ($due = array_sum(array_column($h['at_hotel'] ?? [], 'amount'))): ?><p class="text-xs font-semibold text-amber-700"><?= e(t('+ {price} due at the hotel', ['price' => price($due)])) ?></p><?php endif; ?>
              </div>
              <a href="<?= e($book) ?>" class="rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-bold text-white hover:bg-accent-600"><?= e(t('Reserve')) ?></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php elseif (!$s): ?>
  <h2 class="text-xl font-bold text-slate-900"><?= e(t('Popular cities')) ?></h2>
  <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4" data-reveal-stagger>
    <?php foreach ($popular as $code): $c = airport($code); $ph = stock_photo($code); ?>
      <a href="<?= e(url('hotels.php', ['to' => $code])) ?>" class="group relative flex aspect-[4/3] flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br from-cyan-500 to-brand-700 p-4 text-white shadow-md transition hover:-translate-y-1 hover:shadow-xl">
        <?php if ($ph): ?><picture><source type="image/avif" srcset="<?= e($ph['avif']) ?>"><img src="<?= e($ph['webp']) ?>" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"></picture>
        <?php else: ?><?= icon('bed', 90, 'absolute -right-3 -top-3 text-white/15') ?><?php endif; ?>
        <span class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></span>
        <span class="relative block text-lg font-bold leading-tight"><?= e($c['city']) ?></span>
        <span class="relative block text-xs text-white/85"><?= e($c['country']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
