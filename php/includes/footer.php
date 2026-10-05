<?php defined('TC_APP') || exit; ?>
</main>
<?php
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
      <p class="max-w-xs text-sm leading-relaxed text-slate-400">Your travel assistant for affordable flights, hotels and holiday packages.</p>
    </div>
    <?php foreach ($columns as $heading => $links): ?>
      <div>
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white"><?= e($heading) ?></h3>
        <ul class="space-y-2 text-sm">
          <?php foreach ($links as [$href, $label]): ?><li><a href="<?= e(url($href)) ?>" class="hover:text-white"><?= e($label) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="border-t border-white/10">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <p>© <?= gmdate('Y') ?> <?= e(config('site_name')) ?>. All rights reserved.</p>
      <p>Prices shown may change until booking is confirmed.</p>
    </div>
  </div>
</footer>
<script src="<?= e(asset('airports.js')) ?>" defer></script>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>
