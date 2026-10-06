<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// List of public pages for Google and other search engines.
$https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$host = preg_match('/^[a-z0-9.\-:]+$/i', (string) ($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'lamazonloads.com';
$root = ($https ? 'https://' : 'http://') . $host;
$pages = ['', 'services.php', 'drivers.php', 'careers.php', 'about.php', 'partners.php', 'last-mile-delivery.php', 'healthcare-delivery.php', 'dedicated-fleet.php', 'faq.php', 'contact.php', 'register.php', 'privacy.php'];
if (network_enabled()) {
    $pages[] = 'job.php?id=0';
}
foreach (db_all("SELECT id FROM jobs WHERE status = 'open'") as $j) {
    $pages[] = 'job.php?id=' . (int) $j['id'];
}
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p) {
    echo '  <url><loc>' . e($root . url($p)) . "</loc></url>\n";
}
echo "</urlset>\n";
