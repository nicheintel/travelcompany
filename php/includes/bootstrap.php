<?php
declare(strict_types=1);

// Loaded at the top of every page.
const TC_APP = true; // the other files in includes/ refuse to run without this
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
// Never show error details to visitors (they can reveal paths and settings); errors go to the log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('expose_php', '0');

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/data.php';
require __DIR__ . '/sample.php';
require __DIR__ . '/search.php';
require __DIR__ . '/quote.php';
require __DIR__ . '/bookings.php';
require __DIR__ . '/packages.php';
require __DIR__ . '/email.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/ui.php';

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

    // Live site with an https address: always use https (on the address the visitor typed).
    $site = (string) config('app_url');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (str_starts_with($site, 'https://') && !request_is_https() && !is_local_request()
        && preg_match('/^[a-z0-9.-]+$/', $host) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header("Location: https://$host" . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name(request_is_https() ? '__Secure-tc_session' : 'tc_session');
    session_set_cookie_params([
        'lifetime' => 30 * 86400,
        'path' => base_path() . '/',
        'httponly' => true,
        'secure' => request_is_https(),
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (30 * 86400));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_start();
}

// Security headers
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    if (request_is_https() && !is_local_request()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    // Content Security Policy: the browser only runs our own scripts, so injected code can't run.
    // Forms may only post to this site; after posting, the payment page sends you to PayPal/Stripe.
    $formTargets = ["'self'", 'https://www.paypal.com', 'https://www.sandbox.paypal.com', 'https://checkout.stripe.com'];
    foreach (['paypal_api_base', 'stripe_api_base'] as $testBase) { // fake payment servers in automated tests
        $u = parse_url((string) config($testBase));
        if (!empty($u['host']) && !in_array($u['host'], ['api.stripe.com', 'api-m.paypal.com', 'api-m.sandbox.paypal.com'], true)) {
            $formTargets[] = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        }
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        . 'form-action ' . implode(' ', $formTargets) . "; frame-ancestors 'none'"
        . (request_is_https() ? '; upgrade-insecure-requests' : ''));
}
