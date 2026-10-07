<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Live chat: the Chat button on the website (assets/chat.js, chat.php) and Admin -> Support chats.
 * Members are recognised by their account; visitors by a random cookie (only a hash of it is stored)
 * plus the name and email they give, so staff can answer by email after they leave.
 * A person only ever sees their own conversation. Nothing here is sent to any other company.
 */

const CHAT_MAX_LEN      = 2000;      // characters per message
const CHAT_FEEDBACK_LEN = 500;       // characters in the rating comment
const CHAT_MSGS_PER_10M = 20;        // messages per chat in 10 minutes
const CHAT_NEW_PER_HOUR = 5;         // new chats per IP address per hour
const CHAT_NOTIFY_GAP   = 15 * 60;   // at most one email per chat in this time
const CHAT_AWAY_AFTER   = 120;       // after this many seconds without the chat open, a person counts as gone
const CHAT_KEEP_DAYS    = 180;       // chats with no new messages for this long are deleted
const CHAT_TYPING_FOR   = 6;         // "is typing" shows this long after the last key press
const CHAT_COOKIE       = 'll_chat';

/** The person on this browser: ['member', user id] or ['visitor', hash or '']. Staff use Support chats instead. */
function chat_owner(): ?array
{
    $u = current_user();
    if ($u && $u['is_admin']) {
        return null;
    }
    if ($u) {
        return ['member', (int) $u['id']];
    }
    $raw = (string) ($_COOKIE[CHAT_COOKIE] ?? '');
    return preg_match('/^[a-f0-9]{48}$/', $raw) ? ['visitor', hash('sha256', $raw)] : ['visitor', ''];
}

