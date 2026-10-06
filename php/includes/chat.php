<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * Live support chat: the Chat button on the website (assets/chat.js, chat.php) and Admin → Support chats.
 * Signed-in customers are recognised by their account; visitors by a random cookie (only a hash of it is
 * stored) plus the name and email they give, so the admin can reply by email after they leave.
 * Each conversation is private: a person only ever sees their own. Nothing is sent to any other company.
 */

const CHAT_MAX_LEN = 2000;          // characters per message
const CHAT_FEEDBACK_LEN = 500;      // characters in the rating comment
const CHAT_MSGS_PER_10M = 20;       // messages per conversation in 10 minutes
const CHAT_NEW_PER_HOUR = 5;        // new conversations per IP address per hour
const CHAT_NOTIFY_GAP = 15 * 60;    // at most one email per conversation in this time
const CHAT_AWAY_AFTER = 120;        // seconds after which a person (or the admin) counts as gone
const CHAT_KEEP_DAYS = 180;         // conversations with no messages for this long are deleted
const CHAT_TYPING_FOR = 6;          // "is typing" shows this long after the last key press
const CHAT_COOKIE = 'tc_chat';

/** The person on this browser: ['member', user id] or ['visitor', cookie hash or '']. Admins answer in Support chats. */
function chat_owner(): ?array
{
    $user = current_user();
    if ($user && $user['role'] === 'admin') return null;
    if ($user) return ['member', (int) $user['id']];
    $raw = (string) ($_COOKIE[CHAT_COOKIE] ?? '');
    return preg_match('/^[a-f0-9]{48}$/', $raw) ? ['visitor', hash('sha256', $raw)] : ['visitor', ''];
}

function chat_new_cookie(): string
{
    $raw = bin2hex(random_bytes(24));
    setcookie(CHAT_COOKIE, $raw, ['expires' => time() + 365 * 86400, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => request_is_https()]);
    return hash('sha256', $raw);
}

function chat_thread_for(array $owner): ?array
{
    [$type, $id] = $owner;
    if ($type === 'member') return db_one('SELECT * FROM support_threads WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    if ($id === '') return null;
    return db_one("SELECT * FROM support_threads WHERE account_type = 'visitor' AND visitor_hash = ? ORDER BY id DESC LIMIT 1", [$id]);
}

function chat_thread(int $id): ?array
{
    return db_one('SELECT * FROM support_threads WHERE id = ?', [$id]);
}

function chat_messages(int $threadId, int $after = 0): array
{
    return array_map(fn($m) => ['id' => (int) $m['id'], 'from' => $m['sender'], 'text' => $m['body'], 'at' => (int) $m['created_at']],
        db_all('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? AND id > ? ORDER BY id', [$threadId, $after]));
}

function chat_last_id(int $threadId): int
{
    return (int) (db_one('SELECT COALESCE(MAX(id), 0) AS m FROM support_messages WHERE thread_id = ?', [$threadId])['m'] ?? 0);
}

// ---------- Admin presence ("Online now") ----------

function chat_admin_online(): bool
{
    $v = db_one("SELECT value FROM settings WHERE name = 'chat_admin_seen'");
    return time() - (int) ($v['value'] ?? 0) < CHAT_AWAY_AFTER;
}

function chat_admin_seen(): void
{
    db_run("INSERT INTO settings (name, value, updated_at) VALUES ('chat_admin_seen', ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)", [(string) time(), now_utc()]);
}

/** The name customers see ("Angel is typing"): the setting, else the first admin's first name. */
function chat_admin_name(): string
{
    $name = trim((string) config('chat_name'));
    if ($name !== '') return $name;
    $emails = configured_admins();
    $row = db_one("SELECT name FROM users WHERE role = 'admin'" . ($emails ? ' OR email IN (' . implode(',', array_fill(0, count($emails), '?')) . ')' : '') . ' ORDER BY id LIMIT 1', $emails);
    return $row ? explode(' ', trim($row['name']))[0] : (string) config('site_name');
}

/** Every admin's email address (for "new chat message" emails). */
function chat_admin_emails(): array
{
    return array_values(array_unique(array_merge(configured_admins(), array_column(db_all("SELECT email FROM users WHERE role = 'admin'"), 'email'))));
}

function chat_typing(int $threadId, string $who): void
{
    db_run('UPDATE support_threads SET ' . ($who === 'admin' ? 'admin' : 'user') . '_typing_at = ? WHERE id = ?', [time(), $threadId]);
}

function chat_is_typing(array $thread, string $who): bool
{
    return time() - (int) $thread[($who === 'admin' ? 'admin' : 'user') . '_typing_at'] <= CHAT_TYPING_FOR;
}

function chat_unread_total(): int
{
    return (int) (db_one('SELECT COUNT(*) AS n FROM support_threads WHERE admin_unread > 0')['n'] ?? 0);
}

function chat_first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}

