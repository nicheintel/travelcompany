<?php
declare(strict_types=1);

// Loaded at the top of every page.
const LL_APP = true; // the other files in includes/ refuse to run without this
date_default_timezone_set('America/New_York');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/emailcheck.php';
require __DIR__ . '/chat.php';
require __DIR__ . '/jobs.php';
require __DIR__ . '/partners.php';
require __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli') {
    // Log error details, show visitors a friendly message.
    set_exception_handler(function (Throwable $e): void {
        error_log('[error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
            . '<p style="font-family:sans-serif;margin:3rem auto;max-width:32rem">Sorry, something went wrong on our side. Please try again in a moment.</p>';
    });
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    // Content Security Policy: only our own scripts run, nothing can be loaded from or sent to other sites
    // (except Google Fonts), and no other site can show our pages in a frame.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    session_name('ll_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}
