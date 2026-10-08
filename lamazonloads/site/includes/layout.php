<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/** Simple line icons (24×24, stroke = currentColor). */
function icon(string $name, string $class = 'ic'): string
{
    static $paths = [
        'truck'     => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 4v3h-7z"/><circle cx="7" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>',
        'semi'      => '<path d="M1 5h12v10H1z"/><path d="M13 8.5h4.5L21 12v3h-8z"/><path d="M15.5 8.5V12H21"/><circle cx="4.5" cy="17.5" r="1.8"/><circle cx="9" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
        'van'       => '<path d="M2 7h12l4 4h3v6H2z"/><path d="M14 7v4h4"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/>',
        'route'     => '<circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H17a3.5 3.5 0 0 0 0-7H7a3.5 3.5 0 0 1 0-7h8.5"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h4"/>',
        'headset'   => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20a3 3 0 0 1-3 2h-3"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        'dollar'    => '<path d="M12 2v20"/><path d="M17 6.5c-.8-1.3-2.6-2-5-2-3 0-4.5 1.4-4.5 3.2 0 4.3 9.5 2.3 9.5 7.3 0 1.9-1.8 3.5-5 3.5-2.6 0-4.4-.9-5.2-2.3"/>',
        'check'     => '<path d="M4.5 12.5l5 5 10-11"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6v.01"/>',
        'alert'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.01"/>',
        'arrow'     => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 4.7-3.4 8.3-8 9-4.6-.7-8-4.3-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'file'      => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
        'upload'    => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'car'       => '<path d="M2 17v-5l2.5-5H15l3.5 5H22v5z"/><path d="M2 12h20M10 7v5"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/>',
        'send'      => '<path d="M21.5 3.5L2.8 10.7c-.8.3-.8 1.4 0 1.7l4.7 1.6 1.8 5.6c.2.7 1.1.9 1.6.4l2.6-2.5 4.7 3.5c.6.4 1.4.1 1.6-.6L22.6 4.8c.2-.8-.5-1.5-1.1-1.3z"/><path d="M7.5 14l10-7.2-7.4 8.4"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
        'chev-left' => '<path d="M15 5l-7 7 7 7"/>',
        'chev-right'=> '<path d="M9 5l7 7-7 7"/>',
        'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/>',
        'phone'     => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'handshake' => '<path d="M2 11l4-4 4 2 3-2 4 1 5 3"/><path d="M6 7v6l5 5c.8.8 2 .8 2.8 0l4.7-4.7"/><path d="M10 15l2 2M12 13l2 2"/>',
        'chart'     => '<path d="M4 20V4M4 20h16"/><path d="M8 16l4-5 3 3 5-7"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'chat'      => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'star'      => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'logout'    => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'trash'     => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'download'  => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'package'   => '<path d="M12 2.5l8.5 4.5v10L12 21.5 3.5 17V7z"/><path d="M3.5 7L12 11.5 20.5 7M12 11.5v10M7.8 4.8l8.5 4.6"/>',
        'medical'   => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>',
        'thermo'    => '<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0z"/><path d="M12 9v7"/>',
        'code'      => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>',
        'building'  => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M11 21v-3h2v3"/>',
        'layers'    => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'cart'      => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2.5 3.5h3l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.3a1.5 1.5 0 0 0 1.5-1.2L21 7H6.2"/>',
        'wrench'    => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3.5 17.5a1.8 1.8 0 0 0 2.5 2.5l5.8-5.8a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
        'leaf'      => '<path d="M5 19c0-8 5-13 15-14-1 10-6 15-14 15"/><path d="M5 19l7-7"/>',
        'home'      => '<path d="M3 11l9-7 9 7"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-5h4v5"/>',
        'link'      => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3.2-3.2a4.5 4.5 0 0 0-6.4-6.4L11.6 6"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3.2 3.2a4.5 4.5 0 0 0 6.4 6.4l1.6-1.6"/>',
        'phone-call'=> '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/><path d="M15 3a6 6 0 0 1 6 6M15 7a2 2 0 0 1 2 2"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** "tel:" link for a US phone number written any way, e.g. (678) 528-1181 -> tel:+16785281181. */
function tel_href(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return 'tel:' . (strlen($digits) === 10 ? '+1' . $digits : (str_starts_with($phone, '+') ? '+' : '') . $digits);
}

function logo_html(string $class = ''): string
{
    return '<img class="logo ' . e($class) . '" src="' . e(asset('brand/logo.webp')) . '" alt="LamazonLoads: Why wait? Let\'s freight." width="364" height="204">';
}

function page_header(string $title, string $active = '', string $description = '', string $bodyClass = ''): void
{
    $user = current_user();
    $desc = $description ?: 'LamazonLoads: freight dispatching, daily routes and real driver support for cargo vans, Sprinter vans and box trucks. Built by drivers, for drivers.';
    $nav = [
        'home' => ['', 'Home'],
        'services' => ['services.php', 'Services'],
        'drivers' => ['drivers.php', 'Drive with us'],
        'careers' => ['careers.php', 'Careers'],
        'about' => ['about.php', 'About'],
        'partners' => ['partners.php', 'Partners'],
        'contact' => ['contact.php', 'Contact'],
    ];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title === '' ? "LamazonLoads | Why Wait? Let's Freight." : $title . ' | LamazonLoads') ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0A2463">
<meta property="og:title" content="<?= e($title ?: 'LamazonLoads') ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<?php if (preg_match('/^[a-z0-9.\-:]+$/i', (string) ($_SERVER['HTTP_HOST'] ?? ''))): ?><meta property="og:image" content="<?= e('https://' . $_SERVER['HTTP_HOST'] . asset('brand/logo.png')) ?>"><?php endif; ?>
<?php // Icon addresses never change between uploads (Google asks for a stable favicon URL). To change an icon, give the file a new name. ?>
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="16x16 32x32 48x48">
<link rel="icon" href="<?= e(url('assets/brand/favicon-192.png')) ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(url('assets/brand/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/montserrat-italic-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
<?php if (($pre = preload_photo()) !== null): [$preSet] = photo_set($pre); ?><link rel="preload" as="image" href="<?= e($preSet[0][0]) ?>" imagesrcset="<?= e(implode(', ', array_map(fn ($s) => $s[0] . ' ' . $s[1] . 'w', $preSet))) ?>" imagesizes="<?= e(BANNER_SIZES) ?>" fetchpriority="high">
<?php endif; ?>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<?php $bodyClass = trim($bodyClass . (is_admin_page() ? ' admin-page' : '')); ?>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header" id="top">
  <div class="container header-row">
    <a href="<?= e(url('')) ?>" class="brand" aria-label="LamazonLoads home"><?= logo_html() ?></a>
    <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>
    <nav class="site-nav" id="site-nav" aria-label="Main">
      <ul class="nav-links">
        <?php foreach ($nav as $key => [$href, $label]): ?>
          <li><a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav-actions<?= $user ? ' signed-in' : '' ?>">
        <?php if ((string) config('contact_phone') !== ''): ?><a class="nav-phone" href="<?= e(tel_href((string) config('contact_phone'))) ?>" aria-label="Call LamazonLoads"><?= icon('phone') ?><span class="nav-phone-num"><?= e(config('contact_phone')) ?></span></a><?php endif; ?>
        <?php if ($user): ?>
          <?php if ($user['is_admin']): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/')) ?>">Admin</a><?php endif; ?>
          <a class="btn btn-primary btn-sm" href="<?= e(url('account.php')) ?>"><?= icon('user') ?> My dashboard</a>
        <?php else: ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('login.php')) ?>">Sign in</a>
          <a class="btn btn-accent btn-sm" href="<?= e(url('register.php')) ?>">Get loaded</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<main id="main">
<?php
    $flashes = take_flashes();
    if ($flashes) {
        echo '<div class="toasts" aria-live="polite">';
        foreach ($flashes as $f) {
            $type = in_array($f[0], ['success', 'error', 'info'], true) ? $f[0] : 'info';
            [$title, $text] = toast_parts((string) $f[1]);
            echo '<div class="toast toast-' . $type . ($title === '' || $text === '' ? ' is-solo' : '') . '" role="' . ($type === 'error' ? 'alert' : 'status') . '" data-toast' . (!empty($f[2]) ? ' data-toast-keep' : '') . '>'
                . '<span class="toast-ico">' . icon($type === 'success' ? 'check' : ($type === 'error' ? 'alert' : 'info')) . '</span>'
                . '<span class="toast-body">' . ($title !== '' ? '<b class="toast-title">' . e($title) . '</b>' : '') . ($text !== '' ? '<span class="toast-msg">' . e($text) . '</span>' : '') . '</span>'
                . '<button type="button" class="toast-x" aria-label="Close" data-toast-close><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
                . (empty($f[2]) ? '<span class="toast-bar" aria-hidden="true"></span>' : '') . '</div>';
        }
        echo '</div>';
    }
}

