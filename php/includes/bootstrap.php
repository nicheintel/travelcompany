<?php
declare(strict_types=1);

// Loaded at the top of every page.
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/data.php';
require __DIR__ . '/sample.php';
require __DIR__ . '/search.php';
require __DIR__ . '/quote.php';
require __DIR__ . '/bookings.php';
require __DIR__ . '/email.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/ui.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('tc_session');
    session_set_cookie_params([
        'lifetime' => 30 * 86400,
        'path' => base_path() . '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (30 * 86400));
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Basic hardening headers
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}
