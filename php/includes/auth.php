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
        'verified' => ($row['email_verified_at'] ?? null) !== null,
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

/** Account, trip and booking pages: customers who haven't confirmed their email yet see the "Check your email"
    screen first, then come back here. Admins are never held up (email may not be set up yet). */
function require_verified_user(): array
{
    $user = require_user();
    if (!$user['verified'] && $user['role'] !== 'admin') {
        redirect(url('verify-email.php', ['next' => current_path_with_query()]));
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
    if (strlen($password) < 8) return t('Password must be at least 8 characters.');
    if (strlen($password) > 128) return t('Password must be 128 characters or fewer.');
    if (!preg_match('/[a-zA-Z]/', $password)) return t('Password must contain at least one letter.');
    if (!preg_match('/[0-9]/', $password)) return t('Password must contain at least one number.');
    return null;
}

function name_problem(string $name): ?string
{
    $len = mb_strlen($name);
    if ($len < 2) return t('Please enter your full name.');
    if ($len > 60) return t('Name must be 60 characters or fewer.');
    return null;
}

function valid_email(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * A likely typo in the part after the @ (gmail.con, gmial.com, yahoo.co, hotmal.com…), so the confirmation
 * link and tickets would never arrive. Returns the corrected address to suggest, or null when it looks fine.
 */
function email_typo(string $email): ?string
{
    $at = strrpos($email, '@');
    if ($at === false) return null;
    $domain = strtolower(substr($email, $at + 1));
    $real = ['gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'me.com', 'mail.com', 'email.com', 'gmx.com', 'cloud.com'];
    if (in_array($domain, $real, true)) return null;
    $parts = explode('.', $domain);
    $last = array_pop($parts);
    // No website address ends in .con, .cmo, .comm… — these are always a slip for .com.
    $end = in_array($last, ['con', 'cmo', 'ocm', 'comm', 'coom', 'cpm', 'vom', 'xom', 'cim', 'clm', 'cmm', 'conm', 'comn'], true) ? 'com' : $last;
    // The big email providers: one letter missing, extra, wrong or swapped (two for the longer names).
    if (count($parts) === 1 && in_array($end, ['com', 'co', 'cm', 'om'], true)) {
        foreach (['gmail', 'yahoo', 'hotmail', 'outlook', 'icloud'] as $provider) {
            if (email_typo_near($parts[0], $provider, strlen($provider) >= 7 ? 2 : 1)) return substr($email, 0, $at + 1) . "$provider.com";
        }
    }
    return $end === $last ? null : substr($email, 0, $at + 1) . implode('.', [...$parts, $end]);
}

/** Whether $a is at most $max typing slips away from $b (two swapped letters count as one). */
function email_typo_near(string $a, string $b, int $max): bool
{
    $d = levenshtein($a, $b);
    if ($d === 2 && strlen($a) === strlen($b)) {
        for ($i = 0; $a[$i] === $b[$i]; $i++);
        if ($i < strlen($a) - 1 && $a[$i] === $b[$i + 1] && $a[$i + 1] === $b[$i] && substr($a, $i + 2) === substr($b, $i + 2)) $d = 1;
    }
    return $d <= $max;
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
        return t('Too many failed attempts. Please wait 15 minutes and try again.');
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
    // Opening the reset link also proves the email address works.
    db_run(
        'UPDATE users SET password_hash = ?, session_version = session_version + 1, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
        [password_hash($newPassword, PASSWORD_DEFAULT), now_utc(), $userId],
    );
    $pdo->commit();
    return $userId;
}

// ---------- Email confirmation ----------

/** Emails a link that confirms the user's current email address (valid 48 hours). */
function send_verification_email(array $user): void
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    db_run('DELETE FROM email_verifications WHERE user_id = ?', [$user['id']]);
    db_run(
        'INSERT INTO email_verifications (token_hash, user_id, email, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [hash('sha256', $token), $user['id'], $user['email'], gmdate('Y-m-d H:i:s', time() + 48 * 3600), now_utc()],
    );
    send_email($user['email'], simple_email(
        'Confirm your email for ' . config('site_name'),
        'Confirm your email address',
        explode(' ', $user['name'])[0],
        ['Please confirm this is your email address. We send your booking confirmations, payment links and tickets here.', 'This link expires in 48 hours.'],
        [],
        account_link('verify-email.php?token=' . $token),
        'Confirm my email',
    ));
}

/** Changes the sign-in email after checking the password: a confirmation link goes to the new address and,
    if the old one was confirmed, a heads-up goes there. Returns problems by field ('email', 'password'); empty = done. */
function change_email(array $user, string $email, string $password): array
{
    $email = normalize_email($email);
    if (!valid_email($email)) return ['email' => t('Please enter a valid email address.')];
    if ($email === $user['email']) return ['email' => t("That's already your email address.")];
    if (($fix = email_typo($email)) !== null) return ['email' => t('Check the spelling. Did you mean {email}?', ['email' => $fix])];
    $row = db_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
    if ($err = check_password_with_lockout($row, $password, t('Your current password is incorrect.'))) return ['password' => $err];
    if (find_user_by_email($email)) return ['email' => t('Another account already uses this email.')];
    if (rate_limited('email-change:' . $user['id'], 5)) return ['email' => t('Too many changes. Please try again in an hour.')];
    rate_hit('email-change:' . $user['id'], 3600);
    db_run('UPDATE users SET email = ?, email_verified_at = NULL WHERE id = ?', [$email, $user['id']]);
    send_verification_email(['email' => $email] + $user);
    // Tell the old address, so a hijacked account doesn't go unnoticed.
    if ($user['verified']) {
        send_email($user['email'], simple_email('Your ' . config('site_name') . ' email address was changed', 'Your email address was changed', explode(' ', $user['name'])[0], [
            "The email address on your account was changed to $email. Future emails will go there.",
            "If this wasn't you, reset your password right away and contact our support team.",
        ], [], account_link('forgot-password.php'), 'Reset password'));
    }
    return [];
}

/** Confirms the email the link was sent to (if the account still uses it). Returns the user id or null. */
function confirm_email_token(string $token): ?int
{
    if ($token === '' || strlen($token) > 100) return null;
    $row = db_one(
        'SELECT v.user_id, v.email FROM email_verifications v JOIN users u ON u.id = v.user_id
         WHERE v.token_hash = ? AND v.used_at IS NULL AND v.expires_at > ? AND u.email = v.email',
        [hash('sha256', $token), now_utc()],
    );
    if (!$row) return null;
    db_run('UPDATE email_verifications SET used_at = ? WHERE token_hash = ?', [now_utc(), hash('sha256', $token)]);
    db_run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now_utc(), $row['user_id']]);
    return (int) $row['user_id'];
}

// ---------- Deleting an account ----------

const DELETED_EMAIL_DOMAIN = '@deleted.invalid';

function is_deleted_account(array $user): bool
{
    return str_ends_with((string) ($user['email'] ?? ''), DELETED_EMAIL_DOMAIN);
}

/**
 * Deletes an account with its chats and email links. Bookings are business and tax records the law makes us keep,
 * so an account that has bookings is emptied instead: its name and email are removed, it can never sign in again,
 * and the email address is free for a new account.
 */
function delete_account(array $user): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_run('DELETE FROM support_threads WHERE user_id = ?', [$user['id']]); // their messages go with them
        db_run('DELETE FROM email_verifications WHERE user_id = ?', [$user['id']]);
        db_run('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        if (db_one('SELECT 1 FROM bookings WHERE user_id = ? LIMIT 1', [$user['id']])) {
            db_run(
                "UPDATE users SET name = 'Deleted account', email = ?, password_hash = ?, role = 'customer', session_version = session_version + 1, email_verified_at = NULL WHERE id = ?",
                ['deleted-' . $user['id'] . DELETED_EMAIL_DOMAIN, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $user['id']],
            );
        } else {
            db_run('DELETE FROM users WHERE id = ?', [$user['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