/**
 * Splits a message into a short bold title and the rest: "Application sent! Please check…" becomes
 * "Application sent!" + "Please check…". One short sentence is just a title; one long sentence is just text.
 */
function toast_parts(string $msg): array
{
    if (preg_match('/^(.{2,80}?[.!?])\s+(?=[A-Z0-9“"(])(.+)$/su', $msg, $m)) return [$m[1], $m[2]];
    return mb_strlen($msg) <= 70 ? [$msg, ''] : ['', $msg];
}

/** Pieces of a status screen ("Application sent", "Check your email"…), styled by .ss in style.css. */
function ss_chip(string $text, string $ic = 'mail'): string
{
    return '<span class="ss-chip"><span class="ss-chip-ico">' . icon($ic) . '</span><span>' . e($text) . '</span></span>';
}

/** Numbered steps: [[title, short line under it], ...] */
function ss_steps(array $steps): string
{
    $h = '<ol class="ss-steps">';
    foreach ($steps as [$title, $line]) {
        $h .= '<li><span><b>' . e($title) . '</b>' . ($line !== '' ? '<small>' . e($line) . '</small>' : '') . '</span></li>';
    }
    return $h . '</ol>';
}

/** Small tip line with an icon. $html may contain a link. */
function ss_hint(string $html, string $ic = 'info'): string
{
    return '<p class="ss-hint">' . icon($ic) . '<span>' . $html . '</span></p>';
}

