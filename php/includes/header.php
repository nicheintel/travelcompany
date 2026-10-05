<?php
defined('TC_APP') || exit;
/** @var string|null $title  Page title (set before including) */
/** @var bool $noindex */
$user = current_user();
$site = (string) config('site_name');
$current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$nav = [['flights.php', 'Flights', 'plane'], ['hotels.php', 'Hotels', 'bed'], ['packages.php', 'Packages', 'package']];
$initials = $user ? implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(preg_split('/\s+/', trim($user['name'])), 0, 2))) : '';
$flashMessage = take_flash();
?><!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(isset($title) ? "$title | $site" : "$site — Cheap flights, hotels & holiday packages") ?></title>
  <meta name="description" content="Your travel assistant for affordable flights, hotels and holiday packages">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <?php if (!empty($noReferrer)): ?><meta name="referrer" content="no-referrer"><?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
  <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="flex min-h-full flex-col font-sans antialiased">
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
    <a href="<?= e(url()) ?>" class="flex items-center gap-2 text-xl font-bold tracking-tight">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md"><?= icon('plane', 18) ?></span>
      <span class="text-slate-900">Fare<span class="text-accent-500">Finders</span></span>
    </a>
    <nav class="hidden items-center gap-1 md:flex">
      <?php foreach ($nav as [$href, $label, $ic]): $active = $current === $href; ?>
        <a href="<?= e(url($href)) ?>" class="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition <?= $active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"><?= icon($ic, 16) ?><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="hidden items-center gap-2 md:flex">
      <?php if ($user): ?>
        <div class="relative" data-menu>
          <button type="button" aria-expanded="false" aria-haspopup="menu" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-slate-100" data-menu-toggle>
            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white"><?= e($initials) ?></span>
            <span class="max-w-32 truncate text-sm font-semibold text-slate-800"><?= e(explode(' ', $user['name'])[0]) ?></span>
          </button>
          <div role="menu" class="absolute right-0 top-full mt-2 hidden w-60 rounded-xl border border-slate-200 bg-white py-2 shadow-xl" data-menu-panel>
            <div class="border-b border-slate-100 px-4 pb-3 pt-1">
              <p class="truncate text-sm font-semibold text-slate-900"><?= e($user['name']) ?></p>
              <p class="truncate text-xs text-slate-500"><?= e($user['email']) ?></p>
            </div>
            <a role="menuitem" href="<?= e(url('account.php')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"><?= icon('user', 16) ?> My account &amp; trips</a>
            <?php if ($user['role'] === 'admin'): ?>
              <a role="menuitem" href="<?= e(url('admin/')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50"><?= icon('package', 16) ?> Admin dashboard</a>
            <?php endif; ?>
            <a role="menuitem" href="<?= e(url('settings.php')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"><?= icon('shield', 16) ?> Settings</a>
            <form method="post" action="<?= e(url('signout.php')) ?>"><?= csrf_field() ?>
              <button type="submit" role="menuitem" class="w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">Sign out</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= e(url('signin.php')) ?>" class="rounded-full px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">Sign in</a>
        <a href="<?= e(url('register.php')) ?>" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">Create account</a>
      <?php endif; ?>
    </div>
    <button type="button" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden" aria-label="Open menu" aria-expanded="false" data-mobile-toggle><?= icon('menu') ?></button>
  </div>
  <div class="hidden border-t border-slate-200 bg-white px-4 pb-4 md:hidden" data-mobile-menu>
    <nav class="flex flex-col py-2">
      <?php foreach ($nav as [$href, $label, $ic]): ?>
        <a href="<?= e(url($href)) ?>" class="flex items-center gap-3 rounded-lg px-3 py-3 text-slate-700 hover:bg-slate-100"><?= icon($ic, 18) ?><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($user): ?>
      <div class="border-t border-slate-100 pt-3">
        <p class="px-3 text-sm font-semibold text-slate-900"><?= e($user['name']) ?></p>
        <p class="px-3 text-xs text-slate-500"><?= e($user['email']) ?></p>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <a href="<?= e(url('account.php')) ?>" class="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white">My account</a>
          <form method="post" action="<?= e(url('signout.php')) ?>"><?= csrf_field() ?><button type="submit" class="w-full rounded-full border border-slate-200 py-2 text-sm font-semibold text-slate-700">Sign out</button></form>
          <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= e(url('admin/')) ?>" class="col-span-2 rounded-full border border-brand-200 py-2 text-center text-sm font-semibold text-brand-700">Admin dashboard</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-2">
        <a href="<?= e(url('signin.php')) ?>" class="rounded-full border border-brand-200 py-2 text-center text-sm font-semibold text-brand-700">Sign in</a>
        <a href="<?= e(url('register.php')) ?>" class="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white">Create account</a>
      </div>
    <?php endif; ?>
  </div>
</header>
<main class="flex-1">
<?php if ($flashMessage): ?>
  <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6"><?= alert_box($flashMessage['message'], $flashMessage['type']) ?></div>
<?php endif; ?>
