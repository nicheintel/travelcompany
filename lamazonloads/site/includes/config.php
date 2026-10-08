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
            'contact_email' => 'info@lamazonloads.com',
            'contact_phone' => '(678) 528-1181',
            'max_upload_mb' => 8,
            // Sign-up email rules
            'block_disposable_emails' => true,  // no temporary / disposable email addresses
            'allowed_email_domains'   => '',    // always allow these domains (comma separated)
            'blocked_email_domains'   => '',    // also block these domains (comma separated)
            // Email (chat notifications). With smtp_pass set, mail goes through your Hostinger mailbox;
            // otherwise PHP's mail(). 'file' saves emails in storage/mail/ instead of sending (testing).
            'app_url'        => 'https://lamazonloads.com',
            'support_email'  => '',          // where chat alerts go; empty = contact_email
            'mail_from'      => '',          // sender address; empty = smtp_user or contact_email
            'mail_transport' => '',          // '' = automatic, or 'smtp', 'mail', 'file'
            'smtp_host'      => 'smtp.hostinger.com',
            'smtp_port'      => 465,
            'smtp_secure'    => 'ssl',       // 'ssl' (port 465) or 'tls' (port 587)
            'smtp_user'      => '',
            'smtp_pass'      => '',
        ];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $over = require $local;
            if (is_array($over)) {
                // Settings files made before October 2026 still hold the old phone number: the current one replaces it
                if (preg_replace('/\D/', '', (string) ($over['contact_phone'] ?? '')) === '6786664334') unset($over['contact_phone']);
                $c = array_merge($c, $over);
            }
        }
    }
    return $c[$key] ?? null;
}
