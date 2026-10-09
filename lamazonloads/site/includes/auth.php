<?php
declare(strict_types=1);
defined('LL_APP') || exit;

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = (int) ($_SESSION['uid'] ?? 0);
    $user = $id ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    // Signing out everywhere (or a password change) bumps session_version.
    if ($user && (int) $user['session_version'] !== (int) ($_SESSION['sv'] ?? 0)) {
        unset($_SESSION['uid'], $_SESSION['sv']);
        $user = null;
    }
    // Signed out after 12 hours without activity, and 14 days after signing in, even if the session is kept alive
    $now = time();
    if ($user && ($now - (int) ($_SESSION['seen'] ?? $now) > 43200 || $now - (int) ($_SESSION['login_at'] ?? $now) > 1209600)) {
        $_SESSION = [];
        $user = null;
    } elseif ($user) {
        if (!defined('LL_PASSIVE')) { // background checks (admin/live.php) don't count as activity
            $_SESSION['seen'] = $now;
        }
        $_SESSION['login_at'] ??= $now;
    }
    return $user;
}

function is_admin(): bool
{
    return (bool) (current_user()['is_admin'] ?? false);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $here = ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/');
        flash('info', 'Please sign in or create an account to continue.');
        redirect('login.php?next=' . rawurlencode($here));
    }
    if (!empty($u['must_change_password']) && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'set-password.php') {
        $here = ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/');
        redirect('set-password.php?next=' . rawurlencode($here));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (!$u['is_admin']) {
        http_response_code(403);
        page_header('Not allowed');
        echo '<section class="section"><div class="container narrow"><div class="card pad"><h1>Staff only</h1><p>This page is for LamazonLoads staff.</p></div></div></section>';
        page_footer();
        exit;
    }
    return $u;
}

/**
 * Staff roles. Admins run everything. Moderators handle the inbox, hiring (applications, onboarding review),
 * members and job posts / Walmart cities, but can't delete anything, change site settings or give staff access.
 */
const STAFF_ROLES = ['admin' => 'Admin', 'moderator' => 'Moderator'];

/** 'admin', 'moderator', or '' for members. Staff count as admins only when their role says so explicitly. */
function staff_role(?array $u = null): string
{
    $u ??= current_user();
    if (!$u || empty($u['is_admin'])) {
        return '';
    }
    return ($u['staff_role'] ?? '') === 'admin' ? 'admin' : 'moderator';
}

function is_full_admin(?array $u = null): bool
{
    return staff_role($u) === 'admin';
}

/** Admin-only pages (contracts, site photos, email check). */
function require_full_admin(): array
{
    $u = require_admin();
    if (!is_full_admin($u)) {
        http_response_code(403);
        page_header('Admins only');
        echo '<section class="section"><div class="container narrow"><div class="card pad ss ss-card"><div class="ss-ico is-warn" aria-hidden="true">' . icon('shield') . '</div>'
            . '<h1 class="ss-title">Admins only</h1><p class="ss-lead">This tool is for LamazonLoads admins. Ask an admin if something here needs changing.</p>'
            . '<div class="ss-acts ss-acts-inline"><a class="btn btn-primary" href="' . e(url('admin/')) . '">Back to Overview</a></div></div></div></section>';
        page_footer();
        exit;
    }
    return $u;
}

/** Admin-only actions on pages moderators can use (deleting, settings): moderators are sent back with a note. */
function require_full_admin_action(string $back): void
{
    if (!is_full_admin()) {
        flash('error', 'Only admins can do that. Ask an admin if it needs doing.');
        redirect($back);
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf']); // a fresh form token for the signed-in session
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['sv'] = (int) $user['session_version'];
    $_SESSION['login_at'] = $_SESSION['seen'] = time();
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    // A chat started on this browser before signing in stays private on shared computers
    if (isset($_COOKIE['ll_chat'])) {
        setcookie('ll_chat', '', ['expires' => time() - 3600, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}

/**
 * Too many wrong passwords in 15 minutes: 8 for this email from this connection, 25 from this connection in total,
 * or 40 for this email from everywhere (a spread-out attack). Strangers can't lock an account with just a few tries.
 */
function login_locked(string $email): bool
{
    db_run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    $ip = limit_ip();
    $pair = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$email, $ip]);
    $byIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]);
    $byEmail = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$email]);
    return $pair >= 8 || $byIp >= 25 || $byEmail >= 40;
}

function record_failed_login(string $email): void
{
    db_run('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, NOW())', [limit_ip(), $email]);
}

/**
 * Passwords bcrypt can't store safely: anything after 72 bytes would be ignored, and a NUL byte isn't allowed.
 * Returns a message, or '' when it's fine.
 */
function password_format_problem(string $pass): string
{
    if (str_contains($pass, "\0")) return 'Your password has a character we can’t use. Please choose another.';
    if (strlen($pass) > 72) return 'Please keep your password to 72 characters or fewer.';
    return '';
}

/**
 * The very first admin: someone whose email is listed in admin_emails (config.local.php) becomes admin only after
 * confirming that email with the link we send, and only while the site has no admin yet. Everyone after that gets
 * staff access from an admin in Admin → Drivers & members.
 */
function promote_first_admin(array $u): bool
{
    if (!empty($u['is_admin']) || !should_be_admin((string) $u['email']) || empty($u['email_verified_at'])) {
        return false;
    }
    if ((int) db_val("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND staff_role = 'admin'") > 0) {
        return false;
    }
    db_run("UPDATE users SET is_admin = 1, staff_role = 'admin', session_version = session_version + 1 WHERE id = ?", [$u['id']]);
    return true;
}

