<?php
/**
 * Settings, from (highest priority first): environment variables (same names, upper-case),
 * config.local.php (never uploaded to GitHub), Admin → Site settings (stored in the
 * database), then the defaults below. Don't put passwords or API keys in this file.
 */
declare(strict_types=1);

/** Settings admins can change on the Admin → Site settings page. */
const EDITABLE_SETTINGS = [
    'admin_emails', 'flight_markup_rate', 'hotel_markup_rate', 'member_discount_rate',
    'duffel_access_token', 'liteapi_key', 'resend_api_key', 'email_from',
    'stripe_secret_key', 'stripe_webhook_secret',
];

function config(string $key, mixed $default = null): mixed
{
    static $settings = null;
    static $dbLoaded = false;
    if ($key === '__reset') {
        $settings = null;
        $dbLoaded = false;
        return null;
    }
    if ($settings === null) {
        $settings = [
            // Database — XAMPP defaults. The database is created automatically if it doesn't exist.
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'travelcompany',
            'db_user' => 'root',
            'db_pass' => '',

            // Leave empty to detect automatically (e.g. http://localhost/travelcompany).
            // Set it on a live server, e.g. https://www.yourdomain.com — reset-password links use it.
            'app_url' => '',

            'site_name' => 'TravelCompany',

            // Comma-separated emails that are always admins.
            'admin_emails' => '',

            // Pricing
            'flight_markup_rate' => 0.20,   // +20% on supplier flight prices
            'hotel_markup_rate' => 0.20,    // +20% on supplier hotel prices
            'member_discount_rate' => 0.10, // members' discount on bookings (0 = off)
            'fx_rates_to_usd' => ['USD' => 1, 'EUR' => 1.08, 'GBP' => 1.27, 'CAD' => 0.73, 'AUD' => 0.66, 'SGD' => 0.75, 'PHP' => 0.0175, 'JPY' => 0.0067],

            // Live suppliers. Without a key, search says "not connected" — no invented results.
            'duffel_access_token' => '',
            'liteapi_key' => '',

            // Email (Resend). Empty = emails are written to storage/emails.log
            'resend_api_key' => '',
            'email_from' => '',

            // Stripe card payments. Empty = "reserve now, pay later" only
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',

            // true = show made-up SAMPLE flights/hotels when no supplier key is set (demos only,
            // never for real customers). Leave false.
            'demo_mode' => false,

            // For automated tests only
            'duffel_api_base' => 'https://api.duffel.com',
            'liteapi_api_base' => 'https://api.liteapi.travel/v3.0',
            'stripe_api_base' => 'https://api.stripe.com',
        ];
        $fixed = [];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $fromFile = (array) require $local;
            $settings = array_merge($settings, $fromFile);
            $fixed = array_keys($fromFile);
        }
        foreach (array_keys($settings) as $name) {
            $env = getenv(strtoupper($name));
            if ($env !== false && $env !== '') {
                $fixed[] = $name;
                $settings[$name] = is_float($settings[$name]) ? (float) $env
                    : (is_int($settings[$name]) ? (int) $env
                    : (is_array($settings[$name]) ? (json_decode($env, true) ?: $settings[$name]) : $env));
            }
        }
        $settings['__fixed'] = array_values(array_unique($fixed));
    }
    // Values saved on Admin → Site settings (loaded on first use; the file and env still win).
    if (!$dbLoaded && in_array($key, EDITABLE_SETTINGS, true) && function_exists('db_all')) {
        $dbLoaded = true;
        foreach (db_all('SELECT name, value FROM settings') as $row) {
            $name = $row['name'];
            if (in_array($name, EDITABLE_SETTINGS, true) && !in_array($name, $settings['__fixed'], true)) {
                $settings[$name] = is_float($settings[$name]) ? (float) $row['value'] : $row['value'];
            }
        }
    }
    return $settings[$key] ?? $default;
}

/** True when a setting comes from config.local.php or an environment variable (not editable on the site). */
function config_fixed(string $key): bool
{
    return in_array($key, (array) config('__fixed'), true);
}
