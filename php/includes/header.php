<?php
defined('TC_APP') || exit;
/** @var string|null $title  Page title (set before including) */
/** @var bool $noindex */
$user = current_user();
$site = (string) config('site_name');
$current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$nav = [['flights.php', t('Flights'), 'plane'], ['hotels.php', t('Hotels'), 'bed'], ['packages.php', t('Packages'), 'package']];
$pkHere = current_path_with_query();
$pkLang = current_lang();
$pkCur = current_currency();
$pkFxOn = (bool) fx_rates();
// Admin pages always stay in English and US dollars, so the language/currency menu isn't shown there.
$pkShowPrefs = !str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/');
$initials = $user ? implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(preg_split('/\s+/', trim($user['name'])), 0, 2))) : '';
$flashMessage = take_flash();
?><!doctype html>
<html lang="<?= e(html_lang()) ?>" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php
  $pageTitle = isset($title) ? t($title) . " | $site" : "$site — " . t('Cheap flights, hotels & holiday packages');
  $pageDescription = t($description ?? 'Your travel assistant for affordable flights, hotels and Flight + Hotel packages. Real airline and hotel prices, booked with help from real travel assistants.');
  $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  $canonical = app_url_or_local() . '/' . ($script === 'index.php' ? '' : $script);
  ?>
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <?php if (!empty($noindex) || ($_GET && in_array($script, ['flights.php', 'hotels.php'], true))): ?>
    <meta name="robots" content="noindex, nofollow">
  <?php else: ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($site) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
  <?php endif; ?>
  <?php if (!empty($noReferrer)): ?><meta name="referrer" content="no-referrer"><?php endif; ?>
  <link rel="preload" href="<?= e(url('assets/fonts/geist-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('chat.css')) ?>">
  <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="flex min-h-full flex-col font-sans antialiased">
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
    <a href="<?= e(url()) ?>" class="flex items-center gap-2 text-xl font-bold tracking-tight">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md"><?= icon('plane', 18) ?></span>
      <span class="text-slate-900">Fare<span class="text-accent-600">Finders</span></span>
    </a>
    <nav class="hidden items-center gap-1 md:flex">
      <?php foreach ($nav as [$href, $label, $ic]): $active = $current === $href; ?>
        <a href="<?= e(url($href)) ?>" class="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition <?= $active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"><?= icon($ic, 16) ?><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="hidden items-center gap-2 md:flex">
      <?php if ($pkShowPrefs): ?>
      <div class="relative" data-menu>
        <button type="button" aria-expanded="false" aria-haspopup="menu" aria-label="<?= e(t('Language and currency')) ?>" class="flex items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-menu-toggle data-prefs-toggle>
          <?= lang_flag($pkLang) ?><span><?= e($pkCur === 'USD' ? '$ USD' : CURRENCIES[$pkCur][0]) ?></span>
        </button>
        <div role="menu" class="absolute right-0 top-full mt-2 hidden w-[36rem] max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-4 shadow-xl" data-menu-panel>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(t('Language')) ?></p>
              <div class="max-h-80 overflow-y-auto pr-1">
                <?php foreach (LANGUAGES as $pkCode => [$pkName]): ?>
                  <a role="menuitem" lang="<?= e(LANGUAGES[$pkCode][2]) ?>" href="<?= e(url('prefs.php', ['lang' => $pkCode, 'next' => $pkHere])) ?>" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm <?= $pkCode === $pkLang ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-700 hover:bg-slate-50' ?>"><?= lang_flag($pkCode, true) ?><?= e($pkName) ?></a>
                <?php endforeach; ?>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(t('Currency')) ?></p>
              <?php if ($pkFxOn): ?>
                <div class="max-h-80 overflow-y-auto pr-1">
                  <?php foreach (CURRENCIES as $pkCode => [$pkSymbol, $pkCurName]): if ($pkCode !== 'USD' && !isset(fx_rates()['rates'][$pkCode])) continue; ?>
                    <a role="menuitem" href="<?= e(url('prefs.php', ['cur' => $pkCode, 'next' => $pkHere])) ?>" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm <?= $pkCode === $pkCur ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-700 hover:bg-slate-50' ?>"><span class="w-9 shrink-0 font-semibold"><?= e($pkCode) ?></span><span class="truncate"><?= e(t($pkCurName)) ?></span><span class="ml-auto text-slate-400"><?= e($pkSymbol) ?></span></a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-sm text-slate-600"><?= e(t('Prices are shown in US dollars.')) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500"><?= e(t('You always pay in US dollars. Other currencies are estimates using today\'s exchange rate.')) ?></p>
        </div>
      </div>
      <?php endif; ?>
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
            <a role="menuitem" href="<?= e(url('account.php')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"><?= icon('user', 16) ?> <?= e(t('My account & trips')) ?></a>
            <?php if ($user['role'] === 'admin'): ?>
              <a role="menuitem" href="<?= e(url('admin/')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50"><?= icon('package', 16) ?> Admin dashboard</a>
            <?php endif; ?>
            <a role="menuitem" href="<?= e(url('settings.php')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"><?= icon('shield', 16) ?> <?= e(t('Settings')) ?></a>
            <form method="post" action="<?= e(url('signout.php')) ?>"><?= csrf_field() ?>
              <button type="submit" role="menuitem" class="w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"><?= e(t('Sign out')) ?></button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= e(url('signin.php')) ?>" class="rounded-full px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50"><?= e(t('Sign in')) ?></a>
        <a href="<?= e(url('register.php')) ?>" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"><?= e(t('Create account')) ?></a>
      <?php endif; ?>
    </div>
    <button type="button" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden" aria-label="<?= e(t('Open menu')) ?>" aria-expanded="false" data-mobile-toggle><?= icon('menu') ?></button>
  </div>
  <div class="hidden border-t border-slate-200 bg-white px-4 pb-4 md:hidden" data-mobile-menu>
    <?php if ($pkShowPrefs): ?>
    <form method="get" action="<?= e(url('prefs.php')) ?>" class="grid grid-cols-[1fr_1fr_auto] items-end gap-2 border-b border-slate-100 py-3">
      <input type="hidden" name="next" value="<?= e($pkHere) ?>">
      <label class="text-xs font-semibold text-slate-500"><?= e(t('Language')) ?>
        <select name="lang" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-800">
          <?php foreach (LANGUAGES as $pkCode => [$pkName]): ?><option value="<?= e($pkCode) ?>"<?= $pkCode === $pkLang ? ' selected' : '' ?>><?= e($pkName) ?></option><?php endforeach; ?>
        </select></label>
      <label class="text-xs font-semibold text-slate-500"><?= e(t('Currency')) ?>
        <select name="cur" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-800">
          <?php foreach (CURRENCIES as $pkCode => [$pkSymbol]): if ($pkCode !== 'USD' && !isset(fx_rates()['rates'][$pkCode])) continue; ?><option value="<?= e($pkCode) ?>"<?= $pkCode === $pkCur ? ' selected' : '' ?>><?= e("$pkCode $pkSymbol") ?></option><?php endforeach; ?>
        </select></label>
      <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white"><?= e(t('Apply')) ?></button>
    </form>
    <?php endif; ?>
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
          <a href="<?= e(url('account.php')) ?>" class="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white"><?= e(t('My account')) ?></a>
          <form method="post" action="<?= e(url('signout.php')) ?>"><?= csrf_field() ?><button type="submit" class="w-full rounded-full border border-slate-200 py-2 text-sm font-semibold text-slate-700"><?= e(t('Sign out')) ?></button></form>
          <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= e(url('admin/')) ?>" class="col-span-2 rounded-full border border-brand-200 py-2 text-center text-sm font-semibold text-brand-700">Admin dashboard</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-2">
        <a href="<?= e(url('signin.php')) ?>" class="rounded-full border border-brand-200 py-2 text-center text-sm font-semibold text-brand-700"><?= e(t('Sign in')) ?></a>
        <a href="<?= e(url('register.php')) ?>" class="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white"><?= e(t('Create account')) ?></a>
      </div>
    <?php endif; ?>
  </div>
</header>
<main class="flex-1">
<?php if ($user && !$user['verified'] && $current !== 'verify-email.php'): ?>
  <form method="post" action="<?= e(url('verify-email.php')) ?>" class="border-b border-amber-200 bg-amber-50"><?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm text-amber-900 sm:px-6">
      <p><strong><?= e(t('Please confirm your email.')) ?></strong> <?= e(t("We sent a link to {email} — you'll need it before booking.", ['email' => $user['email']])) ?></p>
      <button type="submit" class="font-semibold underline underline-offset-2 hover:text-amber-700"><?= e(t('Send a new link')) ?></button>
    </div>
  </form>
<?php endif; ?>
<?php if ($flashMessage): ?>
  <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6"><?= alert_box($flashMessage['message'], $flashMessage['type']) ?></div>
<?php endif; ?>