/** Emails listed in admin_emails (config.local.php): the owner's accounts, which other admins can't demote or delete. */
function should_be_admin(string $email): bool
{
    $list = array_filter(array_map('trim', explode(',', strtolower((string) config('admin_emails')))));
    return in_array(strtolower($email), $list, true);
}

/** How far along a member is with onboarding, for the dashboard checklist. */
function onboarding_steps(int $userId): array
{
    $p = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$userId]);
    $kinds = db_all('SELECT DISTINCT kind FROM documents WHERE user_id = ?', [$userId]);
    $kinds = array_column($kinds, 'kind');
    $apps = (int) db_val('SELECT COUNT(*) FROM applications WHERE user_id = ?', [$userId]);
    return [
        ['Account created', true, 'account.php'],
        ['Driver profile: equipment, ZIP code & availability', $p && $p['equipment'] !== '' && $p['home_zip'] !== '' && $p['availability'] !== '', 'profile.php'],
        ['W-9 uploaded', in_array('w9', $kinds, true), 'documents.php'],
        ['Insurance (COI) uploaded', in_array('insurance', $kinds, true), 'documents.php'],
        ["Driver's license uploaded", in_array('license', $kinds, true), 'documents.php'],
        ['Applied to the network or a job post', $apps > 0, 'careers.php'],
    ];
}

/** Passwords that are on every attacker's list, or that contain the person's own email or name. */
function weak_password(string $pass, string $email = '', string $name = ''): bool
{
    $p = strtolower($pass);
    $common = ['password', 'password1', 'password123', '12345678', '123456789', '1234567890', '11111111', '00000000', 'qwerty123',
        'qwertyuiop', 'iloveyou', 'sunshine', 'princess', 'football', 'baseball', 'welcome1', 'abc12345', 'letmein1', 'trustno1',
        'lamazonloads', 'lamazon123', 'truck123', 'trucking', 'dispatch', 'freight1', 'password!', 'admin123', 'changeme'];
    if (in_array($p, $common, true) || preg_match('/^(.)\1+$/', $p) || preg_match('/^(0123456789|123456789|abcdefgh)/', $p)) {
        return true;
    }
    $local = strtolower((string) strtok($email, '@'));
    if (strlen($local) >= 4 && str_contains($p, $local)) {
        return true;
    }
    foreach (preg_split('/\s+/', strtolower($name)) ?: [] as $part) {
        if (strlen($part) >= 4 && $p === $part . preg_replace('/\D/', '', $p) && $p !== $part) {
            return true; // name + digits, e.g. marcus2024
        }
    }
    return false;
}

/* ---------- Email confirmation ---------- */

const VERIFY_HOURS = 48; // the link in the email works this long

/** Members must confirm their email before applying or uploading. Staff never need to (so nobody gets locked out). */
function is_verified(?array $u): bool
{
    return $u && ($u['is_admin'] || !empty($u['email_verified_at']));
}

/** Like require_login(), but unconfirmed members are sent to the "Check your email" page. */
function require_verified(): array
{
    $u = require_login();
    if (!is_verified($u)) {
        redirect('verify.php');
    }
    return $u;
}

/** Emails a fresh confirmation link. Returns false if the email couldn't be sent. */
function send_verification(array $u): bool
{
    $raw = bin2hex(random_bytes(32));
    db_run('UPDATE users SET verify_token = ?, verify_expires = NOW() + INTERVAL ' . VERIFY_HOURS . ' HOUR, verify_sent_at = NOW(), verify_prev = NULL, verify_prev_expires = NULL WHERE id = ?',
        [hash('sha256', $raw), $u['id']]);
    $first = first_name((string) $u['name']);
    [$text, $html] = email_body('Confirm your email', [
        "Hi $first,",
        'Thanks for creating your LamazonLoads account. Please confirm your email address so we can reach you about loads, daily routes and job openings.',
    ], 'Confirm my email', abs_url('verify.php?t=' . $raw),
        'This link works for ' . VERIFY_HOURS . " hours. If you didn't create a LamazonLoads account, you can ignore this email.");
    return send_mail((string) $u['email'], 'Confirm your email for LamazonLoads', $text, $html, support_email());
}

const RESET_MINUTES = 60;

/** Emails a one-time "choose a new password" link (works for an hour; only its hash is stored). */
function send_password_reset(array $u): bool
{
    $raw = bin2hex(random_bytes(32));
    db_run('UPDATE users SET reset_token = ?, reset_expires = NOW() + INTERVAL ' . RESET_MINUTES . ' MINUTE, reset_sent_at = NOW() WHERE id = ?',
        [hash('sha256', $raw), $u['id']]);
    $first = first_name((string) $u['name']);
    [$text, $html] = email_body('Reset your password', [
        "Hi $first,",
        'We got a request to reset the password for your LamazonLoads account. Click the button below to choose a new one.',
    ], 'Choose a new password', abs_url('reset-password.php?t=' . $raw),
        'This link works for 1 hour and can only be used once. If you didn’t ask for this, you can ignore this email: your password stays the same.');
    return send_mail((string) $u['email'], 'Reset your LamazonLoads password', $text, $html, support_email());
}

/** The member a reset link belongs to, or null if it is wrong, used or expired. */
function user_by_reset_token(string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }
    return db_one('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()', [hash('sha256', $raw)]);
}

