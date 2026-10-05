<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$f = ['q' => param('q'), 'status' => param('status'), 'kind' => param('kind'), 'user' => int_param('user', 0, 0, PHP_INT_MAX) ?: null];
$page = int_param('page', 1, 1, 10000);
$res = admin_search_bookings($f, $page);
$customer = $f['user'] ? find_user($f['user']) : null;
$title = 'Bookings';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
$select = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
echo admin_open('bookings');
?>
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Bookings</h1>
    <?php if ($customer): ?><p class="mt-1 text-sm text-slate-600">Showing bookings by <strong><?= e($customer['name']) ?></strong> (<?= e($customer['email']) ?>) · <a href="<?= e(url('admin/bookings.php')) ?>" class="font-semibold text-brand-700 hover:underline">show all</a></p><?php endif; ?>
  </div>
  <form class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <?php if ($f['user']): ?><input type="hidden" name="user" value="<?= (int) $f['user'] ?>"><?php endif; ?>
    <label class="flex min-w-64 flex-1 flex-col gap-1 text-xs font-medium text-slate-500">Search
      <input name="q" value="<?= e($f['q']) ?>" placeholder="Reference, name, email, phone or traveler" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"></label>
    <label class="flex flex-col gap-1 text-xs font-medium text-slate-500">Status
      <select name="status" class="<?= $select ?>"><?php foreach (['' => 'All', 'reserved' => 'Unpaid', 'paid' => 'Paid · to ticket', 'ticketed' => 'Ticketed', 'cancelled' => 'Cancelled'] as $v => $l): ?><option value="<?= $v ?>"<?= $f['status'] === $v ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label class="flex flex-col gap-1 text-xs font-medium text-slate-500">Type
      <select name="kind" class="<?= $select ?>"><?php foreach (['' => 'All', 'flight' => 'Flights', 'hotel' => 'Hotels', 'package' => 'Packages'] as $v => $l): ?><option value="<?= $v ?>"<?= $f['kind'] === $v ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Filter</button>
    <?php if ($f['q'] || $f['status'] || $f['kind']): ?><a href="<?= e(url('admin/bookings.php', ['user' => $f['user']])) ?>" class="py-2 text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a><?php endif; ?>
  </form>
  <?= bookings_table($res['bookings']) ?>
  <?= pager($page, $res['has_more'], fn($n) => url('admin/bookings.php', $f + ['page' => $n])) ?>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
