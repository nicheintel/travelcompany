<?php
declare(strict_types=1);
defined('WS_APP') || exit;

/*
 * Admin accounts. There is no public sign-up: visitors send a request without an account,
 * and only the team signs in (at /admin/).
 * - Passwords: password_hash() (bcrypt).
 * - The session stores the admin id plus admins.session_version; changing a password bumps
 *   the version, which signs out every other browser.
 */

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    $admin = $id ? db_one('SELECT id, name, email, session_version, created_at, last_login_at FROM admins WHERE id = ?', [$id]) : null;
    if ($admin && (int) $admin['session_version'] !== (int) ($_SESSION['admin_ver'] ?? 0)) {
        unset($_SESSION['admin_id'], $_SESSION['admin_ver']); // password changed elsewhere
        $admin = null;
    }
    return $admin;
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_ver'] = (int) $admin['session_version'];
    db_run('UPDATE admins SET last_login_at = ? WHERE id = ?', [now_utc(), $admin['id']]);
}

function logout_admin(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Admin pages: signed-out visitors go to the sign-in page, then back here. */
function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(url('admin/login.php', ['next' => (string) ($_SERVER['REQUEST_URI'] ?? '')]));
    }
    return $admin;
}

function has_admins(): bool
{
    return (bool) db_value('SELECT 1 FROM admins LIMIT 1');
}

/** Passwords: 8–128 characters with a letter and a number. Returns an error or null. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (strlen($password) > 128) return 'Password must be 128 characters or fewer.';
    if (!preg_match('/[a-zA-Z]/', $password)) return 'Password must contain at least one letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    return null;
}

// ---------- Rate limiting (stored in the database) ----------

function rate_limited(string $key, int $max): bool
{
    $row = db_one('SELECT hits, reset_at FROM rate_limits WHERE rkey = ?', [$key]);
    if (!$row) return false;
    if ($row['reset_at'] < now_utc()) {
        db_run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
        return false;
    }
    return (int) $row['hits'] >= $max;
}

function rate_hit(string $key, int $windowSeconds): void
{
    $reset = gmdate('Y-m-d H:i:s', time() + $windowSeconds);
    db_run(
        'INSERT INTO rate_limits (rkey, hits, reset_at) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE hits = IF(reset_at < ?, 1, hits + 1), reset_at = IF(reset_at < ?, VALUES(reset_at), reset_at)',
        [$key, $reset, now_utc(), now_utc()],
    );
}

function rate_clear(string $key): void
{
    db_run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
}

/**
 * Per-IP limit for an action. Counts this attempt and returns true when the visitor has gone
 * over $max in $windowSeconds. Not applied on your own computer (so testing is easy).
 */
function ip_throttled(string $action, int $max, int $windowSeconds): bool
{
    if (is_local_request() && !getenv('WS_TEST_LIMITS')) return false;
    $key = "ip:$action:" . client_ip();
    if (rate_limited($key, $max)) return true;
    rate_hit($key, $windowSeconds);
    return false;
}

/**
 * Checks an admin's email + password. 5 wrong passwords lock that email for 15 minutes.
 * Returns the admin row, or an error message.
 */
function attempt_login(string $email, string $password): array|string
{
    $email = normalize_email($email);
    $key = 'signin:' . $email;
    if (rate_limited($key, 5)) {
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $row = db_one('SELECT * FROM admins WHERE email = ?', [$email]);
    // Verify against a dummy hash when the email is unknown, so timing doesn't reveal which emails exist.
    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    if (!password_verify($password, $hash) || !$row) {
        rate_hit($key, 900);
        return 'That email and password don\'t match.';
    }
    rate_clear($key);
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    return $row;
}
