<?php
/**
 * Two kinds of settings:
 *
 * 1. config() — technical settings: database login, site address, setup key. From (highest
 *    priority first) environment variables (same names, upper-case), config.local.php (never
 *    uploaded to GitHub), then the defaults below.
 *
 * 2. setting() — what the website says: brand name, headline, contact details, currency…
 *    Changed on Admin → Settings (saved in the database); the defaults are in SITE_DEFAULTS.
 */
declare(strict_types=1);
defined('WS_APP') || exit;

function config(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = [
            // Database — XAMPP defaults. The database is created automatically if it doesn't exist.
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'webstudio',
            'db_user' => 'root',
            'db_pass' => '',

            // Your site's address on a live server, e.g. https://www.yourdomain.com
            // Empty = worked out automatically on your own computer (XAMPP).
            'app_url' => '',

            // Creating the first admin account from the internet (not localhost) needs this key:
            // open /admin/setup.php?key=THE-KEY. Not needed on XAMPP.
            'setup_key' => '',
        ];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            // Read the file fresh after you edit it (PHP's cache would keep the old copy for a while).
            if (function_exists('opcache_invalidate')) @opcache_invalidate($local);
            $settings = array_merge($settings, (array) require $local);
        }
        foreach (array_keys($settings) as $name) {
            $env = getenv('WS_' . strtoupper($name));
            if ($env !== false && $env !== '') {
                $settings[$name] = is_int($settings[$name]) ? (int) $env : $env;
            }
        }
    }
    return $settings[$key] ?? $default;
}

/** What the website says. Every one of these can be changed on Admin → Settings. */
const SITE_DEFAULTS = [
    // Brand
    'brand_name' => 'IdeaCraft Studio',
    'tagline' => 'Affordable websites for growing businesses',
    // Put *stars* around words to color them orange.
    'hero_title' => "Let's turn your ideas into a *website*.",
    'hero_text' => 'Professional, mobile-friendly websites for small businesses — designed, built and launched for you at a price that makes sense. No tech headaches. No hidden fees.',

    // Contact (empty = hidden on the site)
    'contact_email' => '',
    'contact_phone' => '',
    'whatsapp' => '',
    'messenger_url' => '',
    'facebook_url' => '',
    'instagram_url' => '',
    'tiktok_url' => '',
    'location' => 'Serving businesses nationwide and worldwide',
    'business_hours' => 'Mon–Sat, 9:00 AM – 6:00 PM',
    'reply_time' => '24 hours',

    // Pricing
    'currency' => '₱',
    'included_in_all' => "Free consultation\nMobile-friendly design\nContact form & Google Maps\nBasic SEO setup\nSSL security setup\n30 days of free support",

    // Times on the dashboard
    'timezone' => 'Asia/Manila',
];

function setting(string $key): string
{
    static $saved = null;
    if ($key === '__reset') {
        $saved = null;
        return '';
    }
    if ($saved === null) {
        $saved = [];
        foreach (db_all('SELECT name, value FROM settings') as $row) {
            $saved[$row['name']] = (string) $row['value'];
        }
    }
    return $saved[$key] ?? (string) (SITE_DEFAULTS[$key] ?? '');
}

function save_setting(string $key, string $value): void
{
    db_run(
        'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
        [$key, $value, now_utc()],
    );
    setting('__reset');
}
