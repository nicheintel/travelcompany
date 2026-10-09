<?php
/**
 * Settings, from (highest priority first): environment variables (same names, upper-case),
 * config.local.php (never uploaded to GitHub), Admin → Site settings (stored in the
 * database), then the defaults below. Don't put passwords or API keys in this file.
 */
declare(strict_types=1);
defined('TC_APP') || exit;

/** Settings admins can change on the Admin → Site settings page. */
const EDITABLE_SETTINGS = [
    'app_url', 'admin_emails', 'flight_markup_rate', 'hotel_markup_rate', 'member_discount_rate', 'travel_care_rate', 'change_service_fee',
    'flight_supplier', 'duffel_access_token', 'liteapi_key', 'resend_api_key', 'email_from',
    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
    'support_email', 'support_phone', 'support_whatsapp', 'business_name', 'business_address',
    'stripe_secret_key', 'stripe_webhook_secret',
    'paypal_client_id', 'paypal_secret', 'paypal_mode', 'paypal_webhook_id',
    'gcash_name', 'gcash_number', 'chat_name',
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

            // Your site's address, e.g. https://www.yourdomain.com — links in emails use it.
            // Empty = detected on your own computer; on a live server it's saved the first time an
            // admin opens the dashboard (or set it on Admin → Site settings).
            'app_url' => '',

            'site_name' => 'FareFinders',

            // Comma-separated emails that are always admins.
            'admin_emails' => '',

            // Pricing
            'flight_markup_rate' => 0.20,   // +20% on supplier flight prices
            'hotel_markup_rate' => 0.20,    // +20% on supplier hotel prices
            'member_discount_rate' => 0.10, // members' discount on bookings (0 = off)
            'travel_care_rate' => 0.25,     // Travel Care Protection on flights: share of the ticket price (0 = not offered)
            'change_service_fee' => 50,     // our own fee (USD) per change or cancellation; waived with Travel Care
            // Real daily exchange rates for showing prices in other currencies ('' = US dollars only).
            'fx_api_url' => 'https://open.er-api.com/v6/latest/USD',
            'fx_backup_url' => 'https://api.frankfurter.dev/v1/latest?base=USD',
            'fx_rates_to_usd' => ['USD' => 1, 'EUR' => 1.08, 'GBP' => 1.27, 'CAD' => 0.73, 'AUD' => 0.66, 'SGD' => 0.75, 'PHP' => 0.0175, 'JPY' => 0.0067],

            // Live suppliers. Without a key, search says "not connected" — no invented results.
            'flight_supplier' => 'duffel', // 'duffel' or 'liteapi' (uses the LiteAPI key)
            'duffel_access_token' => '',
            'liteapi_key' => '',

            // Email (Resend). Empty = emails are written to storage/emails.log.php
            'resend_api_key' => '',
            'email_from' => '',
            // Or send through your own mailbox (e.g. Hostinger email): address + password.
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_user' => '',
            'smtp_pass' => '',

            // "Need help?" contact shown to customers. Email defaults to the sending mailbox.
            // GCash (manual): the account customers send pesos to. Empty = GCash off.
            // Name customers see in the chat ("Angel is typing"). Empty = the first admin's first name.
            'chat_name' => 'Angel',
            'gcash_name' => '',
            'gcash_number' => '',
            'support_email' => '',
            'support_phone' => '',
            'support_whatsapp' => '',

            // Shown on the Terms and Privacy pages once filled in (Admin → Site settings).
            'business_name' => '',
            'business_address' => '',

            // PayPal (PayPal account or card via PayPal). Get the Client ID and Secret from
            // developer.paypal.com → Apps & Credentials. Used instead of Stripe when set.
            'paypal_client_id' => '',
            'paypal_secret' => '',
            'paypal_mode' => 'sandbox', // 'sandbox' for testing, 'live' for real money
            'paypal_webhook_id' => '',  // optional, from your PayPal webhook

            // Stripe card payments. Empty (and no PayPal) = "reserve now, pay later" only
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',

            // true = show made-up SAMPLE flights/hotels when no supplier key is set (demos only,
            // never for real customers). Leave false.
            'demo_mode' => false,

            // For automated tests only
            'duffel_api_base' => 'https://api.duffel.com',
            'liteapi_api_base' => 'https://api.liteapi.travel/v3.0',
            'stripe_api_base' => 'https://api.stripe.com',
            'paypal_api_base' => '', // empty = chosen by paypal_mode
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
