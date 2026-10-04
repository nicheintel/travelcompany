<?php
/**
 * Settings. Don't put passwords or API keys in this file — copy
 * config.local.example.php to config.local.php (never uploaded to GitHub)
 * or set environment variables on your host (same names, upper-case).
 */
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    static $settings = null;
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

            // Live suppliers — empty = sample data
            'duffel_access_token' => '',
            'liteapi_key' => '',

            // Email (Resend). Empty = emails are written to storage/emails.log
            'resend_api_key' => '',
            'email_from' => '',

            // Stripe card payments. Empty = "reserve now, pay later" only
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',

            // For automated tests only
            'duffel_api_base' => 'https://api.duffel.com',
            'liteapi_api_base' => 'https://api.liteapi.travel/v3.0',
            'stripe_api_base' => 'https://api.stripe.com',
        ];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $settings = array_merge($settings, (array) require $local);
        }
        foreach (array_keys($settings) as $name) {
            $env = getenv(strtoupper($name));
            if ($env !== false && $env !== '') {
                $settings[$name] = is_float($settings[$name]) ? (float) $env
                    : (is_int($settings[$name]) ? (int) $env
                    : (is_array($settings[$name]) ? (json_decode($env, true) ?: $settings[$name]) : $env));
            }
        }
    }
    return $settings[$key] ?? $default;
}
