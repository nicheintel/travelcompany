<?php
declare(strict_types=1);
// Pages linked from the menu, footer and buttons start loading when the pointer rests on the link,
// so they open almost instantly. Sent to browsers through the Speculation-Rules header.
header('Content-Type: application/speculationrules+json');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo json_encode(['prefetch' => [['where' => ['selector_matches' => '.site-nav a, .site-footer a, a.btn:not([href*="doc.php"]), .jc-title a, a.sol-top, a.more-sol-card, a.biz-item, .crumbs a'], 'eagerness' => 'moderate']]]);
