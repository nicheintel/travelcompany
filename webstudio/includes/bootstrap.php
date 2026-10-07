<?php
declare(strict_types=1);

// Loaded at the top of every page.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
        . '<h1>This website needs PHP 8.1 or newer</h1><p>This server runs PHP ' . PHP_VERSION . '. On Hostinger: hPanel → '
        . '<b>Advanced → PHP Configuration</b> → choose <b>PHP 8.2</b> (or newer) → Update. On XAMPP: install the latest XAMPP.</p>');
}
const WS_APP = true; // the other files in includes/ refuse to run without this
date_default_timezone_set('UTC'); // the database stores UTC; pages show the time zone from Settings
mb_internal_encoding('UTF-8');
// Never show error details to visitors (they can reveal paths and settings); errors go to the log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/content.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/crud.php';

if (PHP_SAPI !== 'cli') {
    if (is_local_request()) {
        ini_set('display_errors', '1'); // on your own computer, show errors to help fix them
    } else {
        set_exception_handler(function (Throwable $e): void {
            error_log('[error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
                . '<p style="font-family:sans-serif;margin:3rem auto;max-width:32rem">Sorry, something went wrong on our side. Please try again in a moment.</p>';
        });
    }
    header_remove('X-Powered-By');

    // Live site with an https address: always use https.
    $site = (string) config('app_url');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (str_starts_with($site, 'https://') && !request_is_https() && !is_local_request()
        && preg_match('/^[a-z0-9.-]+$/', $host) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header("Location: https://$host" . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }

    session_name(request_is_https() ? '__Secure-ws_session' : 'ws_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'httponly' => true,
        'secure' => request_is_https(),
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (12 * 3600));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_start();

    // Security headers. Only this site's own script files can run (no inline scripts anywhere),
    // forms can only post to this site, and pages can't be shown inside other sites (clickjacking).
    // .htaccess sets the same policy too; keep both in step.
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    if (request_is_https() && !is_local_request()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'none'" . (request_is_https() ? '; upgrade-insecure-requests' : ''));
}
