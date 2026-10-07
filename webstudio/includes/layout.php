<?php
declare(strict_types=1);
defined('WS_APP') || exit;

/** Brand name with the last word lighter, e.g. "IdeaCraft <span>Studio</span>". */
function brand_name_html(): string
{
    $name = trim(setting('brand_name'));
    $pos = mb_strrpos($name, ' ');
    if ($pos === false) {
        return e($name);
    }
    return e(mb_substr($name, 0, $pos)) . '<span>' . e(mb_substr($name, $pos)) . '</span>';
}

function brand_link(string $href, string $extra = ''): string
{
    return '<a class="brand ' . e($extra) . '" href="' . e($href) . '">' . logo_mark() . '<span class="brand-name">' . brand_name_html() . '</span></a>';
}

/** Social / chat links that are filled in on Admin → Settings. */
function social_links(): array
{
    $links = [];
    if (setting('facebook_url') !== '') $links[] = ['facebook', 'Facebook', setting('facebook_url')];
    if (setting('instagram_url') !== '') $links[] = ['instagram', 'Instagram', setting('instagram_url')];
    if (setting('tiktok_url') !== '') $links[] = ['tiktok', 'TikTok', setting('tiktok_url')];
    return $links;
}

/** Chat link for the floating button: WhatsApp first, then Messenger. */
function chat_link(): ?array
{
    if (phone_digits(setting('whatsapp')) !== '') {
        return ['WhatsApp', 'https://wa.me/' . phone_digits(setting('whatsapp')) . '?text=' . rawurlencode('Hi! I\'d like to ask about a website for my business.')];
    }
    if (setting('messenger_url') !== '') {
        return ['Messenger', setting('messenger_url')];
    }
    return null;
}

// ---------- Public pages ----------

/**
 * Opens a public page. Options: title, description, home (true on the landing page), dark_top
 * (page starts with a dark section: transparent header), noindex.
 */
function page_start(array $o = []): void
{
    $brand = setting('brand_name');
    $title = isset($o['title']) ? $o['title'] . ' · ' . $brand : $brand . ' — ' . setting('tagline');
    $description = $o['description'] ?? setting('hero_text');
    $home = !empty($o['home']);
    $overHero = $home || !empty($o['dark_top']); // transparent header over a dark top section
    $link = fn(string $hash) => $home ? '#' . $hash : url('', []) . '#' . $hash;
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#0a1a4f">
<?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:site_name" content="<?= e($brand) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/jakarta-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</head>
<body class="<?= $home ? 'is-home' : 'is-inner' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header<?= $overHero ? ' is-over-hero' : '' ?>" data-header>
  <div class="container header-inner">
    <?= brand_link(url()) ?>
    <nav class="nav" id="site-nav" aria-label="Main">
      <a href="<?= e($link('services')) ?>">Services</a>
      <a href="<?= e($link('work')) ?>">Our work</a>
      <a href="<?= e($link('process')) ?>">How it works</a>
      <a href="<?= e($link('pricing')) ?>">Pricing</a>
      <a href="<?= e($link('faq')) ?>">FAQ</a>
      <a class="nav-cta-mobile btn btn-orange" href="<?= e($link('quote')) ?>">Get a free quote <?= icon('arrow-right') ?></a>
    </nav>
    <div class="header-actions">
      <a class="btn btn-orange btn-sm header-cta" href="<?= e($link('quote')) ?>"><span>Get a free quote</span> <?= icon('arrow-right') ?></a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu" data-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
<main id="main">
<?php
}

function page_end(): void
{
    $chat = chat_link();
    $socials = social_links();
    $email = setting('contact_email');
    $phone = setting('contact_phone');
    $home = url();
    ?>
</main>
<footer class="site-footer">
  <div class="footer-glow" aria-hidden="true"></div>
  <div class="container footer-grid">
    <div class="footer-brand">
      <?= brand_link($home, 'brand-light') ?>
      <p><?= e(setting('tagline')) ?>. We design, build and launch websites that help small businesses look professional and win more customers.</p>
      <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as [$ic, $label, $href]): ?>
            <a href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= icon($ic) ?></a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>
    <div>
      <h3>Services</h3>
      <ul>
        <li><a href="<?= e($home) ?>#services">Business websites</a></li>
        <li><a href="<?= e($home) ?>#services">Landing pages</a></li>
        <li><a href="<?= e($home) ?>#services">Online stores</a></li>
        <li><a href="<?= e($home) ?>#services">Website redesign</a></li>
        <li><a href="<?= e($home) ?>#services">SEO &amp; Google setup</a></li>
      </ul>
    </div>
    <div>
      <h3>Company</h3>
      <ul>
        <li><a href="<?= e($home) ?>#work">Our work</a></li>
        <li><a href="<?= e($home) ?>#process">How it works</a></li>
        <li><a href="<?= e($home) ?>#pricing">Pricing</a></li>
        <li><a href="<?= e($home) ?>#faq">FAQ</a></li>
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy</a></li>
      </ul>
    </div>
    <div>
      <h3>Get in touch</h3>
      <ul class="footer-contact">
        <?php if ($email !== ''): ?><li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif ?>
        <?php if ($phone !== ''): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif ?>
        <?php if (setting('location') !== ''): ?><li><?= icon('pin') ?><span><?= e(setting('location')) ?></span></li><?php endif ?>
        <?php if (setting('business_hours') !== ''): ?><li><?= icon('clock') ?><span><?= e(setting('business_hours')) ?></span></li><?php endif ?>
      </ul>
      <a class="btn btn-orange btn-sm" href="<?= e($home) ?>#quote">Request your website <?= icon('arrow-right') ?></a>
    </div>
  </div>
  <div class="container footer-bottom">
    <p>© <?= gmdate('Y') ?> <?= e(setting('brand_name')) ?>. All rights reserved.</p>
    <p>Made with care for small businesses.</p>
  </div>
