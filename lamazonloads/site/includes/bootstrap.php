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
require __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli') {
    if (is_local_request()) {
        ini_set('display_errors', '1'); // on your own computer, show errors to help fix them
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off');
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