/** "Are you sure?" pop-up for forms with data-confirm (filled in by app.js). */
function confirm_dialog_html(): string
{
    return '<dialog class="cfm" data-cfm aria-labelledby="cfm-title" aria-describedby="cfm-text"><div class="cfm-box">'
        . '<span class="cfm-ico cfm-ico-delete">' . icon('trash') . '</span><span class="cfm-ico cfm-ico-warn">' . icon('alert') . '</span><span class="cfm-ico cfm-ico-info">' . icon('info') . '</span>'
        . '<h2 id="cfm-title" data-cfm-title></h2><p id="cfm-text" data-cfm-text></p>'
        . '<div class="cfm-acts"><button type="button" class="btn btn-ghost" data-cfm-no>Cancel</button><button type="button" class="btn btn-primary" data-cfm-yes>Continue</button></div>'
        . '</div></dialog>';
}

/** The document viewer pop-up (filled in by app.js when a [data-doc-view] link is clicked). */
function doc_viewer_html(): string
{
    $x = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    return '<div class="modal doc-modal" id="docview" data-modal data-modal-param="doc" role="dialog" aria-modal="true" aria-labelledby="dv-title" aria-hidden="true" data-pdfjs="' . e(url('assets/vendor/pdfjs/')) . '">'
        . '<a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>'
        . '<div class="modal-panel">'
        . '<header class="modal-head dv-head"><div class="dv-title"><span class="eyebrow" data-dv-kind>Document</span><h2 id="dv-title" data-dv-name>Document</h2></div>'
        . '<div class="dv-tools"><span class="dv-count" data-dv-count hidden></span>'
        . '<button type="button" class="dv-btn" data-dv-prev aria-label="Previous document" hidden>' . icon('chev-left') . '</button>'
        . '<button type="button" class="dv-btn" data-dv-next aria-label="Next document" hidden>' . icon('chev-right') . '</button>'
        . '<a class="dv-btn" href="#" data-dv-download aria-label="Download" title="Download">' . icon('download') . '</a>'
        . '<a class="dv-btn" href="#" data-dv-tab target="_blank" rel="noopener" aria-label="Open in a new tab" title="Open in a new tab">' . icon('external') . '</a>'
        . '<a class="modal-x" href="#" data-modal-close aria-label="Close">' . $x . '</a></div></header>'
        . '<div class="dv-body" data-dv-body></div>'
        . '</div></div>';
}

