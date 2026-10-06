<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/** Simple line icons (24×24, stroke = currentColor). */
function icon(string $name, string $class = 'ic'): string
{
    static $paths = [
        'truck'     => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 4v3h-7z"/><circle cx="7" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>',
        'van'       => '<path d="M2 7h12l4 4h3v6H2z"/><path d="M14 7v4h4"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/>',
        'route'     => '<circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H17a3.5 3.5 0 0 0 0-7H7a3.5 3.5 0 0 1 0-7h8.5"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h4"/>',
        'headset'   => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20a3 3 0 0 1-3 2h-3"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        'dollar'    => '<path d="M12 2v20"/><path d="M17 6.5c-.8-1.3-2.6-2-5-2-3 0-4.5 1.4-4.5 3.2 0 4.3 9.5 2.3 9.5 7.3 0 1.9-1.8 3.5-5 3.5-2.6 0-4.4-.9-5.2-2.3"/>',
        'check'     => '<path d="M4.5 12.5l5 5 10-11"/>',
        'arrow'     => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 4.7-3.4 8.3-8 9-4.6-.7-8-4.3-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'file'      => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
        'upload'    => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
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

/** "tel:" link for a US phone number written any way, e.g. 678-666-4334 -> tel:+16786664334. */
function tel_href(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return 'tel:' . (strlen($digits) === 10 ? '+1' . $digits : (str_starts_with($phone, '+') ? '+' : '') . $digits);
}

function logo_html(string $class = ''): string
{
    return '<img class="logo ' . e($class) . '" src="' . e(asset('brand/logo.png')) . '" alt="LamazonLoads: Why wait? Let\'s freight." width="364" height="204">';
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
<link rel="icon" href="<?= e(asset('favicon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
<?php if (($pre = preload_photo()) !== null): [$preSet] = photo_set($pre); ?><link rel="preload" as="image" href="<?= e($preSet[0][0]) ?>" imagesrcset="<?= e(implode(', ', array_map(fn ($s) => $s[0] . ' ' . $s[1] . 'w', $preSet))) ?>" imagesizes="<?= e(BANNER_SIZES) ?>" fetchpriority="high">
<?php endif; ?>
<script src="<?= e(asset('app.js')) ?>" defer></script>
<script type="speculationrules">{"prefetch":[{"where":{"selector_matches":".site-nav a, .site-footer a, a.btn, .jc-title a, a.sol-top, a.more-sol-card, a.biz-item, .crumbs a"},"eagerness":"moderate"}]}</script>
</head>
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
        echo '<div class="container flashes">';
        foreach ($flashes as [$type, $msg]) {
            echo '<div class="alert alert-' . e($type) . '" role="status">' . e($msg) . '</div>';
        }
        echo '</div>';
    }
}

function page_footer(): void
{
    run_automations_if_due();
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
      <p>Create your free account, finish onboarding once, and get first access to loads, daily routes and new openings.</p>
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
        'profile' => ['profile.php', 'truck', 'Driver profile'],
        'documents' => ['documents.php', 'file', 'Documents'],
        'settings' => ['settings.php', 'user', 'Account settings'],
        'careers' => ['careers.php', 'briefcase', 'Browse openings'],
    ];
    echo '<div class="container dash"><aside class="card dash-nav"><div class="who"><b>' . e($u['name']) . '</b><small>' . e(ACCOUNT_TYPES[$u['account_type']] ?? '') . '</small></div>';
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

function admin_open(string $active): void
{
    $newApps = (int) db_val("SELECT COUNT(*) FROM applications WHERE status = 'new'");
    $unread = (int) db_val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
    $items = [
        'overview' => ['admin/', 'chart', 'Overview', 0],
        'applications' => ['admin/applications.php', 'clipboard', 'Applications', $newApps],
        'jobs' => ['admin/jobs.php', 'briefcase', 'Job posts', 0],
        'drivers' => ['admin/drivers.php', 'truck', 'Drivers & members', 0],
        'chats' => ['admin/chats.php', 'chat', 'Support chats', chat_unread_total()],
        'walmart' => ['admin/walmart.php', 'route', 'Walmart routes', 0],
        'partners' => ['admin/partners.php', 'handshake', 'Partner requests', (int) db_val("SELECT COUNT(*) FROM partner_requests WHERE status = 'new'")],
        'messages' => ['admin/messages.php', 'mail', 'Messages', $unread],
        'photos' => ['admin/photos.php', 'upload', 'Site photos', 0],
        'email' => ['admin/email.php', 'shield', 'Email check', 0],
    ];
    echo '<div class="container dash"><aside class="card dash-nav"><div class="who"><b>Admin</b><small>LamazonLoads staff</small></div>';
    foreach ($items as $key => [$href, $ic, $label, $count]) {
        echo '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active"' : '') . '>' . icon($ic) . e($label)
            . ($count ? ' <span class="nav-count">' . $count . '</span>' : '') . '</a>';
    }
    echo '<a href="' . e(url('account.php')) . '">' . icon('user') . 'My dashboard</a>';
    echo '</aside><div class="dash-main">';
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
