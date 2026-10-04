<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_user();
$bookings = user_bookings($user['id']);
$upcoming = array_values(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled' && ($b['quote']['end_date'] ?? $b['start_date']) >= today()));
$other = array_reverse(array_values(array_filter($bookings, fn($b) => !in_array($b, $upcoming, true))));
$first = explode(' ', $user['name'])[0];
$since = (new DateTimeImmutable($user['created_at']))->format('F Y');
$title = 'My account';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
  <div class="flex flex-col gap-4 rounded-3xl bg-gradient-to-br from-brand-800 to-brand-600 p-8 text-white sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-brand-100">My account</p>
      <h1 class="mt-1 text-3xl font-bold">Hi, <?= e($first) ?>! 👋</h1>
      <p class="mt-1 text-brand-100">Where are you heading next?</p>
    </div>
    <span class="rounded-full bg-white/15 px-4 py-1.5 text-sm font-semibold ring-1 ring-white/25">Member since <?= e($since) ?></span>
  </div>
  <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
      <h2 class="text-lg font-semibold text-slate-900">My trips</h2>
      <?php if (!$bookings): ?>
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center">
          <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><?= icon('plane', 28) ?></span>
          <p class="mt-4 font-semibold text-slate-900">No trips yet</p>
          <p class="mt-1 max-w-sm text-sm text-slate-600">When you book a flight, hotel or package it will show up here.</p>
          <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="<?= e(url('flights.php')) ?>" class="flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700"><?= icon('plane', 16) ?> Find flights</a>
            <a href="<?= e(url('packages.php')) ?>" class="flex items-center gap-2 rounded-xl bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-600"><?= icon('package', 16) ?> Browse packages</a>
            <a href="<?= e(url('hotels.php')) ?>" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50"><?= icon('bed', 16) ?> Hotels</a>
          </div>
        </div>
      <?php else: ?>
        <div class="mt-4 space-y-6">
          <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Upcoming (<?= count($upcoming) ?>)</h3>
            <?php if ($upcoming): ?>
              <div class="space-y-3"><?php foreach ($upcoming as $b) echo trip_card($b); ?></div>
            <?php else: ?>
              <p class="text-sm text-slate-500">No upcoming trips. <a href="<?= e(url('packages.php')) ?>" class="font-semibold text-brand-700 hover:underline">Find your next getaway →</a></p>
            <?php endif; ?>
          </div>
          <?php if ($other): ?>
            <div>
              <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Past &amp; cancelled</h3>
              <div class="space-y-3"><?php foreach ($other as $b) echo trip_card($b); ?></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-6">
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-brand-700"><?= icon('user', 22) ?></span>
        <div class="min-w-0"><p class="truncate font-semibold text-slate-900"><?= e($user['name']) ?></p><p class="truncate text-sm text-slate-500"><?= e($user['email']) ?></p></div>
      </div>
      <dl class="mt-6 space-y-3 border-t border-slate-100 pt-4 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Membership</dt><dd class="font-medium text-slate-900">Free member</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Member since</dt><dd class="font-medium text-slate-900"><?= e($since) ?></dd></div>
      </dl>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= e(url('admin/')) ?>" class="mt-6 block w-full rounded-xl bg-brand-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-700">Admin dashboard</a>
      <?php endif; ?>
      <a href="<?= e(url('settings.php')) ?>" class="mt-3 block w-full rounded-xl py-2.5 text-center text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">Account settings</a>
      <form method="post" action="<?= e(url('signout.php')) ?>" class="mt-3"><?= csrf_field() ?>
        <button type="submit" class="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Sign out</button>
      </form>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