function page_footer(): void
{
    run_automations_if_due();
    if (is_admin_page()) { // admin: a slim footer, no chat bubble (staff answer chats in Support chats)
        echo '</main><footer class="admin-foot"><div class="container"><span>&copy; ' . date('Y') . ' LamazonLoads · Admin</span>'
            . '<span><a href="' . e(url('')) . '">View website</a> · <a href="' . e(url('privacy.php')) . '">Privacy policy</a></span></div></footer>'
            . (current_user() ? doc_viewer_html() : '') . confirm_dialog_html() . '</body></html>';
        return;
    }
    $email = (string) config('contact_email');
    $phone = (string) config('contact_phone');
    ?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <?= logo_html('logo-footer') ?>
      <p>Freight dispatching, daily routes and driver support for independent drivers and owner-operators. Built from the driver's seat.</p>
    </div>
    <div>
      <h3>Company</h3>
      <ul>
        <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
        <li><a href="<?= e(url('services.php')) ?>">Services</a></li>
        <li><a href="<?= e(url('careers.php')) ?>">Careers</a></li>
        <li><a href="<?= e(url('partners.php')) ?>">Partner with us</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
      </ul>
    </div>
    <div>
      <h3>Drivers</h3>
      <ul>
        <li><a href="<?= e(url('drivers.php')) ?>">Drive with us</a></li>
        <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
        <li><a href="<?= e(url('login.php')) ?>">Driver sign in</a></li>
        <li><a href="<?= e(url('careers.php')) ?>">Open opportunities</a></li>
        <li><a href="<?= e(url('faq.php')) ?>">Driver FAQ</a></li>
      </ul>
    </div>
    <div>
      <h3>Get in touch</h3>
      <ul class="footer-contact">
        <?php if ($email !== ''): ?><li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li><?= icon('phone') ?><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <li><?= icon('pin') ?>Serving drivers across the USA</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom-wrap"><div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> LamazonLoads. All rights reserved.</span>
    <span><a href="<?= e(url('privacy.php')) ?>">Privacy policy</a> · <span class="motto">Why wait? <b>Let's freight.</b></span></span>
  </div></div>
</footer>
<?= current_user() ? doc_viewer_html() : '' ?><?= confirm_dialog_html() ?>
<?= chat_bubble() ?>
</body>
</html>
<?php
}

