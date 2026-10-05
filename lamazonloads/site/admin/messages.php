<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        db_run('DELETE FROM messages WHERE id = ?', [$id]);
    } else {
        db_run('UPDATE messages SET is_read = 1 - is_read WHERE id = ?', [$id]);
    }
    redirect('admin/messages.php');
}
$topics = ['dispatch' => 'Freight dispatching', 'routes' => 'Daily routes', 'onboarding' => 'Driver onboarding', 'shipper' => 'Has freight to move', 'other' => 'Other'];
$rows = db_all('SELECT * FROM messages ORDER BY is_read, created_at DESC LIMIT 300');

page_header('Messages');
admin_open('messages');
?>
<h1>Messages</h1>
<p class="muted">From the Contact page. Reply by email or phone.</p>
<?php if (!$rows): ?><div class="card empty">No messages yet.</div><?php endif; ?>
<?php foreach ($rows as $m): ?>
  <div class="card pad" style="margin-bottom:16px;<?= $m['is_read'] ? 'opacity:.75' : 'border-left:4px solid var(--blue)' ?>">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div><b><?= e($m['name']) ?></b> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?><br>
        <span class="tag tag-solid"><?= e($topics[$m['topic']] ?? $m['topic']) ?></span> <span class="muted"><?= e(fmt_date($m['created_at'], 'M j, Y g:i a')) ?></span></div>
      <div class="row-actions">
        <form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $m['is_read'] ? 'Mark unread' : 'Mark as handled' ?></button></form>
        <form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
      </div>
    </div>
    <p class="mt mb-0"><?= nl2br(e($m['message'])) ?></p>
  </div>
<?php endforeach; ?>
<?php dash_close(); page_footer();
