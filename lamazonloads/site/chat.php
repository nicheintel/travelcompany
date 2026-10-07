<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// The Chat button's connection (JSON), used by assets/chat.js.
// GET a=poll: the person's own conversation and new messages. POST a=send|typing|end|rate.
// Staff: GET a=admin (live updates on Support chats) and POST a=typing.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
$a = as_str($_GET['a'] ?? $_POST['a'] ?? 'poll');
$csrfOk = fn (): bool => is_post() && is_string($_POST['csrf'] ?? null) && hash_equals(csrf_token(), $_POST['csrf']);

// Staff: live view of Support chats (not until they've replaced a temporary password)
if (is_admin() && !empty(current_user()['must_change_password'])) {
    $out(['error' => 'Please choose your own password first.'], 403);
}
if (is_admin()) {
    $t = (int) ($_GET['t'] ?? $_POST['t'] ?? 0);
    if ($a === 'typing') {
        if (!$csrfOk()) {
            $out(['error' => 'Please reload the page.'], 400);
        }
        if ($t) {
            chat_typing($t, 'admin');
        }
        $out(['ok' => true]);
    }
    if ($a !== 'admin') {
        $out(['error' => 'Use Support chats to answer people.'], 400);
    }
    chat_admin_seen();
    if ($t) {
        db_run('UPDATE support_threads SET admin_unread = 0, admin_read_id = (SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE thread_id = ?) WHERE id = ?', [$t, $t]);
    }
    $thread = $t ? chat_thread($t) : null;
    $out([
        'unread' => chat_unread_total(),
        'sig' => (int) db_val('SELECT COALESCE(MAX(id), 0) FROM support_messages'),
        'messages' => $thread ? chat_messages($t, (int) ($_GET['after'] ?? 0)) : [],
        'here' => $thread ? time() - (int) $thread['user_seen_at'] < CHAT_AWAY_AFTER : false,
        'typing' => $thread ? chat_is_typing($thread, 'user') : false,
        'read' => $thread ? (int) $thread['user_read_id'] : 0,
    ]);
}

if ($a === 'typing') {
    if (!$csrfOk()) {
        $out(['error' => 'Please reload the page.'], 400);
    }
    $owner = chat_owner();
    $th = $owner ? chat_thread_for($owner) : null;
    if ($th && (int) $th['ended_at'] === 0) {
        chat_typing((int) $th['id'], 'user');
        db_run('UPDATE support_threads SET user_seen_at = ? WHERE id = ?', [time(), $th['id']]);
    }
    $out(['ok' => true]);
}

if ($a === 'end' || $a === 'rate') {
    if (!$csrfOk()) {
        $out(['error' => 'Please reload the page and try again.'], 400);
    }
    $owner = chat_owner();
    $th = $owner ? chat_thread_for($owner) : null;
    if (!$th) {
        $out(['error' => "There's no chat to end."], 404);
    }
    if ($a === 'end') {
        chat_end($th);
        $out(['ok' => true]);
    }
    $err = chat_rate(chat_thread((int) $th['id']), (int) ($_POST['rating'] ?? 0), as_str($_POST['comment'] ?? ''), ($_POST['skip'] ?? '') === '1');
    $out($err === '' ? ['ok' => true] : ['error' => $err], $err === '' ? 200 : 422);
}

if ($a === 'send') {
    if (!$csrfOk()) {
        $out(['error' => 'Please reload the page and try again.'], 400);
    }
    if (post('website') !== '') { // a field only bots fill in
        $out(['ok' => true, 'messages' => []]);
    }
    [$thread, $err] = chat_send(as_str($_POST['text'] ?? ''), post('name', 100), post('email', 190), post('page', 80));
    if ($err !== '') {
        $out(['error' => $err], 422);
    }
    $out(['ok' => true, 'thread' => true, 'messages' => chat_messages((int) $thread['id'], (int) ($_POST['after'] ?? 0)),
        'online' => chat_admin_online(), 'read' => (int) $thread['admin_read_id']]);
}

// poll
$owner = chat_owner();
$thread = $owner ? chat_thread_for($owner) : null;
// No chat yet, or an ended chat they already rated: show a fresh chat
if (!$thread || ((int) $thread['ended_at'] > 0 && (int) $thread['rated_at'] > 0)) {
    $out(['thread' => false, 'online' => $owner ? chat_admin_online() : false, 'messages' => [], 'unread' => 0]);
}
$open = ($_GET['open'] ?? '') === '1';
if ($open) {
    db_run('UPDATE support_threads SET user_unread = 0, user_seen_at = ?, user_read_id = (SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE thread_id = ?) WHERE id = ?',
        [time(), $thread['id'], $thread['id']]);
}
$out([
    'thread' => true,
    'online' => chat_admin_online(),
    'unread' => $open ? 0 : (int) $thread['user_unread'],
    'typing' => $open && chat_is_typing($thread, 'admin'),
    'read' => (int) $thread['admin_read_id'],
    'ended' => (int) $thread['ended_at'] > 0 ? (string) $thread['ended_by'] : '',
    'messages' => chat_messages((int) $thread['id'], (int) ($_GET['after'] ?? 0)),
]);
