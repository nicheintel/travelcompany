<?php
require __DIR__ . '/includes/bootstrap.php';

$p = parse_hotel_params($_GET);
$s = $p['search'];
$result = $s ? search_hotels($s) : null;
$hotels = $result['items'] ?? [];
$popular = ['CDG', 'NRT', 'DPS', 'BKK', 'LHR', 'CUN', 'DXB', 'CEB'];
$description = 'Find hotels worldwide at live rates and book them with help from a real travel assistant.';
$title = $s ? "Hotels in {$s['city']['city']}" : 'Hotels';
require __DIR__ . '/includes/header.php';
?>
<section class="bg-gradient-to-br from-teal-700 via-brand-800 to-brand-900 pb-8 pt-8">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <div class="mb-5 text-white">
      <?php if ($s): ?>
        <h1 class="text-2xl font-bold sm:text-3xl">Hotels in <?= e($s['city']['city']) ?></h1>
        <p class="mt-1 text-brand-100"><?= e(fmt_date($s['checkin'])) ?> – <?= e(fmt_date($s['checkout'])) ?> · <?= plural($s['nights'], 'night') ?> · <?= plural($s['adults'] + $s['children'], 'guest') ?> · <?= plural($s['rooms'], 'room') ?></p>
      <?php else: ?>
        <h1 class="text-3xl font-bold sm:text-4xl">Find your perfect stay</h1>
        <p class="mt-1 text-brand-100">Hand-picked hotels, resorts and apartments — many with free cancellation.</p>
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
  <div class="grid gap-6 lg:grid-cols-[260px_1fr]" data-results>
    <aside class="h-fit space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
      <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-900">Filters</h2><button type="button" class="text-sm font-medium text-brand-700 hover:underline" data-reset>Reset</button></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" data-filter-flag="free" class="h-4 w-4 accent-brand-600"> Free cancellation</label>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700">Price per night <span class="font-normal text-slate-500">· up to <span data-range-label="price"><?= money(max($nightly)) ?></span></span></legend>
        <input type="range" min="<?= min($nightly) ?>" max="<?= max($nightly) ?>" value="<?= max($nightly) ?>" class="w-full accent-brand-600" data-filter-max="price">
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700">Star rating</legend>
        <div class="flex flex-wrap gap-2">
          <?php foreach ([5, 4, 3, 2] as $st): ?>
            <label class="flex cursor-pointer items-center gap-1 rounded-lg px-3 py-1.5 text-sm ring-1 text-slate-700 ring-slate-200 hover:ring-slate-300 has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:ring-brand-600">
              <input type="checkbox" value="<?= $st ?>" data-filter="stars" class="sr-only"><?= $st ?> <?= star_icons(1, 12) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700">Guest rating</legend>
        <?php foreach (['0' => 'Any', '9' => 'Exceptional 9+', '8' => 'Very good 8+', '7' => 'Good 7+'] as $v => $label): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="radio" name="minRating" value="<?= $v ?>"<?= $v === '0' ? ' checked' : '' ?> data-filter-min="rating" class="h-4 w-4 accent-brand-600"><?= $label ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-slate-700">Amenities</legend>
        <?php foreach (['breakfast', 'pool', 'parking', 'shuttle', 'spa', 'gym'] as $am): ?>
          <label class="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700"><input type="checkbox" value="<?= $am ?>" data-filter-has="amenities" class="h-4 w-4 accent-brand-600"><?= AMENITY_LABELS[$am] ?></label>
        <?php endforeach; ?>
      </fieldset>
    </aside>
    <div class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600"><span class="font-semibold" data-visible-count><?= count($hotels) ?></span> of <?= count($hotels) ?> properties · prices for <?= plural($s['nights'], 'night') ?>, <?= plural($s['rooms'], 'room') ?></p>
        <label class="flex items-center gap-2 text-sm text-slate-700">Sort by
          <select class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium" data-sort-select>
            <option value="score">Recommended</option><option value="price">Lowest price</option><option value="-rating">Guest rating</option><option value="-stars">Star rating</option>
          </select>
        </label>
      </div>
      <p class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600" data-empty>No hotels match your filters. Try removing some.</p>
      <div class="space-y-4" data-list>
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
            <?php if ($h['original']): ?><span class="absolute left-3 top-3 z-10 rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold">Deal −<?= round((1 - $h['nightly'] / $h['original']) * 100) ?>%</span><?php endif; ?>
            <span class="relative text-xs font-medium"><?= e($h['room']) ?></span>
          </div>
          <div class="flex flex-col gap-4 p-5 md:flex-row md:justify-between">
            <div class="min-w-0 space-y-2">
              <?php if ($h['stars']): ?><div class="flex text-amber-400" aria-label="<?= $h['stars'] ?> stars"><?= star_icons($h['stars'], 14) ?></div><?php endif; ?>
              <h3 class="text-lg font-semibold text-slate-900"><?= e($h['name']) ?></h3>
              <p class="flex items-center gap-1 text-sm text-slate-500"><?= icon('pin', 14, 'shrink-0') ?><span class="truncate"><?= e($h['area']) ?><?= $h['distance'] !== null ? ' · ' . $h['distance'] . ' km from center' : '' ?></span></p>
              <?php if ($h['rating'] !== null): ?>
                <div class="flex items-center gap-2">
                  <span class="rounded-lg bg-brand-700 px-2 py-1 text-sm font-bold text-white"><?= number_format($h['rating'], 1) ?></span>
                  <span class="text-sm font-semibold text-slate-800"><?= rating_label($h['rating']) ?></span>
                  <?php if ($h['reviews']): ?><span class="text-xs text-slate-500"><?= number_format($h['reviews']) ?> reviews</span><?php endif; ?>
                </div>
              <?php endif; ?>
              <ul class="flex flex-wrap gap-1.5 text-xs">
                <?php foreach (array_slice($h['amenities'], 0, 4) as $am): ?><li class="rounded-md bg-slate-100 px-2 py-1 text-slate-700"><?= AMENITY_LABELS[$am] ?></li><?php endforeach; ?>
              </ul>
              <?php if ($h['free_cancel']): ?><p class="flex items-center gap-1 text-sm font-medium text-emerald-700"><?= icon('check', 14) ?> Free cancellation</p><?php endif; ?>
              <?= admin_cost_line($h['cost'] ?? null, $total) ?>
            </div>
            <div class="flex shrink-0 flex-row items-end justify-between gap-3 md:flex-col md:text-right">
              <div>
                <?php if ($h['original']): ?><p class="text-sm text-slate-400 line-through"><?= money($h['original']) ?></p><?php endif; ?>
                <p class="text-2xl font-extrabold text-slate-900"><?= money($h['nightly']) ?></p>
                <p class="text-xs text-slate-500">per night</p>
                <p class="mt-1 text-xs text-slate-600"><?= money($total) ?> total <?= $h['stay_total'] ? 'incl. taxes' : '+ taxes' ?></p>
              </div>
              <a href="<?= e($book) ?>" class="rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-bold text-white hover:bg-accent-600">Reserve</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php elseif (!$s): ?>
  <h2 class="text-xl font-bold text-slate-900">Popular cities</h2>
  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <?php foreach ($popular as $code): $c = airport($code); ?>
      <a href="<?= e(url('hotels.php', ['to' => $code])) ?>" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300 hover:shadow-md">
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-600"><?= icon('bed', 18) ?></span>
        <span><span class="block font-medium text-slate-900"><?= e($c['city']) ?></span><span class="block text-xs text-slate-500"><?= e($c['country']) ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
