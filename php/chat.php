<?php
/*
 * The Chat button's connection (JSON). GET a=poll: the person's own conversation and new messages.
 * POST a=send|typing|end|rate. The admin's Support chats page uses a=admin (live updates) and a=typing.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
$a = (string) ($_GET['a'] ?? $_POST['a'] ?? 'poll');
$posted = function () use ($out): void {
    if (!is_post() || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) $out(['error' => t('Please reload the page and try again.')], 400);
};

// The admin's live view of Support chats
if (is_admin()) {
    $t = (int) ($_GET['t'] ?? $_POST['t'] ?? 0);
    if ($a === 'typing') {
        $posted();
        if ($t) chat_typing($t, 'admin');
        $out(['ok' => true]);
    }
    if ($a !== 'admin') $out(['error' => 'Use Support chats to answer people.'], 400);
    chat_admin_seen();
    if ($t) chat_mark_admin_read($t);
    $thread = $t ? chat_thread($t) : null;
    $out([
        'unread' => chat_unread_total(),
        'sig' => (int) (db_one('SELECT COALESCE(MAX(id), 0) AS m FROM support_messages')['m'] ?? 0),
        'messages' => $thread ? chat_messages($t, (int) ($_GET['after'] ?? 0)) : [],
        'here' => $thread && time() - (int) $thread['user_seen_at'] < CHAT_AWAY_AFTER,
        'typing' => $thread && chat_is_typing($thread, 'user'),
        'read' => $thread ? (int) $thread['user_read_id'] : 0,
    ]);
}

$owner = chat_owner();
if (!$owner) $out(['error' => 'Use Support chats to answer people.'], 400);

if ($a === 'typing') {
    $posted();
    if ($th = chat_thread_for($owner)) {
        chat_typing((int) $th['id'], 'user');
        db_run('UPDATE support_threads SET user_seen_at = ? WHERE id = ?', [time(), $th['id']]);
    }
    $out(['ok' => true]);
}
if ($a === 'end' || $a === 'rate') {
    $posted();
    $th = chat_thread_for($owner);
    if (!$th) $out(['error' => t("There's no chat to end.")], 404);
    if ($a === 'end') {
        chat_end($th);
        $out(['ok' => true]);
    }
    $err = chat_rate($th, (int) ($_POST['rating'] ?? 0), (string) ($_POST['comment'] ?? ''), ($_POST['skip'] ?? '') === '1');
    $out($err === '' ? ['ok' => true] : ['error' => $err], $err === '' ? 200 : 422);
}
if ($a === 'send') {
    $posted();
    if (post('website') !== '') $out(['ok' => true, 'messages' => []]);   // a hidden field only bots fill in
    [$thread, $err] = chat_send((string) ($_POST['text'] ?? ''), post('name'), post('email'), post('page'));
    if ($err !== '') $out(['error' => $err], 422);
    $out(['ok' => true, 'thread' => true, 'messages' => chat_messages((int) $thread['id'], (int) ($_POST['after'] ?? 0)),
        'online' => chat_admin_online(), 'read' => (int) $thread['admin_read_id']]);
}

// poll
$thread = chat_thread_for($owner);
// An ended chat they've already rated (or skipped): the next message starts a new one, so show a fresh chat
if (!$thread || ((int) $thread['ended_at'] > 0 && (int) $thread['rated_at'] > 0)) {
    $out(['thread' => false, 'online' => chat_admin_online(), 'messages' => [], 'unread' => 0]);
}
$open = ($_GET['open'] ?? '') === '1';
if ($open) chat_mark_user_read($thread);
$out([
    'thread' => true,
    'online' => chat_admin_online(),
    'unread' => $open ? 0 : (int) $thread['user_unread'],
    'typing' => $open && chat_is_typing($thread, 'admin'),
    'read' => (int) $thread['admin_read_id'],
    'ended' => (int) $thread['ended_at'] > 0 ? (string) $thread['ended_by'] : '',
    'messages' => chat_messages((int) $thread['id'], (int) ($_GET['after'] ?? 0)),
]);