</footer>
<?php if ($chat): ?>
<a class="chat-fab" href="<?= e($chat[1]) ?>" target="_blank" rel="noopener" aria-label="Chat with us on <?= e($chat[0]) ?>">
  <?= icon('chat') ?><span>Chat with us</span>
</a>
<?php endif ?>
</body>
</html>
<?php
}

// ---------- Admin pages ----------

const ADMIN_NAV = [
    'dashboard' => ['Dashboard', 'grid', 'admin/'],
    'requests' => ['Requests', 'inbox', 'admin/requests.php'],
    'packages' => ['Pricing packages', 'tag', 'admin/packages.php'],
    'portfolio' => ['Portfolio', 'briefcase', 'admin/portfolio.php'],
    'testimonials' => ['Testimonials', 'heart', 'admin/testimonials.php'],
    'faqs' => ['FAQs', 'help', 'admin/faqs.php'],
    'settings' => ['Site settings', 'sliders', 'admin/settings.php'],
    'team' => ['Team & password', 'users', 'admin/team.php'],
];

function admin_head(string $title, string $bodyClass): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(setting('brand_name')) ?> Admin</title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/jakarta-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

/** Opens an admin page: sidebar, top bar with the title, and the flash message. */
function admin_start(string $title, string $active, string $actions = ''): void
{
    $admin = current_admin();
    $newCount = new_request_count();
    admin_head($title, 'admin');
    ?>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <a class="brand brand-light" href="<?= e(url('admin/')) ?>"><?= logo_mark() ?><span class="brand-stack"><span class="brand-name"><?= brand_name_html() ?></span><small>Admin dashboard</small></span></a>
    </div>
    <nav class="side-nav" aria-label="Admin">
      <?php foreach (ADMIN_NAV as $key => [$label, $ic, $href]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $key === $active ? 'is-active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
          <?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($key === 'requests' && $newCount > 0): ?><b class="nav-count"><?= $newCount ?></b><?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
    <div class="sidebar-bottom">
      <a class="side-site" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View website</a>
      <div class="me">
        <span class="avatar"><?= e(initials($admin['name'] ?? '')) ?></span>
        <span class="me-text"><b><?= e($admin['name'] ?? '') ?></b><small><?= e($admin['email'] ?? '') ?></small></span>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="icon-btn" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></button>
        </form>
      </div>
    </div>
  </aside>
  <div class="sidebar-backdrop" data-sidebar-close></div>
  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" aria-label="Open menu" aria-controls="sidebar" data-sidebar-open><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <div class="topbar-actions"><?= $actions ?></div>
    </header>
    <div class="content">
      <?php if ($f = take_flash()): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status" data-flash><?= icon($f['type'] === 'error' ? 'alert' : 'check-circle') ?><span><?= e($f['message']) ?></span></div>
      <?php endif ?>
<?php
}

function admin_end(): void
{
    ?>
    </div>
  </div>
</div>
</body>
</html>
<?php
}

/** Sign-in and setup pages: brand panel on the left, form on the right. */
function auth_start(string $title, string $heading, string $intro): void
{
    admin_head($title, 'auth');
    ?>
<div class="auth">
  <section class="auth-art" aria-hidden="true">
    <div class="auth-orb auth-orb-1"></div><div class="auth-orb auth-orb-2"></div>
    <div class="auth-art-inner">
      <?= brand_link(url(), 'brand-light') ?>
      <h2>Run your studio <span>from one place.</span></h2>
      <p>New requests, quotes, projects and every word on your website — all in your dashboard.</p>
      <div class="auth-cards">
        <div class="auth-card"><span class="dot dot-orange"></span><b>New request</b><small>Website for a bakery</small></div>
        <div class="auth-card"><span class="dot dot-blue"></span><b>Quote sent</b><small>Online store · <?= e(money(24999)) ?></small></div>
        <div class="auth-card"><span class="dot dot-green"></span><b>Launched</b><small>Dental clinic website</small></div>
      </div>
    </div>
  </section>
  <section class="auth-form">
    <div class="auth-box">
      <a class="auth-back" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> Back to website</a>
      <h1><?= e($heading) ?></h1>
      <p class="muted"><?= e($intro) ?></p>
      <?php if ($f = take_flash()): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'error' ? 'alert' : 'check-circle') ?><span><?= e($f['message']) ?></span></div>
      <?php endif ?>
<?php
}

function auth_end(): void
{
    ?>
    </div>
  </section>
</div>
</body>
</html>
<?php
}

/** "Page not found" for public pages. */
function not_found(): never
{
    http_response_code(404);
    page_start(['title' => 'Page not found', 'noindex' => true]);
    echo '<section class="simple-page"><div class="container narrow center"><p class="eyebrow">404</p><h1>We couldn\'t find that page</h1>'
        . '<p class="lead">The link may be old or mistyped.</p><a class="btn btn-orange" href="' . e(url()) . '">Back to home ' . icon('arrow-right') . '</a></div></section>';
    page_end();
    exit;
}