function status_badge(string $status): string
{
    $label = APP_STATUSES[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

/** Small page banner used at the top of inner pages. */
function page_hero(string $eyebrow, string $title, string $lead = '', string $photo = '', string $pos = 'center'): void
{
    echo '<section class="page-hero' . ($photo !== '' ? ' has-photo' : '') . '">'
        . ($photo !== '' ? bg_photo($photo, $pos) : '')
        . '<div class="container"><span class="eyebrow">' . e($eyebrow) . '</span><h1>' . $title . '</h1>'
        . ($lead !== '' ? '<p class="lead">' . e($lead) . '</p>' : '') . '</div></section>';
}

/** A photo spot from PAGE_PHOTOS (your upload from Admin → Site photos, or the original stock photo), or a stock photo name. */
function photo(string $name, string $alt, string $class = '', string $sizes = '(max-width: 900px) 100vw, 50vw', string $pos = 'center', bool $eager = false): string
{
    [$set, $up] = photo_set($name);
    $file = $set[0][2];
    $srcset = implode(', ', array_map(fn ($s) => $s[0] . ' ' . $s[1] . 'w', $set));
    if ($up) { // a photo uploaded in Admin → Site photos: centred, with its own description
        $pos = 'center';
        $alt = $alt !== '' && $up['caption'] !== '' ? $up['caption'] : $alt;
    }
    $blur = photo_placeholder($file);
    $style = ($blur !== '' ? 'background-image:url(' . $blur . ');background-size:cover;background-position:' . $pos . ';' : '')
        . ($pos !== 'center' ? 'object-position:' . $pos . ';' : '');
    return '<img class="' . e(trim('ph ' . $class)) . '" src="' . e($set[0][0]) . '" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '" alt="' . e($alt) . '"'
        . ($style !== '' ? ' style="' . e($style) . '"' : '')
        . ($eager ? ' fetchpriority="high" decoding="sync"' : ' loading="lazy" decoding="async"') . '>';
}

/** Banner photos sit under a blue overlay, so 1200 px is plenty even on big screens. */
const BANNER_SIZES = '(max-width: 1200px) 100vw, 1200px';

/** Ask the browser to fetch a page's top banner photo straight away (call before page_header). */
function preload_photo(?string $name = null): ?string
{
    static $preload = null;
    if ($name !== null) {
        $preload = $name;
    }
    return $preload;
}

/** Row (or mosaic) of photos with captions: [[photo, title, text], ...]. */
function photo_band(array $items, string $class = ''): string
{
    $html = '<div class="photo-band ' . e($class) . ' n' . count($items) . '">';
    foreach ($items as $i => [$name, $title, $text]) {
        $html .= '<figure class="pb-item reveal">' . photo($name, $title, '', $i === 0 && count($items) > 3 ? '(max-width: 900px) 100vw, 50vw' : '(max-width: 900px) 100vw, 33vw')
            . '<figcaption><b>' . e($title) . '</b>' . ($text !== '' ? '<span>' . e($text) . '</span>' : '') . '</figcaption></figure>';
    }
    return $html . '</div>';
}

/** Full-width background photo for a hero; the blue overlay comes from CSS. */
function bg_photo(string $name, string $pos = 'center'): string
{
    return '<div class="bg-photo" aria-hidden="true">' . photo($name, '', '', BANNER_SIZES, $pos, true) . '</div>';
}

/** Saved and applied job ids for the signed-in member (for the hearts and "Applied" on job cards). */
function member_job_marks(): array
{
    static $marks = null;
    if ($marks === null) {
        $u = current_user();
        $marks = ['saved' => [], 'applied' => []];
        if ($u) {
            $marks['saved'] = array_map('intval', array_column(db_all('SELECT job_id FROM saved_jobs WHERE user_id = ?', [$u['id']]), 'job_id'));
            $marks['applied'] = array_map('intval', array_column(db_all('SELECT job_id FROM applications WHERE user_id = ?', [$u['id']]), 'job_id'));
        }
    }
    return $marks;
}

/** Job box: title, "LamazonLoads – location", a short description, View & apply and a heart to save it. */
function job_card(array $job): string
{
    $id = (int) $job['id'];
    $href = e(url('job.php?id=' . $id));
    $marks = member_job_marks();
    $saved = in_array($id, $marks['saved'], true);
    $applied = in_array($id, $marks['applied'], true);
    $where = trim((string) $job['location']) !== '' ? (string) $job['location'] : 'United States';
    return '<article class="card jc reveal">'
        . '<h3 class="jc-title"><a href="' . $href . '">' . e($job['title']) . '</a></h3>'
        . '<p class="jc-where">LamazonLoads &ndash; ' . e($where) . '</p>'
        . '<p class="jc-posted">' . icon('clock') . e(job_posted_label($job)) . '</p>'
        . '<p class="jc-desc">' . e(job_excerpt($job, 260, true)) . '</p>'
        . '<div class="jc-actions">'
        . ($applied
            ? '<a class="btn jc-apply is-applied" href="' . $href . '">Applied ' . icon('check') . '</a>'
            : '<a class="btn jc-apply" href="' . $href . '">View &amp; apply ' . icon('arrow') . '</a>')
        . '<form method="post" action="' . e(url('save.php')) . '" class="jc-save-form" data-save>'
        . csrf_field() . '<input type="hidden" name="job" value="' . $id . '">'
        . '<button type="submit" class="jc-save' . ($saved ? ' on' : '') . '" aria-pressed="' . ($saved ? 'true' : 'false') . '" aria-label="' . ($saved ? 'Saved. Remove from saved jobs' : 'Save this job') . '" title="' . ($saved ? 'Saved' : 'Save job') . '">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.3A4.2 4.2 0 0 1 12 7.6a4.2 4.2 0 0 1 7.5 2.6c0 5.7-7.5 10.3-7.5 10.3z"/></svg>'
        . '</button></form>'
        . '</div></article>';
}

/** The closing call-to-action shown at the bottom of most pages. */
function cta_band(): void
{
    $signedIn = (bool) current_user();
    ?>
<section class="section-sm"><div class="container">
  <div class="cta-band reveal">
    <div>
      <h2>Why wait? Let's freight.</h2>
      <p>Create your account, finish onboarding once, and get first access to loads, daily routes and new openings.</p>
    </div>
    <div class="btns">
      <?php if ($signedIn): ?>
        <a class="btn btn-accent btn-lg" href="<?= e(url('account.php')) ?>">Go to my dashboard <?= icon('arrow') ?></a>
      <?php else: ?>
        <a class="btn btn-accent btn-lg" href="<?= e(url('register.php')) ?>">Join LamazonLoads <?= icon('arrow') ?></a>
        <a class="btn btn-outline-light btn-lg" href="<?= e(url('contact.php')) ?>">Talk to dispatch</a>
      <?php endif; ?>
    </div>
  </div>
</div></section>
<?php
}

/** Left-hand menu on member dashboard pages. */
function dash_open(string $active): void
{
    $u = current_user();
    $items = [
        'overview' => ['account.php', 'clipboard', 'Overview'],
        'onboarding' => ['onboarding.php', 'check', 'Onboarding'],
        'profile' => ['profile.php', 'truck', 'Driver profile'],
        'documents' => ['documents.php', 'file', 'Documents'],
        'settings' => ['settings.php', 'user', 'Account settings'],
        'careers' => ['careers.php', 'briefcase', 'Browse openings'],
    ];
    echo '<div class="container dash"><aside class="card dash-nav"><div class="who"><b>' . e($u['name']) . '</b><small>' . e(ACCOUNT_TYPES[$u['account_type']] ?? '') . '</small></div>';
    if (!onboarding_unlocked(onboarding_row((int) $u['id']))) {
        unset($items['onboarding']); // shows up once they open their onboarding from the email link
    }
    if (!documents_visible($u)) {
        unset($items['documents']); // shows up once onboarding is finished (files are on the onboarding page until then)
    }
    foreach ($items as $key => [$href, $ic, $label]) {
        echo '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active"' : '') . '>' . icon($ic) . e($label) . '</a>';
    }
    if ($u['is_admin']) {
        echo '<a href="' . e(url('admin/')) . '">' . icon('shield') . 'Admin dashboard</a>';
    }
    echo '<form method="post" action="' . e(url('logout.php')) . '">' . csrf_field() . '<button class="link-btn" type="submit">' . icon('logout') . 'Sign out</button></form>';
    echo '</aside><div class="dash-main">';
}

function dash_close(): void
{
    echo '</div></div>';
}

/** Admin menu, in labeled groups: [group => [key => [link, icon, label]]]. */
const ADMIN_NAV = [
    'Dashboard' => ['overview' => ['admin/', 'chart', 'Overview']],
    'Hiring' => [
        'applications' => ['admin/applications.php', 'clipboard', 'Applications'],
        'onboarding' => ['admin/onboarding.php', 'check', 'Onboarding'],
        'send' => ['admin/send-onboarding.php', 'send', 'Send onboarding email'],
        'contracts' => ['admin/contracts.php', 'file', 'Contracts'],
        'jobs' => ['admin/jobs.php', 'briefcase', 'Job posts'],
        'drivers' => ['admin/drivers.php', 'truck', 'Drivers & members'],
        'walmart' => ['admin/walmart.php', 'route', 'Walmart routes'],
    ],
    'Inbox' => [
        'chats' => ['admin/chats.php', 'chat', 'Support chats'],
        'messages' => ['admin/messages.php', 'mail', 'Contact messages'],
        'partners' => ['admin/partners.php', 'handshake', 'Partner requests'],
    ],
    'Website' => [
        'photos' => ['admin/photos.php', 'upload', 'Site photos'],
        'email' => ['admin/email.php', 'shield', 'Email check'],
    ],
];
/** Menu pages only admins see (moderators get the rest; see STAFF_ROLES). */
const ADMIN_ONLY_PAGES = ['contracts', 'photos', 'email'];

/** Things waiting for staff, shown as red counts in the menu and on the Overview. */
function admin_counts(): array
{
    static $c = null;
    return $c ??= [
        'applications' => (int) db_val("SELECT COUNT(*) FROM applications WHERE status = 'new'"),
        'onboarding' => (int) db_val("SELECT COUNT(*) FROM onboarding WHERE stage = 'review'"),
        'chats' => chat_unread_total(),
        'messages' => (int) db_val('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
        'partners' => (int) db_val("SELECT COUNT(*) FROM partner_requests WHERE status = 'new'"),
    ];
}

/** Is this an admin page? (Slim footer, no chat bubble, wider layout.) */
function is_admin_page(): bool
{
    return str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/');
}

/** The admin side menu: who's signed in, then the pages in labeled groups. On phones it becomes a scrolling row of tabs. */
function admin_open(string $active): void
{
    global $adminGroup;
    $u = current_user();
    $counts = admin_counts();
    $initials = strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $u['name'])) ?: [], 0, 2))));
    echo '<div class="container dash admin-dash"><aside class="card admin-nav" aria-label="Admin menu">'
        . '<div class="an-user"><span class="an-avatar" aria-hidden="true">' . e($initials ?: 'LL') . '</span><span><b>' . e((string) $u['name']) . '</b><small>' . e(STAFF_ROLES[staff_role($u)] ?? 'Staff') . '</small></span></div>'
        . '<nav class="an-links">';
    foreach (ADMIN_NAV as $group => $items) {
        if (!is_full_admin($u)) {
            $items = array_diff_key($items, array_flip(ADMIN_ONLY_PAGES));
        }
        if (!$items) {
            continue;
        }
        echo '<p class="an-label">' . e($group) . '</p>';
        foreach ($items as $key => [$href, $ic, $label]) {
            if ($active === $key) {
                $adminGroup = $group;
            }
            $n = $counts[$key] ?? 0;
            echo '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active" aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span>'
                . ($n ? '<span class="nav-count" aria-label="' . $n . ' new">' . $n . '</span>' : '') . '</a>';
        }
    }
    echo '</nav><div class="an-foot"><a href="' . e(url('account.php')) . '">' . icon('user') . '<span>My dashboard</span></a>'
        . '<a href="' . e(url('')) . '">' . icon('external') . '<span>View website</span></a></div>';
    echo '</aside><div class="dash-main admin-main">';
}

