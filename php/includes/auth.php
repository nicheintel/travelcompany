<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * Accounts and sessions.
 * - Passwords: password_hash() (bcrypt).
 * - The PHP session stores the user id plus users.session_version; changing the
 *   password bumps the version, which signs out every other browser.
 */

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function configured_admins(): array
{
    return array_values(array_filter(array_map('normalize_email', explode(',', (string) config('admin_emails')))));
}

function to_user(array $row): array
{
    $byConfig = in_array($row['email'], configured_admins(), true);
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'created_at' => $row['created_at'],
        'role' => $byConfig || $row['role'] === 'admin' ? 'admin' : 'customer',
        'admin_by_config' => $byConfig,
        'session_version' => (int) $row['session_version'],
    ];
}

function find_user(int $id): ?array
{
    $row = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    return $row ? to_user($row) : null;
}

function find_user_by_email(string $email): ?array
{
    return db_one('SELECT * FROM users WHERE email = ?', [normalize_email($email)]);
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = (int) ($_SESSION['uid'] ?? 0);
    $user = $id ? find_user($id) : null;
    if ($user && ($user['session_version'] !== (int) ($_SESSION['ver'] ?? 0))) {
        // Password was changed elsewhere: this browser is signed out.
        unset($_SESSION['uid'], $_SESSION['ver']);
        $user = null;
    }
    if ($user && $user['role'] !== 'admin' && claim_owner($user)) {
        $user['role'] = 'admin';
    }
    return $user;
}

/** Visiting from this same computer (XAMPP), not from the internet. */
function is_local_request(): bool
{
    $loopback = ['127.0.0.1', '::1'];
    $host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $proxied = false;
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_REAL_IP', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP'] as $h) {
        if (!empty($_SERVER[$h])) $proxied = true;
    }
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $loopback, true)
        && (!isset($_SERVER['SERVER_ADDR']) || in_array($_SERVER['SERVER_ADDR'], $loopback, true))
        && in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
        && !$proxied;
}

/**
 * First-run setup on your own computer: while the site has no admin at all (none in the
 * database and none in admin_emails), the account you use on localhost becomes the admin.
 * Never applies to visitors from the internet.
 */
function claim_owner(array $user): bool
{
    if (!is_local_request() || configured_admins() || db_one("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")) {
        return false;
    }
    db_run("UPDATE users SET role = 'admin' WHERE id = ?", [$user['id']]);
    flash("You're the site owner on this computer, so your account is now the admin. Open Admin dashboard → Site settings to add your supplier keys.");
    return true;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['ver'] = $user['session_version'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Protected pages: send signed-out visitors to sign in, then back here. */
function require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect(url('signin.php', ['next' => current_path_with_query()]));
    }
    return $user;
}

/** Admin pages and actions. Others get "not found" so the area isn't advertised. */
function require_admin(): array
{
    $user = require_user();
    if ($user['role'] !== 'admin') {
        not_found();
    }
    remember_site_address();
    return $user;
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

function name_problem(string $name): ?string
{
    $len = mb_strlen($name);
    if ($len < 2) return 'Please enter your full name.';
    if ($len > 60) return 'Name must be 60 characters or fewer.';
    return null;
}

function valid_email(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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

/**
 * Per-IP limit for an action (sign-in, sign-up, searches…). Counts this attempt and returns
 * true when the visitor has gone over $max in $windowSeconds. Not applied on your own computer.
 */
function ip_throttled(string $action, int $max, int $windowSeconds): bool
{
    if (is_local_request() && !getenv('TC_TEST_IP_LIMITS')) return false;
    $key = "ip:$action:" . client_ip();
    if (rate_limited($key, $max)) return true;
    rate_hit($key, $windowSeconds);
    return false;
}

function rate_clear(string $key): void
{
    db_run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
}

/** Sign-in lockout: 5 wrong passwords per email locks it for 15 minutes. */
const SIGNIN_MAX = 5;
const SIGNIN_WINDOW = 900;

/**
 * Verifies a password with the shared lockout.
 * Returns null when correct, otherwise the message to show.
 */
function check_password_with_lockout(array $userRow, string $password, string $wrongMessage): ?string
{
    $key = 'signin:' . $userRow['email'];
    if (rate_limited($key, SIGNIN_MAX)) {
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    if (!password_verify($password, $userRow['password_hash'])) {
        rate_hit($key, SIGNIN_WINDOW);
        return $wrongMessage;
    }
    rate_clear($key);
    if (password_needs_rehash($userRow['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userRow['id']]);
    }
    return null;
}

/**
 * On a live server, the first time an admin opens the dashboard we save the address they used
 * as the site address (for links in emails) — an admin's own browser can't be faked by others.
 */
function remember_site_address(): void
{
    if ((string) config('app_url') !== '' || config_fixed('app_url') || is_local_request() || PHP_SAPI === 'cli') return;
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host)) return;
    db_run("INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = IF(value = '', VALUES(value), value)", ['app_url', request_origin() . base_path(), now_utc()]);
    config('__reset');
}

// ---------- Password reset tokens (only a SHA-256 hash is stored) ----------

function create_reset_token(int $userId): string
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    db_run('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
    db_run(
        'INSERT INTO password_resets (token_hash, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)',
        [hash('sha256', $token), $userId, gmdate('Y-m-d H:i:s', time() + 3600), now_utc()],
    );
    return $token;
}

function find_reset_user(string $token): ?int
{
    if ($token === '') return null;
    $row = db_one(
        'SELECT user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), now_utc()],
    );
    return $row ? (int) $row['user_id'] : null;
}

/** Sets the new password, burns the token and signs the user out everywhere. */
function consume_reset_token(string $token, string $newPassword): ?int
{
    $pdo = db();
    $pdo->beginTransaction();
    $userId = find_reset_user($token);
    if (!$userId) {
        $pdo->rollBack();
        return null;
    }
    db_run('UPDATE password_resets SET used_at = ? WHERE token_hash = ?', [now_utc(), hash('sha256', $token)]);
    db_run(
        'UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
        [password_hash($newPassword, PASSWORD_DEFAULT), $userId],
    );
    $pdo->commit();
    return $userId;
}