// ---------- Customer side ----------

/** A message from the Chat button. Returns [thread, error message or '']. */
function chat_send(string $body, string $name, string $email, string $page = ''): array
{
    $owner = chat_owner();
    if (!$owner) return [null, 'Use Support chats to answer people.'];
    $body = trim(preg_replace("/\r\n?/", "\n", $body));
    if ($body === '') return [null, t('Type a message first.')];
    if (mb_strlen($body) > CHAT_MAX_LEN) return [null, t('That message is too long ({n} characters at most).', ['n' => number_format(CHAT_MAX_LEN)])];
    $thread = chat_thread_for($owner);
    $previous = null;
    if ($thread && (int) $thread['ended_at'] > 0) { $previous = $thread; $thread = null; }   // an ended chat: this starts a new one
    $limitKey = 'chat:' . ($thread ? 't' . $thread['id'] : 'ip' . client_ip());
    if (rate_limited($limitKey, CHAT_MSGS_PER_10M)) return [null, t("You've sent a lot of messages. Please wait a few minutes.")];

    if (!$thread) {
        if ($owner[0] === 'member') {
            $user = current_user();
            $name = $user['name'];
            $email = $user['email'];
        } elseif ($previous) {
            $name = $previous['name'];   // the same visitor: no need to ask again
            $email = $previous['email'];
        } else {
            $name = trim(preg_replace('/\s+/', ' ', $name));
            $email = normalize_email($email);
            if ($name === '' || mb_strlen($name) > 80) return [null, t('Enter your name.')];
            if (!valid_email($email) || mb_strlen($email) > 190) return [null, t('Enter a valid email, so we can reply if you leave.')];
            if (ip_throttled('chat-new', CHAT_NEW_PER_HOUR, 3600)) return [null, t('Please wait a little before starting another chat.')];
            if ($owner[1] === '') $owner[1] = chat_new_cookie();
        }
        db_run('INSERT INTO support_threads (account_type, user_id, visitor_hash, name, email, status, created_at, updated_at, page) VALUES (?, ?, ?, ?, ?, \'open\', ?, ?, ?)', [
            $owner[0], $owner[0] === 'member' ? $owner[1] : null, $owner[0] === 'visitor' ? $owner[1] : '',
            mb_substr($name, 0, 80), mb_substr($email, 0, 190), time(), time(), mb_substr($page, 0, 120),
        ]);
        $thread = chat_thread((int) db()->lastInsertId());
        $limitKey = 'chat:t' . $thread['id'];
    }
    rate_hit($limitKey, 600);
    db_run("INSERT INTO support_messages (thread_id, sender, body, created_at) VALUES (?, 'user', ?, ?)", [$thread['id'], $body, time()]);
    db_run("UPDATE support_threads SET updated_at = ?, last_from = 'user', admin_unread = admin_unread + 1, user_unread = 0, user_seen_at = ?, user_typing_at = 0, status = 'open' WHERE id = ?",
        [time(), time(), $thread['id']]);
    $thread = chat_thread((int) $thread['id']);
    if (!chat_admin_online() && time() - (int) $thread['admin_notified_at'] >= CHAT_NOTIFY_GAP) chat_notify_admin($thread, $body);
    return [$thread, ''];
}

/** The person opened the chat: everything from the admin counts as read. */
function chat_mark_user_read(array $thread): void
{
    db_run('UPDATE support_threads SET user_unread = 0, user_seen_at = ?, user_read_id = ? WHERE id = ?', [time(), chat_last_id((int) $thread['id']), $thread['id']]);
}

function chat_end(array $thread): void
{
    db_run("UPDATE support_threads SET status = 'done', ended_at = ?, ended_by = 'user', user_typing_at = 0 WHERE id = ? AND ended_at = 0", [time(), $thread['id']]);
}

/** Their rating (1–5 stars) and an optional comment, once per ended chat. */
function chat_rate(array $thread, int $rating, string $comment, bool $skip = false): string
{
    if ((int) $thread['ended_at'] === 0) return t('End the chat first.');
    if ($skip) {
        db_run('UPDATE support_threads SET rated_at = ? WHERE id = ?', [time(), $thread['id']]);
        return '';
    }
    if ($rating < 1 || $rating > 5) return t('Pick from 1 to 5 stars.');
    db_run('UPDATE support_threads SET rating = ?, feedback = ?, rated_at = ? WHERE id = ?', [$rating, mb_substr(trim($comment), 0, CHAT_FEEDBACK_LEN), time(), $thread['id']]);
    return '';
}

