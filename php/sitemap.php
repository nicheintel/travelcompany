<?php
/* List of public pages for search engines (submit https://your-site/sitemap.php in Google Search Console). */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$base = app_url_or_local();
$pages = ['' => '1.0', 'flights.php' => '0.9', 'hotels.php' => '0.9', 'packages.php' => '0.9', 'help.php' => '0.6', 'register.php' => '0.5', 'terms.php' => '0.3', 'privacy.php' => '0.3', 'cookies.php' => '0.2'];
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($pages as $path => $priority) {
    echo '  <url><loc>', htmlspecialchars("$base/$path", ENT_XML1), '</loc><priority>', $priority, "</priority></url>\n";
}
echo "</urlset>\n";
