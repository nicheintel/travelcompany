<?php defined('TC_APP') || exit; ?>
</main>
<?php
// i18n-keys: 'Book', 'Account', 'Help & legal', 'Cheap flights', 'Hotels', 'Flight + Hotel packages', 'My trips', 'Account settings', 'Sign in', 'Create account', 'Help center', 'FAQs', 'Privacy policy', 'Cookie policy', 'Terms of use'
$columns = [
    'Book' => [['flights.php', 'Cheap flights'], ['hotels.php', 'Hotels'], ['packages.php', 'Flight + Hotel packages']],
    'Account' => current_user()
        ? [['account.php', 'My trips'], ['settings.php', 'Account settings']]
        : [['signin.php', 'Sign in'], ['register.php', 'Create account'], ['account.php', 'My trips']],
    'Help & legal' => [['help.php', 'Help center'], ['help.php#faq', 'FAQs'], ['privacy.php', 'Privacy policy'], ['cookies.php', 'Cookie policy'], ['terms.php', 'Terms of use']],
];
?>
<footer class="mt-auto bg-brand-950 text-slate-300">
  <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_repeat(3,1fr)]">
    <div class="space-y-4">
      <a href="<?= e(url()) ?>" class="flex items-center gap-2 text-xl font-bold tracking-tight">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md"><?= icon('plane', 18) ?></span>
        <span class="text-white">Fare<span class="text-accent-500">Finders</span></span>
      </a>
      <p class="max-w-xs text-sm leading-relaxed text-slate-400"><?= e(t('Your travel assistant for affordable flights, hotels and holiday packages.')) ?></p>
    </div>
    <?php foreach ($columns as $heading => $links): ?>
      <div>
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white"><?= e(t($heading)) ?></h3>
        <ul class="space-y-2 text-sm">
          <?php foreach ($links as [$href, $label]): ?><li><a href="<?= e(url($href)) ?>" class="hover:text-white"><?= e(t($label)) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="border-t border-white/10">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <p>© <?= gmdate('Y') ?> <?= e(config('site_name')) ?>. <?= e(t('All rights reserved.')) ?></p>
      <p><?= e(t('Prices shown may change until booking is confirmed.')) ?>
        <?php if (current_currency() !== 'USD' && ($fx = fx_rates())): ?>
          <?= e(t('You pay in US dollars; {currency} prices are estimates.', ['currency' => current_currency()])) ?>
          <?php if ($fx['source'] === 'ExchangeRate-API'): ?><a href="https://www.exchangerate-api.com" target="_blank" rel="noopener" class="underline hover:text-slate-300">Rates By Exchange Rate API</a><?php else: ?><?= e(t('Rates: {source}', ['source' => $fx['source']])) ?><?php endif; ?>
        <?php endif; ?>
        <?php if (current_lang() !== 'en'): ?><?= e(t('Translated automatically.')) ?><?php endif; ?></p>
    </div>
  </div>
</footer>
<?php if ($jsStrings = js_strings()): ?><div hidden data-i18n="<?= e(json_encode($jsStrings, JSON_UNESCAPED_UNICODE)) ?>"></div><?php endif; ?>
<?= chat_bubble() ?>
<?php if (!empty($GLOBALS['search_wait'])): ?>
<div class="search-wait" data-search-wait hidden role="status" aria-live="polite">
  <div class="search-wait-card">
    <svg viewBox="0 0 240 80" class="search-wait-art" aria-hidden="true">
      <path d="M30 70 Q 120 -10 210 70" fill="none" stroke="#bcd7ff" stroke-width="2" stroke-dasharray="4 6"/>
      <circle cx="30" cy="70" r="5" fill="#1c54f0"/><circle cx="210" cy="70" r="5" fill="#d4af37"/>
      <g><animateMotion dur="2.4s" repeatCount="indefinite" rotate="auto" path="M30 70 Q 120 -10 210 70"/>
        <g transform="rotate(45) translate(-12 -12)" fill="#1c54f0" stroke="#1c54f0" stroke-width="1" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></g>
      </g>
    </svg>
    <p class="search-wait-title"><?= e(t('Finding the best prices…')) ?></p>
    <p class="search-wait-sub"><?= e(t('This can take a few seconds.')) ?></p>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>
