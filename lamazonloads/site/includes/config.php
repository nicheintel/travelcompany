<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/**
 * Settings. Your real values (database, admin emails, contact details) live in config.local.php,
 * which is made from config.local.example.php. See HOSTINGER.md.
 */
function config(string $key): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [
            'site_name'     => 'LamazonLoads',
            'motto'         => "Why Wait? Let's Freight.",
            'db_host'       => 'localhost',
            'db_port'       => 3306,
            'db_name'       => '',
            'db_user'       => '',
            'db_pass'       => '',
            'admin_emails'  => '',
            'contact_email' => 'dispatch@lamazonloads.com',
            'contact_phone' => '',
            'max_upload_mb' => 8,
        ];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $over = require $local;
            if (is_array($over)) {
                $c = array_merge($c, $over);
            }
        }
    }
    return $c[$key] ?? null;
}