// ---------- Emails ----------

function chat_notify_admin(array $thread, string $body): void
{
    $to = chat_admin_emails();
    if (!$to) return;
    in_english(function () use ($thread, $body, $to) {
        try {
            $link = app_url() . '/admin/chats.php?t=' . $thread['id'] . '#reply';
        } catch (RuntimeException $e) {
            $link = url('admin/chats.php', ['t' => $thread['id']]);
        }
        $who = ($thread['account_type'] === 'member' ? 'Customer' : 'Visitor') . ' · ' . $thread['email'];
        $mail = simple_email(
            'Chat: ' . $thread['name'] . ': ' . mb_strimwidth(preg_replace('/\s+/', ' ', $body), 0, 50, '…'),
            'New chat message',
            chat_admin_name(),
            [$thread['name'] . " ($who) wrote:", mb_strimwidth($body, 0, 600, '…'),
                'You get at most one email every ' . (CHAT_NOTIFY_GAP / 60) . ' minutes per chat, and none while Support chats is open.'],
            [], $link, 'Answer in Support chats',
        );
        $mail['reply_to'] = $thread['email'];
        foreach ($to as $address) send_email($address, $mail);
    });
    db_run('UPDATE support_threads SET admin_notified_at = ? WHERE id = ?', [time(), $thread['id']]);
}

/** The admin answered. Emails the person when they're not on the website. Returns an error or ''. */
function chat_reply(int $threadId, string $body): string
{
    $thread = chat_thread($threadId);
    if (!$thread) return 'That chat no longer exists.';
    $body = trim(preg_replace("/\r\n?/", "\n", $body));
    if ($body === '') return 'Type a reply first.';
    if (mb_strlen($body) > CHAT_MAX_LEN) return 'That reply is too long (' . number_format(CHAT_MAX_LEN) . ' characters at most).';
    db_run("INSERT INTO support_messages (thread_id, sender, body, created_at) VALUES (?, 'admin', ?, ?)", [$threadId, $body, time()]);
    $newId = (int) db()->lastInsertId();
    db_run("UPDATE support_threads SET updated_at = ?, last_from = 'admin', user_unread = user_unread + 1, admin_unread = 0, admin_read_id = ?, admin_typing_at = 0, status = 'open', ended_at = 0, ended_by = '' WHERE id = ?",
        [time(), $newId, $threadId]);
    if (time() - (int) $thread['user_seen_at'] >= CHAT_AWAY_AFTER && time() - (int) $thread['user_notified_at'] >= CHAT_NOTIFY_GAP) {
        in_english(function () use ($thread, $body, $threadId) {
            try {
                $base = app_url();
            } catch (RuntimeException $e) {
                $base = '';
            }
            $link = $base . '/' . ($thread['account_type'] === 'member' ? 'account.php' : '') . '?chat=1';
            $domain = parse_url($base, PHP_URL_HOST) ?: (string) config('site_name');
            $site = (string) config('site_name');
            $mail = simple_email("Reply from $site support", 'You have a reply', chat_first_name($thread['name']), [
                "We answered your message on $site:",
                mb_strimwidth($body, 0, 1500, '…'),
                "You can reply in the chat on $domain, or just reply to this email.",
            ], [], $link, 'Open the chat');
            $mail['reply_to'] = (string) config('support_email') ?: (string) config('smtp_user');
            if (deliver_email($thread['email'], $mail) === null) db_run('UPDATE support_threads SET user_notified_at = ? WHERE id = ?', [time(), $threadId]);
        });
    }
    return '';
}

// ---------- Admin ----------

/** Mark as done (ends the chat: they're asked to rate it) or Reopen. */
function chat_set_status(int $threadId, string $status): void
{
    if ($status === 'done') {
        db_run("UPDATE support_threads SET status = 'done', admin_unread = 0, ended_by = IF(ended_at = 0, 'admin', ended_by), ended_at = IF(ended_at = 0, ?, ended_at) WHERE id = ?", [time(), $threadId]);   // MySQL sets columns left to right: ended_by first
    } else {
        db_run("UPDATE support_threads SET status = 'open', ended_at = 0, ended_by = '' WHERE id = ?", [$threadId]);
    }
}

/** The admin opened a chat: their unread count clears and the person sees "Seen". */
function chat_mark_admin_read(int $threadId): void
{
    db_run('UPDATE support_threads SET admin_unread = 0, admin_read_id = ? WHERE id = ?', [chat_last_id($threadId), $threadId]);
}

function chat_delete(int $threadId): void
{
    db_run('DELETE FROM support_threads WHERE id = ?', [$threadId]);   // messages go with it
}

