<?php
require __DIR__ . '/includes/bootstrap.php';

$to = airport(param('to'));
$all = active_packages();
$shown = $to ? array_values(array_filter($all, fn($p) => $p['to_code'] === $to['code'])) : $all;
$description = 'Flight + Hotel promo packages put together by FareFinders travel assistants — one booking, one price per person.';
$title = 'Promo packages — Flight + Hotel';
require __DIR__ . '/includes/header.php';
?>
<section class="relative bg-gradient-to-br from-accent-600 via-rose-500 to-brand-700">
  <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true"><svg class="absolute inset-0 h-full w-full opacity-15" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><circle cx="1050" cy="90" r="120" fill="white"/><path d="M0 420 Q300 340 600 400 T1200 380 V500 H0Z" fill="white"/></svg></div>
  <div class="relative mx-auto max-w-7xl px-4 pb-14 pt-12 sm:px-6 sm:pt-16">
    <div class="max-w-2xl text-white">
      <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-semibold ring-1 ring-white/25"><?= e(t('Promo packages')) ?></p>
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl"><?= e(t('Flight + Hotel.')) ?><br><span class="text-yellow-200"><?= e(t('One booking, one price.')) ?></span></h1>
      <p class="mt-4 text-lg text-white/90"><?= e(t('Ready-made trips put together by our travel assistants — flights and hotel in one booking, at one price per person.')) ?></p>
    </div>
    <div class="mt-8 rounded-2xl bg-white p-4 shadow-2xl sm:p-6"><?= package_search_form(['to' => $to['code'] ?? null]) ?></div>
  </div>
</section>

<?php if ($all || is_admin()): ?>
<section id="results" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-12 sm:px-6">
  <?php if ($all): ?>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl"><?= e($to ? t('Packages to {city}', ['city' => $to['city']]) : t('All promo packages')) ?></h2>
        <p class="mt-1 text-slate-600"><?= e(t('Prices are per person, including taxes. Pick your departure date on the next page.')) ?></p>
      </div>
      <?php if ($to): ?><a href="<?= e(url('packages.php')) ?>" class="text-sm font-semibold text-brand-700 hover:underline"><?= e(t('See all packages →')) ?></a><?php endif; ?>
    </div>
    <?php if ($shown): ?>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-reveal-stagger><?php foreach ($shown as $p) echo package_card($p); ?></div>
    <?php else: ?>
      <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600"><?= e(t('No promo to {city} right now.', ['city' => $to['city']])) ?></p>
    <?php endif; ?>
  <?php elseif (is_admin()): ?>
    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">Admin: add your first package on <a class="font-semibold underline" href="<?= e(url('admin/packages.php')) ?>">Admin → Packages</a> — it will appear here and on the homepage.</p>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
