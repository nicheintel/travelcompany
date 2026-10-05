<?php
require __DIR__ . '/includes/bootstrap.php';

$contacts = support_contacts();
$description = 'Answers about booking flights, hotels and packages with FareFinders: payment, tickets, changes, refunds and how to reach our travel assistants.';
$title = 'Help center';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
  <div class="flex flex-wrap gap-2">
    <?php foreach ([['help.php', 'Help center'], ['privacy.php', 'Privacy policy'], ['cookies.php', 'Cookie policy'], ['terms.php', 'Terms of use']] as [$href, $label]): ?>
      <a href="<?= e(url($href)) ?>" class="rounded-full px-3 py-1 text-sm font-medium <?= $href === 'help.php' ? 'bg-brand-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-brand-300' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <div class="mt-6 rounded-3xl bg-gradient-to-br from-brand-800 to-brand-600 p-8 text-white sm:p-10">
    <h1 class="text-3xl font-bold sm:text-4xl">How can we help?</h1>
    <p class="mt-2 max-w-2xl text-brand-100">Answers to common questions about booking, payment, tickets and changes. Can't find what you need? Our travel assistants are here to help.</p>
  </div>

  <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([
        ['account.php', 'plane', 'My trips', 'See your bookings, pay, and view confirmation codes.'],
        ['help.php#faq', 'tag', 'Payments & refunds', 'How paying works, and how refunds are handled.'],
        ['terms.php#changes', 'calendar', 'Changes & cancellations', 'What you can change before and after paying.'],
        ['settings.php', 'shield', 'Account & password', 'Update your name, email or password.'],
    ] as [$href, $ic, $h, $d]): ?>
      <a href="<?= e(url($href)) ?>" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><?= icon($ic, 20) ?></span>
        <p class="mt-3 font-semibold text-slate-900"><?= e($h) ?></p><p class="mt-1 text-sm text-slate-600"><?= e($d) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_320px]">
    <section id="faq" class="scroll-mt-24">
      <h2 class="text-2xl font-bold text-slate-900">Frequently asked questions</h2>
      <div class="mt-5 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
        <?php foreach (faq_items() as [$q, $a]): ?>
          <details class="group p-5">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900"><?= e($q) ?><span class="text-xl text-brand-600 transition group-open:rotate-45">+</span></summary>
            <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= e($a) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
    <aside class="h-fit space-y-6 lg:sticky lg:top-24">
      <?= support_card() ?: '<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold text-slate-900">Need help?</h2><p class="mt-1 text-sm text-slate-600">Sign in and open your trip — our travel assistants will contact you about your booking.</p></section>' ?>
      <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm">
        <h2 class="font-semibold text-slate-900">Policies</h2>
        <ul class="mt-3 space-y-2">
          <li><a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('terms.php')) ?>">Terms of use</a></li>
          <li><a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('privacy.php')) ?>">Privacy policy</a></li>
          <li><a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('cookies.php')) ?>">Cookie policy</a></li>
        </ul>
      </section>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
