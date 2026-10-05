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
        flash('info', 'Please sign in or create a free account to continue.');
        redirect('login.php?next=' . rawurlencode($here));
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

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['sv'] = (int) $user['session_version'];
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Too many wrong passwords in 15 minutes for this email or from this address. */
function login_locked(string $email): bool
{
    db_run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    $byEmail = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$email]);
    $byIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [client_ip()]);
    return $byEmail >= 8 || $byIp >= 25;
}

function record_failed_login(string $email): void
{
    db_run('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, NOW())', [client_ip(), $email]);
}

/** New accounts become staff when listed in admin_emails, or — on your own computer — when the site has no admin yet. */
function should_be_admin(string $email): bool
{
    $list = array_filter(array_map('trim', explode(',', strtolower((string) config('admin_emails')))));
    if (in_array(strtolower($email), $list, true)) {
        return true;
    }
    return is_local_request() && !db_val('SELECT COUNT(*) FROM users WHERE is_admin = 1');
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
