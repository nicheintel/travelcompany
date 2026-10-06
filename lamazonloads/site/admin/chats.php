<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Staff: conversations from the Chat button, and answering them. Engine: includes/chat.php.
require_admin();
chat_admin_seen();
chat_cleanup();

$filters = ['open' => 'Open', 'done' => 'Done', 'all' => 'All'];
if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['t'] ?? 0);
    $f = isset($filters[$_POST['f'] ?? '']) ? $_POST['f'] : 'open';
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'reply') {
        $err = chat_reply($id, (string) ($_POST['text'] ?? ''));
        if ($err !== '') {
            flash('error', $err);
            $_SESSION['chat_draft'] = (string) ($_POST['text'] ?? '');
        }
    } elseif ($action === 'done' || $action === 'open') {
        chat_set_status($id, $action);
        flash('info', $action === 'done' ? "Marked as done. They're asked to rate the chat, and it moves back to Open if they write again." : 'Moved back to Open.');
    } elseif ($action === 'delete') {
        chat_delete($id);
        flash('success', 'Chat deleted.');
        redirect('admin/chats.php?f=' . $f);
    }
    redirect('admin/chats.php?f=' . $f . '&t=' . $id . '#reply');
}

$f = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'open';
$tid = (int) ($_GET['t'] ?? 0);
if ($tid) { // read, before the list is drawn
    db_run('UPDATE support_threads SET admin_unread = 0, admin_read_id = (SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE thread_id = ?) WHERE id = ?', [$tid, $tid]);
}
$thread = $tid ? chat_thread($tid) : null;
$threads = chat_threads($f);
$msgs = $thread ? chat_messages($tid) : [];
$counts = [];
foreach (db_all('SELECT status, COUNT(*) n FROM support_threads GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$newOpen = (int) db_val("SELECT COUNT(*) FROM support_threads WHERE status = 'open' AND admin_unread > 0");
$rs = chat_rating_stats();
$stars = fn (int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
$draft = (string) ($_SESSION['chat_draft'] ?? '');
unset($_SESSION['chat_draft']);
$initials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
};
$when = function (int $t): string {
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return intdiv($d, 60) . ' min ago';
    if (date('Y-m-d', $t) === date('Y-m-d')) return date('g:i A', $t);
    return date('M j', $t);
};

page_header('Support chats');
admin_open('chats');
?>
<?= admin_head('Support chats', 'Live messages from the Chat button on your website. Pick a conversation to read and reply. Only staff can see them.') ?>

<div class="kpis kpis-3 cs-facts">
  <div class="card kpi"><span class="kpi-ico"><?= icon('chat') ?></span><span class="kpi-label">Open chats</span><b class="kpi-num"><?= (int) ($counts['open'] ?? 0) ?></b><small class="kpi-sub"><?= $newOpen ? $newOpen . ' with new messages' : 'Nothing new' ?></small></div>
  <div class="card kpi"><span class="kpi-ico"><?= icon('star') ?></span><span class="kpi-label">Average rating</span><b class="kpi-num"><?= $rs['avg'] !== null ? number_format($rs['avg'], 1) . ' / 5' : '–' ?></b><small class="kpi-sub"><?= $rs['n'] ? 'Last 90 days' : 'No ratings yet' ?></small></div>
  <div class="card kpi"><span class="kpi-ico"><?= icon('check') ?></span><span class="kpi-label">Rated chats</span><b class="kpi-num"><?= $rs['n'] ?></b><small class="kpi-sub">People rate a chat after it ends</small></div>
</div>

<div class="filters cs-tabs" role="tablist" aria-label="Show">
  <?php foreach ($filters as $k => $label): ?>
    <a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e(url('admin/chats.php?f=' . $k)) ?>" role="tab" aria-selected="<?= $f === $k ? 'true' : 'false' ?>"><?= e($label) ?><?php if ($k !== 'all'): ?> <span><?= (int) ($counts[$k] ?? 0) ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<div class="cs-wrap<?= $thread ? ' has-thread' : '' ?>" data-cs data-endpoint="<?= e(url('chat.php')) ?>" data-thread="<?= $thread ? $tid : 0 ?>" data-read="<?= $thread ? (int) $thread['user_read_id'] : 0 ?>" data-last="<?= $msgs ? end($msgs)['id'] : 0 ?>" data-sig="<?= (int) db_val('SELECT COALESCE(MAX(id), 0) FROM support_messages') ?>">
  <section class="card cs-list" aria-label="Chats">
    <?php if (!$threads): ?>
      <p class="cs-empty"><?= $f === 'open' ? 'No open chats. When someone writes with the Chat button, it shows up here and you get an email.' : 'Nothing here yet.' ?></p>
    <?php endif; ?>
    <?php foreach ($threads as $t): ?>
      <a class="cs-row<?= (int) $t['id'] === $tid ? ' cur' : '' ?><?= (int) $t['admin_unread'] ? ' unread' : '' ?>" href="<?= e(url('admin/chats.php?f=' . $f . '&t=' . (int) $t['id'])) ?>">
        <span class="cs-avatar"><?= e($initials((string) $t['name'])) ?></span>
        <span class="cs-main"><b><?= e($t['name']) ?></b><span><?= $t['last_from'] === 'admin' ? 'You: ' : '' ?><?= e(mb_strimwidth((string) preg_replace('/\s+/', ' ', (string) $t['last_body']), 0, 70, '…')) ?></span></span>
        <span class="cs-when"><time data-at="<?= (int) $t['updated_at'] ?>" data-short><?= e($when((int) $t['updated_at'])) ?></time><?php if ((int) $t['rating']): ?><span class="cs-stars" title="Rated <?= (int) $t['rating'] ?> out of 5"><?= $stars((int) $t['rating']) ?></span><?php endif; ?><?php if ((int) $t['admin_unread']): ?><b class="cs-count"><?= (int) $t['admin_unread'] ?></b><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </section>

  <section class="card cs-conv" aria-label="Conversation">
    <?php if (!$thread): ?>
      <p class="cs-empty">Pick a chat on the left to read it and reply.</p>
    <?php else: ?>
      <div class="cs-head">
        <a class="cs-back" href="<?= e(url('admin/chats.php?f=' . $f)) ?>">&larr; All chats</a>
        <div class="cs-who"><b><?= e($thread['name']) ?></b>
          <span><span class="badge <?= $thread['account_type'] === 'member' ? 'badge-new' : 'badge-closed' ?>"><?= $thread['account_type'] === 'member' ? 'Member' : 'Visitor' ?></span>
            <a href="mailto:<?= e($thread['email']) ?>"><?= e($thread['email']) ?></a> · started <time data-at="<?= (int) $thread['created_at'] ?>"><?= e(date('M j, g:i A', (int) $thread['created_at'])) ?></time><?php if ($thread['account_type'] === 'member'): ?> · <a href="<?= e(url('admin/driver.php?id=' . (int) $thread['account_id'])) ?>">profile</a><?php endif; ?><span class="cs-here" data-cs-here<?= time() - (int) $thread['user_seen_at'] < CHAT_AWAY_AFTER ? '' : ' hidden' ?>> · <i></i>on the website now</span></span></div>
        <div class="cs-acts">
          <form method="post" action="<?= e(url('admin/chats.php')) ?>"><?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="<?= $thread['status'] === 'done' ? 'open' : 'done' ?>"><button class="btn btn-ghost btn-sm" type="submit"<?= $thread['status'] === 'done' ? '' : " title=\"Ends the chat. They're asked to rate it.\"" ?>><?= $thread['status'] === 'done' ? 'Reopen' : 'Mark as done' ?></button></form>
          <form method="post" action="<?= e(url('admin/chats.php')) ?>" data-confirm="Delete this chat with <?= e($thread['name']) ?>? This can't be undone."><?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
        </div>
      </div>
      <?php if ((int) $thread['ended_at']): ?>
        <div class="cs-ended">
          <span>Ended by <?= $thread['ended_by'] === 'user' ? e(chat_first_name((string) $thread['name'])) : 'you' ?> · <time data-at="<?= (int) $thread['ended_at'] ?>"><?= e(date('M j, g:i A', (int) $thread['ended_at'])) ?></time></span>
          <?php if ((int) $thread['rating']): ?>
            <span class="cs-rating"><b class="cs-stars"><?= $stars((int) $thread['rating']) ?></b> <?= (int) $thread['rating'] ?> out of 5<?php if ($thread['feedback'] !== ''): ?> · “<?= e($thread['feedback']) ?>”<?php endif; ?></span>
          <?php else: ?>
            <span class="muted">Not rated yet</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="cs-msgs" data-cs-msgs>
        <?php foreach ($msgs as $m): ?>
          <div class="cm cm-<?= $m['from'] === 'admin' ? 'me' : 'them' ?>" data-msg-id="<?= $m['id'] ?>"><p><?= e($m['text']) ?></p><small data-at="<?= $m['at'] ?>"><?= e(date('M j, g:i A', $m['at'])) ?></small></div>
        <?php endforeach; ?>
      </div>
      <p class="cs-typing" data-cs-typing hidden><?= e(chat_first_name((string) $thread['name'])) ?> is typing<span class="dots"><i></i><i></i><i></i></span></p>
      <form method="post" action="<?= e(url('admin/chats.php')) ?>" class="cs-reply" id="reply">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="reply">
        <label class="sr-only" for="cs-text">Your reply</label>
        <textarea id="cs-text" name="text" rows="3" maxlength="<?= CHAT_MAX_LEN ?>" placeholder="Type your reply… (Ctrl+Enter sends)" required data-cs-text><?= e($draft) ?></textarea>
        <div class="cs-send"><span class="muted cs-note">If they've left the website, they get your reply by email.</span><button class="btn btn-primary" type="submit">Send</button></div>
      </form>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(asset('chat.js')) ?>" defer></script>
<?php dash_close(); page_footer();
