<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
if (is_post()) {
    verify_csrf();
    $target = find_user((int) post('user_id'));
    if (!$target) flash('User not found.', 'error');
    elseif ($target['id'] === $me['id']) flash("You can't change your own access.", 'error');
    elseif ($target['admin_by_config']) flash('This admin is set in admin_emails and can only be changed there.', 'error');
    else {
        $role = post('role') === 'admin' ? 'admin' : 'customer';
        db_run('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]);
        flash($role === 'admin' ? "{$target['name']} is now an admin." : "{$target['name']} is no longer an admin.");
    }
    redirect(current_path_with_query());
}
$q = param('q');
$page = int_param('page', 1, 1, 10000);
$res = search_users($q, $page);
$title = 'Users';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('users');
?>
<div class="space-y-6">
  <h1 class="text-2xl font-bold text-slate-900">Users</h1>
  <form class="flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <input name="q" value="<?= e($q) ?>" placeholder="Search by name or email" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Search</button>
  </form>
  <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full min-w-[720px] text-left text-sm">
      <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Name</th><th class="px-4 py-3 font-semibold">Joined</th><th class="px-4 py-3 font-semibold">Bookings</th><th class="px-4 py-3 font-semibold">Role</th><th class="px-4 py-3"></th></tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($res['users'] as $u): ?>
          <tr>
            <td class="px-4 py-3"><p class="font-medium text-slate-900"><?= e($u['name']) ?> <?= $u['id'] === $me['id'] ? '<span class="text-xs text-slate-400">(you)</span>' : '' ?></p><p class="text-xs text-slate-500"><?= e($u['email']) ?></p></td>
            <td class="px-4 py-3 text-slate-600"><?= local_time($u['created_at'], true) ?></td>
            <td class="px-4 py-3"><?= $u['booking_count'] ? '<a href="' . e(url('admin/bookings.php', ['user' => $u['id']])) . '" class="font-semibold text-brand-700 hover:underline">' . plural($u['booking_count'], 'booking') . '</a>' : '<span class="text-slate-400">None</span>' ?></td>
            <td class="px-4 py-3"><?= $u['role'] === 'admin' ? '<span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">Admin' . ($u['admin_by_config'] ? ' · config' : '') . '</span>' : '<span class="text-slate-500">Customer</span>' ?><?= $u['verified'] ? '' : ' <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Email not confirmed</span>' ?></td>
            <td class="px-4 py-3 text-right">
              <?php if ($u['id'] !== $me['id'] && !$u['admin_by_config']): $isAdmin = $u['role'] === 'admin'; ?>
                <form method="post" data-confirm="<?= e($isAdmin ? "Remove admin access for {$u['name']}?" : "Give {$u['name']} admin access? They'll see all bookings and customers.") ?>">
                  <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="role" value="<?= $isAdmin ? 'customer' : 'admin' ?>">
                  <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 <?= $isAdmin ? 'text-red-600 ring-red-200 hover:bg-red-50' : 'text-brand-700 ring-brand-200 hover:bg-brand-50' ?>"><?= $isAdmin ? 'Remove admin' : 'Make admin' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$res['users']): ?><tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No users found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= pager($page, $res['has_more'], fn($n) => url('admin/users.php', ['q' => $q, 'page' => $n])) ?>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
