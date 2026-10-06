<?php
/* Admin → Support chats: conversations from the Chat button, and answering them. Engine: includes/chat.php. */
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
chat_admin_seen();
chat_cleanup();

if (is_post()) {
    verify_csrf();
    $id = (int) post('t');
    $f = in_array(post('f'), ['open', 'done', 'all'], true) ? post('f') : 'open';
    $action = post('action');
    if ($action === 'reply') {
        $err = chat_reply($id, (string) ($_POST['text'] ?? ''));
        if ($err !== '') {
            flash($err, 'error');
            $_SESSION['chat_draft'] = (string) ($_POST['text'] ?? '');
        }
    } elseif ($action === 'done' || $action === 'open') {
        chat_set_status($id, $action);
        flash($action === 'done' ? "Marked as done. They're asked to rate the chat, and it moves back to Open if they write again." : 'Moved back to Open.');
    } elseif ($action === 'delete') {
        chat_delete($id);
        flash('Chat deleted.');
        redirect(url('admin/chats.php', ['f' => $f]));
    }
    redirect(url('admin/chats.php', ['f' => $f, 't' => $id]) . '#reply');
}

$f = in_array($_GET['f'] ?? '', ['open', 'done', 'all'], true) ? $_GET['f'] : 'open';
$tid = (int) ($_GET['t'] ?? 0);
if ($tid) chat_mark_admin_read($tid);   // read, before the list is drawn
$thread = $tid ? chat_thread($tid) : null;
if ($thread && !isset($_GET['f'])) $f = $thread['status'] === 'done' ? 'done' : 'open';
$threads = chat_threads($f);
$msgs = $thread ? chat_messages($tid) : [];
$counts = array_column(db_all('SELECT status, COUNT(*) AS n FROM support_threads GROUP BY status'), 'n', 'status');
$withNew = (int) (db_one("SELECT COUNT(*) AS n FROM support_threads WHERE status = 'open' AND admin_unread > 0")['n'] ?? 0);
$rs = chat_rating_stats();
$sig = (int) (db_one('SELECT COALESCE(MAX(id), 0) AS m FROM support_messages')['m'] ?? 0);
$stars = fn(int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
$chatInitials = fn(string $name): string => mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [$name], 0, 2))));
$draft = $_SESSION['chat_draft'] ?? '';
unset($_SESSION['chat_draft']);
// Their recent bookings (signed-in customers), to answer questions about a trip quickly
$bookings = $thread && $thread['user_id'] ? db_all('SELECT reference, status FROM bookings WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$thread['user_id']]) : [];

$title = 'Support chats';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('chats');
?>
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Support chats</h1>
    <p class="text-slate-600">Messages from the Chat button on your website. Only admins see them. Customers see "Online now" while this page is open.</p>
  </div>
  <div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-medium text-slate-500">Open chats</p><p class="mt-1 text-3xl font-bold text-slate-900"><?= (int) ($counts['open'] ?? 0) ?></p><p class="text-xs text-slate-500"><?= $withNew ? $withNew . ' with new messages' : 'nothing new' ?></p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-medium text-slate-500">Average rating</p><p class="mt-1 text-3xl font-bold text-slate-900"><?= $rs['avg'] !== null ? '<span class="text-amber-500">★</span> ' . number_format($rs['avg'], 1) : '–' ?></p><p class="text-xs text-slate-500"><?= $rs['n'] ? 'out of 5, last 90 days' : 'no ratings yet' ?></p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-medium text-slate-500">Rated chats</p><p class="mt-1 text-3xl font-bold text-slate-900"><?= $rs['n'] ?></p><p class="text-xs text-slate-500">people rate a chat after it ends</p></div>
  </div>

  <nav class="flex flex-wrap gap-2" aria-label="Show">
    <?php foreach (['open' => 'Open', 'done' => 'Done', 'all' => 'All'] as $k => $label): ?>
      <a href="<?= e(url('admin/chats.php', ['f' => $k])) ?>" class="rounded-full px-4 py-1.5 text-sm font-semibold <?= $f === $k ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= $label ?><?php if ($k !== 'all'): ?> <span class="<?= $f === $k ? 'text-brand-100' : 'text-slate-400' ?>"><?= (int) ($counts[$k] ?? 0) ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="cs-wrap<?= $thread ? ' has-thread' : '' ?>" data-cs data-endpoint="<?= e(url('chat.php')) ?>" data-thread="<?= $thread ? $tid : 0 ?>" data-read="<?= $thread ? (int) $thread['user_read_id'] : 0 ?>" data-last="<?= $msgs ? end($msgs)['id'] : 0 ?>" data-sig="<?= $sig ?>">
    <section class="cs-card cs-list" aria-label="Chats">
      <?php if (!$threads): ?>
        <p class="cs-empty"><?= $f === 'open' ? 'No open chats. When someone writes with the Chat button, it shows up here and you get an email.' : 'Nothing here yet.' ?></p>
      <?php endif; ?>
      <?php foreach ($threads as $t): ?>
        <a class="cs-row<?= (int) $t['id'] === $tid ? ' cur' : '' ?><?= (int) $t['admin_unread'] ? ' unread' : '' ?>" href="<?= e(url('admin/chats.php', ['f' => $f, 't' => $t['id']])) ?>">
          <span class="cs-avatar"><?= e($chatInitials($t['name'])) ?></span>
          <span class="cs-main"><b><?= e($t['name']) ?></b><span><?= $t['last_from'] === 'admin' ? 'You: ' : '' ?><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $t['last_body']), 0, 70, '…')) ?></span></span>
          <span class="cs-when"><time data-at="<?= (int) $t['updated_at'] ?>" data-short><?= e(gmdate('M j', (int) $t['updated_at'])) ?></time><?php if ((int) $t['rating']): ?><span class="cs-stars" title="Rated <?= (int) $t['rating'] ?> out of 5"><?= $stars((int) $t['rating']) ?></span><?php endif; ?><?php if ((int) $t['admin_unread']): ?><b class="cs-count"><?= (int) $t['admin_unread'] ?></b><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </section>

    <section class="cs-card cs-conv" aria-label="Conversation">
      <?php if (!$thread): ?>
        <p class="cs-empty">Pick a chat on the left to read it and reply.</p>
      <?php else: $first = chat_first_name($thread['name']); ?>
        <div class="cs-head">
          <a class="cs-back" href="<?= e(url('admin/chats.php', ['f' => $f])) ?>">← All chats</a>
          <div class="cs-who"><b><?= e($thread['name']) ?></b>
            <span><span class="cs-type"><?= $thread['account_type'] === 'member' ? 'Customer' : 'Visitor' ?></span> <a href="mailto:<?= e($thread['email']) ?>"><?= e($thread['email']) ?></a> · started <time data-at="<?= (int) $thread['created_at'] ?>"><?= e(gmdate('M j, H:i', (int) $thread['created_at'])) ?> UTC</time><span class="cs-here" data-cs-here<?= time() - (int) $thread['user_seen_at'] < CHAT_AWAY_AFTER ? '' : ' hidden' ?>> · <i></i>on the website now</span></span></div>
          <div class="cs-acts">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="<?= $thread['status'] === 'done' ? 'open' : 'done' ?>"><button class="cs-btn" type="submit"<?= $thread['status'] === 'done' ? '' : ' title="Ends the chat. They\'re asked to rate it."' ?>><?= $thread['status'] === 'done' ? 'Reopen' : 'Mark as done' ?></button></form>
            <form method="post" data-confirm="Delete this chat with <?= e($thread['name']) ?>? This can't be undone."><?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="delete"><button class="cs-btn danger" type="submit">Delete</button></form>
          </div>
        </div>
        <?php if ($bookings): ?>
          <p class="cs-bookings">Their bookings: <?php foreach ($bookings as $bk): ?><a href="<?= e(url('admin/booking.php', ['ref' => $bk['reference']])) ?>" title="<?= e(ucfirst($bk['status'])) ?>"><?= e($bk['reference']) ?></a><?php endforeach; ?></p>
        <?php endif; ?>
        <?php if ((int) $thread['ended_at']): ?>
          <div class="cs-ended">
            <span>Ended by <?= $thread['ended_by'] === 'user' ? e($first) : 'you' ?> · <time data-at="<?= (int) $thread['ended_at'] ?>"><?= e(gmdate('M j, H:i', (int) $thread['ended_at'])) ?> UTC</time></span>
            <?php if ((int) $thread['rating']): ?>
              <span class="cs-rating"><b class="cs-stars"><?= $stars((int) $thread['rating']) ?></b> <?= (int) $thread['rating'] ?> out of 5<?php if ($thread['feedback'] !== ''): ?> · “<?= e($thread['feedback']) ?>”<?php endif; ?></span>
            <?php else: ?>
              <span class="text-slate-500">Not rated yet</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="cs-msgs" data-cs-msgs>
          <?php foreach ($msgs as $m): ?>
            <div class="cm cm-<?= $m['from'] === 'admin' ? 'me' : 'them' ?>" data-msg-id="<?= $m['id'] ?>"><p><?= e($m['text']) ?></p><small data-at="<?= $m['at'] ?>"><?= e(gmdate('M j, H:i', $m['at'])) ?></small></div>
          <?php endforeach; ?>
        </div>
        <p class="cs-typing" data-cs-typing hidden><?= e($first) ?> is typing<span class="dots"><i></i><i></i><i></i></span></p>
        <form method="post" class="cs-reply" id="reply">
          <?= csrf_field() ?><input type="hidden" name="t" value="<?= $tid ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="action" value="reply">
          <label class="sr-only" for="cs-text">Your reply</label>
          <textarea id="cs-text" name="text" rows="3" maxlength="<?= CHAT_MAX_LEN ?>" placeholder="Type your reply… (Ctrl+Enter sends)" required data-cs-text><?= e($draft) ?></textarea>
          <div class="cs-send"><span>If they've left the website, they get your reply by email.<?= (int) $thread['ended_at'] ? ' Replying reopens this chat.' : '' ?></span><button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700" type="submit">Send</button></div>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<script src="<?= e(asset('chat.js')) ?>" defer></script>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