function chat_new_cookie(): string
{
    $raw = bin2hex(random_bytes(24));
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    setcookie(CHAT_COOKIE, $raw, ['expires' => time() + 30 * 86400, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    return hash('sha256', $raw);
}

function chat_thread_for(array $owner): ?array
{
    [$type, $id] = $owner;
    if ($type === 'member') {
        return db_one("SELECT * FROM support_threads WHERE account_type = 'member' AND account_id = ? ORDER BY id DESC LIMIT 1", [$id]);
    }
    return $id !== '' ? db_one("SELECT * FROM support_threads WHERE account_type = 'visitor' AND visitor_hash = ? ORDER BY id DESC LIMIT 1", [$id]) : null;
}

function chat_thread(int $id): ?array
{
    return db_one('SELECT * FROM support_threads WHERE id = ?', [$id]);
}

function chat_messages(int $threadId, int $after = 0): array
{
    return array_map(fn ($m) => ['id' => (int) $m['id'], 'from' => $m['sender'], 'text' => $m['body'], 'at' => (int) $m['created_at']],
        db_all('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? AND id > ? ORDER BY id', [$threadId, $after]));
}

/** Staff count as online while someone has Support chats open (seen in the last 2 minutes). */
function chat_admin_online(): bool
{
    return time() - (int) db_val("SELECT v FROM meta WHERE k = 'chat_admin_seen'") < CHAT_AWAY_AFTER;
}

function chat_admin_seen(): void
{
    db_run("INSERT INTO meta (k, v) VALUES ('chat_admin_seen', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string) time()]);
}

function chat_typing(int $threadId, string $who): void
{
    db_run('UPDATE support_threads SET ' . ($who === 'admin' ? 'admin' : 'user') . '_typing_at = ? WHERE id = ?', [time(), $threadId]);
}

function chat_is_typing(array $thread, string $who): bool
{
    return time() - (int) $thread[($who === 'admin' ? 'admin' : 'user') . '_typing_at'] <= CHAT_TYPING_FOR;
}

function chat_first_name(string $name): string
{
    return first_name($name);
}

/** The first admin's first name, shown in the chat ("Lamar is typing"). */
function chat_admin_name(): string
{
    static $name = null;
    if ($name === null) {
        $n = (string) db_val('SELECT name FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1');
        $name = $n !== '' ? chat_first_name($n) : '';
    }
    return $name;
}

function chat_unread_total(): int
{
    return (int) db_val('SELECT COUNT(*) FROM support_threads WHERE admin_unread > 0');
}

function rate_limited(string $kind, string $key, int $max, int $seconds): bool
{
    return (int) db_val('SELECT COUNT(*) FROM rate_hits WHERE kind = ? AND k = ? AND created_at > ?', [$kind, $key, time() - $seconds]) >= $max;
}

function record_hit(string $kind, string $key): void
{
    db_run('INSERT INTO rate_hits (kind, k, created_at) VALUES (?, ?, ?)', [$kind, $key, time()]);
}

/** Someone sent a message with the Chat button. Returns [thread, error message or '']. */
function chat_send(string $body, string $name, string $email, string $page): array
{
    $owner = chat_owner();
    if (!$owner) {
        return [null, 'Use Support chats to answer people.'];
    }
    $body = trim((string) preg_replace("/\r\n?/", "\n", $body));
    if ($body === '') {
        return [null, 'Type a message first.'];
    }
    if (mb_strlen($body) > CHAT_MAX_LEN) {
        return [null, 'That message is too long (' . number_format(CHAT_MAX_LEN) . ' characters at most).'];
    }
    $thread = chat_thread_for($owner);
    $prev = null;
    if ($thread && (int) $thread['ended_at'] > 0) { // an ended chat: this message starts a new one
        $prev = $thread;
        $thread = null;
    }
    $key = $thread ? 't' . $thread['id'] : 'ip' . limit_ip();
    if (rate_limited('chat', $key, CHAT_MSGS_PER_10M, 600)) {
        return [null, "You've sent a lot of messages. Please wait a few minutes."];
    }
    if (!$thread) {
        if ($owner[0] === 'member') {
            $u = current_user();
            $name = (string) $u['name'];
            $email = (string) $u['email'];
        } elseif ($prev) {
            $name = (string) $prev['name']; // same visitor: no need to ask again
            $email = (string) $prev['email'];
        } else {
            $name = trim($name);
            $email = trim($email);
            if ($name === '' || mb_strlen($name) > 80) {
                return [null, 'Enter your name.'];
            }
            if (!email_valid($email)) {
                return [null, 'Enter a valid email, so we can reply if you leave.'];
            }
            if (rate_limited('chat-new', limit_ip(), CHAT_NEW_PER_HOUR, 3600)) {
                return [null, 'Please wait a little before starting another chat.'];
            }
            record_hit('chat-new', limit_ip());
            if ($owner[1] === '') {
                $owner[1] = chat_new_cookie();
            }
        }
        db_run("INSERT INTO support_threads (account_type, account_id, visitor_hash, name, email, status, page, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'open', ?, ?, ?)",
            [$owner[0], $owner[0] === 'member' ? $owner[1] : 0, $owner[0] === 'visitor' ? $owner[1] : '', mb_substr($name, 0, 100), $email, mb_substr($page, 0, 80), time(), time()]);
        $thread = chat_thread((int) db()->lastInsertId());
    }
    record_hit('chat', 't' . $thread['id']);
    db_run("INSERT INTO support_messages (thread_id, sender, body, created_at) VALUES (?, 'user', ?, ?)", [$thread['id'], $body, time()]);
    db_run("UPDATE support_threads SET updated_at = ?, last_from = 'user', admin_unread = admin_unread + 1, user_unread = 0, user_seen_at = ?, user_typing_at = 0, status = 'open' WHERE id = ?",
        [time(), time(), $thread['id']]);
    $thread = chat_thread((int) $thread['id']);
    if (!chat_admin_online() && time() - (int) $thread['admin_notified_at'] >= CHAT_NOTIFY_GAP) {
        chat_notify_admin($thread, $body);
    }
    return [$thread, ''];
}

function chat_notify_admin(array $thread, string $body): void
{
    $who = ($thread['account_type'] === 'member' ? 'Member' : 'Visitor') . ' · ' . $thread['email'];
    [$text, $html] = email_body('New chat message', [
        $thread['name'] . ' (' . $who . ') wrote:',
        mb_strimwidth($body, 0, 600, '…'),
    ], 'Answer in Support chats', abs_url('admin/chats.php?t=' . (int) $thread['id']),
        'You get at most one email every ' . (CHAT_NOTIFY_GAP / 60) . ' minutes per chat, and none while Support chats is open.');
    send_mail(support_email(), 'Chat: ' . $thread['name'] . ': ' . mb_strimwidth((string) preg_replace('/\s+/', ' ', $body), 0, 50, '…'), $text, $html, (string) $thread['email']);
    db_run('UPDATE support_threads SET admin_notified_at = ? WHERE id = ?', [time(), $thread['id']]);
}

/** Staff answered. Emails the person when they're not on the website. Returns an error message or ''. */
function chat_reply(int $threadId, string $body): string
{
    $thread = chat_thread($threadId);
    if (!$thread) {
        return 'That chat no longer exists.';
    }
    $body = trim((string) preg_replace("/\r\n?/", "\n", $body));
    if ($body === '') {
        return 'Type a reply first.';
    }
    if (mb_strlen($body) > CHAT_MAX_LEN) {
        return 'That reply is too long (' . number_format(CHAT_MAX_LEN) . ' characters at most).';
    }
    db_run("INSERT INTO support_messages (thread_id, sender, body, created_at) VALUES (?, 'admin', ?, ?)", [$threadId, $body, time()]);
    db_run("UPDATE support_threads SET updated_at = ?, last_from = 'admin', user_unread = user_unread + 1, admin_unread = 0, admin_typing_at = 0, status = 'open', ended_at = 0, ended_by = '' WHERE id = ?",
        [time(), $threadId]);
    if (time() - (int) $thread['user_seen_at'] >= CHAT_AWAY_AFTER && time() - (int) $thread['user_notified_at'] >= CHAT_NOTIFY_GAP) {
        $domain = parse_url((string) config('app_url'), PHP_URL_HOST) ?: 'lamazonloads.com';
        [$text, $html] = email_body('You have a reply', [
            'Hi ' . chat_first_name((string) $thread['name']) . ',',
            'We answered your message on LamazonLoads:',
            mb_strimwidth($body, 0, 1500, '…'),
        ], 'Open the chat', abs_url(($thread['account_type'] === 'member' ? 'account.php' : '') . '?chat=1'),
            'You can reply in the chat on ' . $domain . ', or just reply to this email.');
        if (send_mail((string) $thread['email'], 'Reply from LamazonLoads support', $text, $html, support_email())) {
            db_run('UPDATE support_threads SET user_notified_at = ? WHERE id = ?', [time(), $threadId]);
        }
    }
    return '';
}

/** Mark as done (ends the chat: they're asked to rate it) or Reopen. */
function chat_set_status(int $threadId, string $status): void
{
    if ($status === 'done') {
        db_run("UPDATE support_threads SET status = 'done', admin_unread = 0, ended_at = IF(ended_at = 0, ?, ended_at), ended_by = IF(ended_by = '', 'admin', ended_by) WHERE id = ?", [time(), $threadId]);
    } else {
        db_run("UPDATE support_threads SET status = 'open', admin_unread = 0, ended_at = 0, ended_by = '' WHERE id = ?", [$threadId]);
    }
}

/** The person clicked End chat. */
function chat_end(array $thread): void
{
    db_run("UPDATE support_threads SET status = 'done', ended_at = ?, ended_by = 'user', user_typing_at = 0 WHERE id = ? AND ended_at = 0", [time(), $thread['id']]);
}

/** Their rating (1-5 stars) and optional comment, once per ended chat. */
function chat_rate(array $thread, int $rating, string $comment, bool $skip = false): string
{
    if ((int) $thread['ended_at'] === 0) {
        return 'End the chat first.';
    }
    if ($skip) {
        db_run('UPDATE support_threads SET rated_at = ? WHERE id = ?', [time(), $thread['id']]);
        return '';
    }
    if ($rating < 1 || $rating > 5) {
        return 'Pick from 1 to 5 stars.';
    }
    db_run('UPDATE support_threads SET rating = ?, feedback = ?, rated_at = ? WHERE id = ?',
        [$rating, mb_substr(trim($comment), 0, CHAT_FEEDBACK_LEN), time(), $thread['id']]);
    return '';
}

/** Ratings in the last 90 days, for the tiles on Support chats. */
function chat_rating_stats(): array
{
    $r = db_one('SELECT COUNT(*) n, AVG(rating) avg FROM support_threads WHERE rating > 0 AND rated_at >= ?', [time() - 90 * 86400]);
    return ['n' => (int) $r['n'], 'avg' => $r['n'] ? round((float) $r['avg'], 1) : null];
}

function chat_delete(int $threadId): void
{
    db_run('DELETE FROM support_threads WHERE id = ?', [$threadId]); // messages go with it (ON DELETE CASCADE)
}

function chat_delete_for_user(int $userId): void
{
    db_run("DELETE FROM support_threads WHERE account_type = 'member' AND account_id = ?", [$userId]);
}

/** Conversations for Support chats, newest activity first, with the last message. */
function chat_threads(string $filter = 'open'): array
{
    $where = $filter === 'done' ? "status = 'done'" : ($filter === 'all' ? '1' : "status = 'open'");
    return db_all("SELECT t.*, (SELECT body FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body
        FROM support_threads t WHERE $where ORDER BY t.updated_at DESC LIMIT 200");
}

/** Delete chats with no new messages for CHAT_KEEP_DAYS, and old rate-limit records. Runs at most once a day. */
function chat_cleanup(): int
{
    if (time() - (int) db_val("SELECT v FROM meta WHERE k = 'chat_cleanup'") < 86400) {
        return 0;
    }
    db_run("INSERT INTO meta (k, v) VALUES ('chat_cleanup', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string) time()]);
    db_run('DELETE FROM rate_hits WHERE created_at < ?', [time() - 86400]);
    return db_run('DELETE FROM support_threads WHERE updated_at < ?', [time() - CHAT_KEEP_DAYS * 86400]);
}

/** The Chat button (bottom-right). Not shown to staff, who use Support chats. */
function chat_bubble(): string
{
    $owner = chat_owner();
    if (!$owner) {
        return '';
    }
    $thread = chat_thread_for($owner);
    $has = $thread && !((int) $thread['ended_at'] > 0 && (int) $thread['rated_at'] > 0);
    return '<div class="chat" data-chat data-endpoint="' . e(url('chat.php')) . '" data-csrf="' . e(csrf_token()) . '" data-member="' . (current_user() ? '1' : '0') . '"'
        . ' data-has="' . ($has ? '1' : '0') . '" data-help="' . e(url('#faq')) . '" data-admin="' . e(chat_admin_name()) . '">'
        . '<button type="button" class="chat-fab" data-chat-open aria-label="Chat with us" aria-expanded="false" aria-controls="chat-panel">'
        . icon('chat', 'ic') . '<span>Chat</span><b class="chat-dot" data-chat-dot hidden></b></button>'
        . '</div><script src="' . e(asset('chat.js')) . '" defer></script>';
}
