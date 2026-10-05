<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/**
 * Settings. Defaults work on a fresh XAMPP install (MySQL user "root", no password).
 * To change anything, copy config.local.example.php to config.local.php and edit that file.
 */
function config(string $key): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [
            'site_name'     => 'LamazonLoads',
            'motto'         => "Why Wait? Let's Freight.",
            'db_host'       => '127.0.0.1',
            'db_port'       => 3306,
            'db_name'       => 'lamazonloads',
            'db_user'       => 'root',
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
