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
<script type="application/json" id="tc-i18n"><?= json_encode(js_strings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(asset('airports.js')) ?>" defer></script>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>
