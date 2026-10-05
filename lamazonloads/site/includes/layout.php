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
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function logo_html(string $class = ''): string
{
    return '<span class="logo ' . e($class) . '"><img src="' . e(asset('brand/mark.svg')) . '" alt="" width="40" height="40">'
        . '<span class="logo-text"><span class="logo-word">Lamazon<b>Loads</b></span><span class="logo-tag">Why wait? Let\'s freight.</span></span></span>';
}

function page_header(string $title, string $active = '', string $description = ''): void
{
    $user = current_user();
    $desc = $description ?: 'LamazonLoads: freight dispatching, daily routes and real driver support for cargo vans, Sprinter vans and box trucks. Built by drivers, for drivers.';
    $nav = [
        'home' => ['', 'Home'],
        'services' => ['services.php', 'Services'],
        'drivers' => ['drivers.php', 'Drive with us'],
        'careers' => ['careers.php', 'Careers'],
        'about' => ['about.php', 'About'],
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
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body>
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
      <div class="nav-actions">
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
    $email = (string) config('contact_email');
    $phone = (string) config('contact_phone');
    ?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <?= logo_html('logo-light') ?>
      <p>Freight dispatching, daily routes and driver support for independent drivers and owner-operators. Built from the driver's seat.</p>
    </div>
    <div>
      <h3>Company</h3>
      <ul>
        <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
        <li><a href="<?= e(url('services.php')) ?>">Services</a></li>
        <li><a href="<?= e(url('careers.php')) ?>">Careers</a></li>
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
        <?php if ($phone !== ''): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <li><?= icon('pin') ?>Serving drivers across the USA</li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> LamazonLoads. All rights reserved.</span>
    <span class="motto">Why wait? <b>Let's freight.</b></span>
  </div>
</footer>
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
function page_hero(string $eyebrow, string $title, string $lead = ''): void
{
    echo '<section class="page-hero"><div class="container"><span class="eyebrow">' . e($eyebrow) . '</span><h1>' . $title . '</h1>'
        . ($lead !== '' ? '<p class="lead">' . e($lead) . '</p>' : '') . '</div></section>';
}

function job_card(array $job): string
{
    $href = e(url('job.php?id=' . (int) $job['id']));
    return '<article class="card job-card reveal">'
        . '<div class="tags"><span class="tag tag-amber">' . e(JOB_CATEGORIES[$job['category']] ?? 'Opportunity') . '</span>'
        . ($job['location'] !== '' ? '<span class="tag">' . icon('pin') . e($job['location']) . '</span>' : '') . '</div>'
        . '<h3><a href="' . $href . '">' . e($job['title']) . '</a></h3>'
        . '<p>' . e($job['summary']) . '</p>'
        . ($job['equipment'] !== '' && $job['equipment'] !== 'Not applicable' ? '<div class="tags"><span class="tag">' . icon('truck') . e($job['equipment']) . '</span></div>' : '')
        . '<a class="btn btn-ghost btn-sm" href="' . $href . '">View &amp; apply ' . icon('arrow') . '</a>'
        . '</article>';
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
        'messages' => ['admin/messages.php', 'mail', 'Messages', $unread],
    ];
    echo '<div class="container dash"><aside class="card dash-nav"><div class="who"><b>Admin</b><small>LamazonLoads staff</small></div>';
    foreach ($items as $key => [$href, $ic, $label, $count]) {
        echo '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active"' : '') . '>' . icon($ic) . e($label)
            . ($count ? ' <span class="badge badge-reviewing" style="margin-left:auto">' . $count . '</span>' : '') . '</a>';
    }
    echo '<a href="' . e(url('account.php')) . '">' . icon('user') . 'My dashboard</a>';
    echo '</aside><div class="dash-main">';
}

function applicant_label(array $row): string
{
    return (int) $row['job_id'] === 0 ? 'Driver network (general)' : (string) ($row['title'] ?? 'Removed opening');
}