/** Page title block used on every admin page: group label, title, one-line explanation and the page's main buttons. */
function admin_head(string $title, string $subtitle = '', string $actions = '', string $eyebrow = ''): string
{
    global $adminGroup;
    $eyebrow = $eyebrow !== '' ? $eyebrow : (string) ($adminGroup ?? 'Admin');
    return '<header class="admin-head"><div class="ah-text"><span class="eyebrow">' . e($eyebrow) . '</span><h1>' . e($title) . '</h1>'
        . ($subtitle !== '' ? '<p>' . $subtitle . '</p>' : '') . '</div>'
        . ($actions !== '' ? '<div class="ah-actions">' . $actions . '</div>' : '') . '</header>';
}

function applicant_label(array $row): string
{
    return (int) $row['job_id'] === 0 ? 'Driver network (general)' : (string) ($row['title'] ?? 'Removed opening');
}

/** White box truck with the company name, used by the road animations. */
function truck_svg(string $class = 'truck-svg'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 140 60" aria-hidden="true">'
        . '<g class="speed" stroke="#8EC2FF" stroke-width="3" stroke-linecap="round"><path d="M2 18h14"/><path d="M6 27h12"/><path d="M0 36h16"/></g>'
        . '<g class="bounce">'
        . '<rect x="22" y="6" width="70" height="38" rx="3" fill="#fff"/>'
        . '<rect x="22" y="33" width="70" height="4" fill="#1E63E9"/>'
        . '<text x="57" y="24" text-anchor="middle" textLength="60" lengthAdjust="spacingAndGlyphs" font-family="Montserrat,Arial,sans-serif" font-weight="900" font-style="italic" font-size="10.5" fill="#0A2463">Lamazon<tspan fill="#1E63E9">Loads</tspan></text>'
        . '<path d="M94 16h18a3 3 0 0 1 2.4 1.2l9 12a3 3 0 0 1 .6 1.8V44H94z" fill="#fff"/>'
        . '<path d="M98 20h12.5l7 9.5H98z" fill="#0A2463"/>'
        . '<rect x="120" y="35" width="4" height="3" rx="1" fill="#8EC2FF"/>'
        . '<rect x="20" y="43" width="106" height="4" rx="2" fill="#C9D6F5"/>'
        . '</g>'
        . '<g class="wheel"><circle cx="42" cy="49" r="8" fill="#0A2463" stroke="#fff" stroke-width="3"/><path d="M42 43.5v11M36.5 49h11" stroke="#8EC2FF" stroke-width="2"/></g>'
        . '<g class="wheel"><circle cx="108" cy="49" r="8" fill="#0A2463" stroke="#fff" stroke-width="3"/><path d="M108 43.5v11M102.5 49h11" stroke="#8EC2FF" stroke-width="2"/></g>'
        . '</svg>';
}
