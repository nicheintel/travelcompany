<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$stats = admin_stats();
$attention = admin_needs_attention();
$toTicket = admin_needs_ticket();
$recent = admin_search_bookings([], 1, 10)['bookings'];
$title = 'Admin';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
$stat = fn(string $label, string $value, string $href, string $hint = '') => '<a href="' . e($href) . '" class="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">'
    . '<p class="text-sm font-medium text-slate-500">' . e($label) . '</p><p class="mt-1 text-3xl font-extrabold text-slate-900">' . e($value) . '</p>'
    . ($hint ? '<p class="mt-1 text-xs text-slate-500">' . e($hint) . '</p>' : '') . '</a>';
echo admin_open('overview');
?>
<div class="space-y-10">
  <div><h1 class="text-2xl font-bold text-slate-900">Hello, <?= e(explode(' ', $admin['name'])[0]) ?></h1><p class="text-slate-600">Here's what needs your attention today.</p></div>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?= $stat('Awaiting payment', (string) $stats['awaiting'], url('admin/bookings.php', ['status' => 'reserved']), money($stats['awaiting_total']) . ' in upcoming unpaid trips') ?>
    <?= $stat('Bookings (7 days)', (string) $stats['bookings_7d'], url('admin/bookings.php')) ?>
    <?= $stat('Revenue (30 days)', money($stats['revenue_30d']), url('admin/bookings.php', ['status' => 'ticketed']), 'Paid bookings') ?>
    <?= $stat('New members (7 days)', (string) $stats['users_7d'], url('admin/users.php')) ?>
  </div>
  <?php if ($gcashToCheck = admin_gcash_to_check()): ?>
    <section class="rounded-2xl border-2 border-sky-300 bg-sky-50 p-5">
      <div class="mb-3"><h2 class="text-lg font-bold text-sky-900">GCash payments to check (<?= count($gcashToCheck) ?>)</h2>
        <p class="text-sm text-sky-800">These customers say they sent a GCash payment. Open each booking, find the reference in your GCash app, then confirm.</p></div>
      <?= bookings_table($gcashToCheck, '') ?>
    </section>
  <?php endif; ?>
  <?php if ($bagsToPrice = admin_bags_to_price()): ?>
    <section class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5">
      <div class="mb-3"><h2 class="text-lg font-bold text-amber-900">Checked bags to price (<?= count($bagsToPrice) ?>)</h2>
        <p class="text-sm text-amber-800">These customers asked for a checked bag and can't pay until you add the airline's bag price. Open each booking and enter the price.</p></div>
      <?= bookings_table($bagsToPrice, '') ?>
    </section>
  <?php endif; ?>
  <?php if ($toTicket): ?>
    <section class="rounded-2xl border-2 border-red-300 bg-red-50 p-5">
      <div class="mb-3"><h2 class="text-lg font-bold text-red-800">Paid — issue the tickets now (<?= count($toTicket) ?>)</h2>
        <p class="text-sm text-red-700">These customers have paid. Buy the flight or room from the supplier, then open the booking and click <strong>Mark as ticketed</strong> — the customer gets their confirmation by email.</p></div>
      <?= bookings_table($toTicket, '') ?>
    </section>
  <?php endif; ?>
  <section>
    <div class="mb-3"><h2 class="text-lg font-semibold text-slate-900">Needs a call (<?= count($attention) ?>)</h2>
      <p class="text-sm text-slate-500">Unpaid trips departing within 14 days, or reserved more than 24 hours ago — soonest departure first.</p></div>
    <?= bookings_table($attention, 'All caught up — no unpaid trips need a call right now. 🎉') ?>
  </section>
  <section>
    <div class="mb-3 flex items-end justify-between"><h2 class="text-lg font-semibold text-slate-900">Latest bookings</h2><a href="<?= e(url('admin/bookings.php')) ?>" class="text-sm font-semibold text-brand-700 hover:underline">All bookings →</a></div>
    <?= bookings_table($recent, 'No bookings yet.') ?>
  </section>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