function chat_threads(string $filter = 'open'): array
{
    $where = $filter === 'done' ? "status = 'done'" : ($filter === 'all' ? '1 = 1' : "status = 'open'");
    return db_all("SELECT t.*, (SELECT body FROM support_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body
        FROM support_threads t WHERE $where ORDER BY t.updated_at DESC LIMIT 200");
}

function chat_rating_stats(): array
{
    $r = db_one('SELECT COUNT(*) AS n, AVG(rating) AS avg FROM support_threads WHERE rating > 0 AND rated_at >= ?', [time() - 90 * 86400]);
    return ['n' => (int) $r['n'], 'avg' => $r['n'] ? round((float) $r['avg'], 1) : null];
}

/** Forget conversations nobody has written in for CHAT_KEEP_DAYS (runs when Support chats is opened). */
function chat_cleanup(): int
{
    return db_run('DELETE FROM support_threads WHERE updated_at < ?', [time() - CHAT_KEEP_DAYS * 86400]);
}

// ---------- The Chat button ----------

/** Customer pages only: not for admins (they have Support chats) and not on admin pages. */
function chat_bubble(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, '/admin/')) return '';
    $owner = chat_owner();
    if (!$owner) return '';
    try {
        $has = chat_thread_for($owner) !== null;
    } catch (Throwable $e) {
        return '';   // database not ready (e.g. first install): no chat rather than a broken page
    }
    $admin = chat_admin_name();
    $site = (string) config('site_name');
    // i18n-keys: 'Chat', 'Chat with {site}', 'Online now', 'Usually replies within a few hours', 'End chat', 'Close chat', "Hi! 👋 Ask us anything about flights, hotels or your booking. {name} answers here, and by email if you've left.", 'Quick answers: Help center', '{name} is typing', 'Your name', 'Your email', 'Type your message…', 'Your message', 'Send', "Your message couldn't be sent. Check your connection and try again.", "Got it. We'll reply here, and by email if you've left.", 'End this chat? You can start a new one any time.', '{name} marked this chat as done.', 'You ended the chat.', 'How was your chat?', 'Rating', '{n} star', '{n} stars', 'Anything we could do better? (optional)', 'Your feedback (optional)', 'Skip', 'Send feedback', 'Thanks for your feedback! 🙏', 'Chat ended.', 'Need anything else? You can start a new chat any time.', 'Start a new chat', 'Need help? 👋', "We're online now. Chat with us", 'Send us a message anytime', 'Hide this', 'Sent', 'Seen', 'Website'
    $keys = ['Chat', 'Chat with {site}', 'Online now', 'Usually replies within a few hours', 'End chat', 'Close chat',
        "Hi! 👋 Ask us anything about flights, hotels or your booking. {name} answers here, and by email if you've left.", 'Quick answers: Help center',
        '{name} is typing', 'Your name', 'Your email', 'Type your message…', 'Your message', 'Send',
        "Your message couldn't be sent. Check your connection and try again.", "Got it. We'll reply here, and by email if you've left.",
        'End this chat? You can start a new one any time.', '{name} marked this chat as done.', 'You ended the chat.', 'How was your chat?', 'Rating',
        '{n} star', '{n} stars', 'Anything we could do better? (optional)', 'Your feedback (optional)', 'Skip', 'Send feedback',
        'Thanks for your feedback! 🙏', 'Chat ended.', 'Need anything else? You can start a new chat any time.', 'Start a new chat',
        'Need help? 👋', "We're online now. Chat with us", 'Send us a message anytime', 'Hide this', 'Sent', 'Seen', 'Website'];
    $strings = [];
    foreach ($keys as $k) $strings[$k] = t($k);
    $auth = in_array(basename($script), ['signin.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'verify-email.php'], true);
    return '<div class="chat' . ($auth ? ' chat-auth' : '') . '" data-chat data-endpoint="' . e(url('chat.php')) . '" data-csrf="' . e(csrf_token()) . '"'
        . ' data-member="' . ($owner[0] === 'member' ? '1' : '0') . '" data-has="' . ($has ? '1' : '0') . '" data-help="' . e(url('help.php')) . '"'
        . ' data-admin="' . e($admin) . '" data-site="' . e($site) . '" data-strings="' . e(json_encode($strings, JSON_UNESCAPED_UNICODE)) . '">'
        . '<button type="button" class="chat-fab" data-chat-open aria-expanded="false" aria-controls="chat-panel">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg><span>' . e(t('Chat')) . '</span><b class="chat-dot" data-chat-dot hidden></b></button>'
        . '</div><script src="' . e(asset('chat.js')) . '" defer></script>';
}
