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
<?php $unread = count(array_filter($rows, fn ($m) => !$m['is_read'])); ?>
<?= admin_head('Contact messages', 'Everything sent through the Contact page. Reply by email or phone, then mark it as handled.') ?>
<?php if (!$rows): ?>
  <div class="card empty">No messages yet. When someone writes on the Contact page, it shows up here and you get an email.</div>
<?php else: ?>
  <p class="muted app-count"><b><?= count($rows) ?></b> message<?= count($rows) === 1 ? '' : 's' ?><?= $unread ? ' · <b>' . $unread . '</b> waiting for a reply' : ' · all handled' ?></p>
  <div class="msg-list">
  <?php foreach ($rows as $m): $tel = $m['phone'] ? tel_href((string) $m['phone']) : ''; ?>
    <article class="card msg<?= $m['is_read'] ? ' is-done' : ' is-new' ?>">
      <header class="msg-head">
        <span class="ov-av" aria-hidden="true"><?= e(strtoupper(mb_substr((string) $m['name'], 0, 1))) ?></span>
        <div class="msg-who">
          <b><?= e($m['name']) ?></b>
          <small><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · <a href="' . e($tel) . '">' . e($m['phone']) . '</a>' : '' ?></small>
        </div>
        <div class="msg-meta">
          <span class="tag tag-solid"><?= e($topics[$m['topic']] ?? $m['topic']) ?></span>
          <time><?= e(fmt_date($m['created_at'], 'M j, Y · g:i a')) ?></time>
        </div>
      </header>
      <p class="msg-body"><?= nl2br(e($m['message'])) ?></p>
      <footer class="msg-actions">
        <a class="btn btn-primary btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: your message to LamazonLoads')) ?>"><?= icon('mail') ?> Reply by email</a>
        <?php if ($tel): ?><a class="btn btn-ghost btn-sm" href="<?= e($tel) ?>"><?= icon('phone') ?> Call</a><?php endif; ?>
        <form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('check') ?> <?= $m['is_read'] ? 'Mark as new' : 'Mark as handled' ?></button></form>
        <form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form msg-del" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm btn-icon" type="submit" aria-label="Delete message" title="Delete"><?= icon('trash') ?></button></form>
      </footer>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php dash_close(); page_footer();
