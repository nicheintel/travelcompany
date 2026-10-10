<?php
require __DIR__ . '/includes/bootstrap.php';

$kind = (string) ($_GET['kind'] ?? '');
if (!in_array($kind, BOOKING_KINDS, true)) not_found();
$user = require_verified_user();
if (ip_throttled('quote', 60, 600)) {
    http_response_code(429);
    exit(t('Too many requests. Please wait a few minutes and try again.'));
}
$params = $_GET;
unset($params['kind']);
$quote = build_quote($kind, $params);

$errors = [];
$message = null;
$values = [];
// Flights and packages need the details airlines ask for; hotels only need the lead guest's name.
$airTravel = $quote && $kind !== 'hotel';
if ($quote && is_post()) {
    verify_csrf();
    $values = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
    if ((int) post('expected_total') !== $quote['total']) {
        $message = t('The price has changed to {price}. Please review and confirm again.', ['price' => money($quote['total'])]);
    } else {
        [$travelers, $errors] = validate_travelers($quote, $values, $airTravel);
        [[$contactName, $email, $phone], $contactErrors] = validate_contact($values);
        $errors += $contactErrors;
        if (!$user['verified']) {
            $message = t('Please confirm your email address first — open the link we emailed you.');
        } elseif ($errors) {
            $message = t('Please fix the highlighted fields.');
        } elseif (rate_limited('book:' . $user['id'], 20) || ip_throttled('book', 30, 86400)) {
            $message = t("You've made a lot of reservations today. Please contact us if you need more.");
        } else {
            rate_hit('book:' . $user['id'], 86400);
            $ref = create_booking($user['id'], $quote, $travelers, $email, $phone, $contactName);
            notify_booking('reserved', $ref);
            redirect(url('trip.php', ['ref' => $ref, 'new' => 1]));
        }
    }
}

$back = ['flight' => ['flights.php', t('Back to flight results')], 'hotel' => ['hotels.php', t('Back to hotel results')], 'package' => ['packages.php', t('Back to packages')]][$kind];
$title = 'Complete your booking';
require __DIR__ . '/includes/header.php';

if (!$quote): ?>
  <div class="mx-auto max-w-xl px-4 py-24 text-center">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(t('This deal is no longer available')) ?></h1>
    <p class="mt-2 text-slate-600"><?= e(t('Prices and availability change quickly. Please search again.')) ?></p>
    <a href="<?= e(url($back[0])) ?>" class="mt-6 inline-block rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white"><?= e(t('Search again')) ?></a>
  </div>
<?php require __DIR__ . '/includes/footer.php'; exit; endif;

[$firstName, $lastName] = array_pad(explode(' ', $user['name'], 2), 2, '');
$v = fn(string $k, string $default = '') => $values[$k] ?? $default;
$actionUrl = url('book.php') . '?kind=' . $kind . '&' . $quote['query'];
parse_str($quote['query'], $qp);
?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url($back[0]) . ($kind === 'package' ? '' : '?' . $quote['query'])) ?>" class="hover:text-brand-700">← <?= e($back[1]) ?></a></nav>
  <h1 class="text-3xl font-bold text-slate-900"><?= e(t('Complete your booking')) ?></h1>
  <p class="mt-1 text-slate-600"><?= e(t('Almost there — just a few details and your trip is reserved.')) ?></p>
  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <?php if ($kind === 'package'): ?>
        <form method="get" action="<?= e(url('book.php')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Trip options')) ?></h2>
          <input type="hidden" name="kind" value="package"><input type="hidden" name="id" value="<?= e($qp['id']) ?>">
          <p class="mt-1 text-sm text-slate-500"><?= e(t('Choose any departure date from {first} to {last}.', ['first' => fmt_date($quote['package']['first']), 'last' => fmt_date($quote['package']['last'])])) ?></p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_0.8fr_auto]">
            <label class="<?= FIELD_BOX ?>"><span class="<?= FIELD_LABEL ?>"><?= e(t('Departure')) ?></span>
              <span class="flex items-center gap-2"><?= icon('calendar', 16, 'shrink-0 text-brand-500') ?>
              <input type="date" name="depart" value="<?= e($qp['depart']) ?>" min="<?= e($quote['package']['first']) ?>" max="<?= e($quote['package']['last']) ?>" required class="<?= FIELD_INPUT ?>"></span></label>
            <label class="flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5">
              <span class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= e(t('Travelers')) ?></span>
              <select name="adults" class="bg-transparent text-base font-semibold text-slate-900 outline-none">
                <?php for ($n = 1; $n <= 6; $n++): ?><option value="<?= $n ?>"<?= (int) $qp['adults'] === $n ? ' selected' : '' ?>><?= e(tn($n, '{n} adult', '{n} adults')) ?></option><?php endfor; ?>
              </select>
            </label>
            <button type="submit" class="rounded-xl px-5 py-3 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50"><?= e(t('Update price')) ?></button>
          </div>
        </form>
      <?php endif; ?>

      <form method="post" action="<?= e($actionUrl) ?>" class="space-y-6" novalidate data-pending-form>
        <?= csrf_field() ?><input type="hidden" name="expected_total" value="<?= $quote['total'] ?>">
        <?= alert_box($message) ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(count($quote['slots']) === 1 && !$quote['slots'][0]['dob'] ? t('Lead guest') : t('Traveler details')) ?></h2>
          <p class="mt-1 text-sm text-slate-500"><?= e(t("Names must match each traveler's passport or government ID.")) ?></p>
          <div class="mt-5 space-y-6">
            <?php foreach ($quote['slots'] as $i => $slot): ?>
              <?= traveler_fieldset($i, $slot, fn(string $k) => $v($k, ['t0_first' => $firstName, 't0_last' => $lastName][$k] ?? ''), $errors, $airTravel, $kind) ?>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Contact details')) ?></h2>
          <p class="mt-1 text-sm text-slate-500"><?= e(t("We'll email you the link to review and pay, so please double-check the address.")) ?></p>
          <div class="mt-5"><?= contact_fields(fn(string $k) => $v($k, ['contact_name' => $user['name'], 'email' => $user['email']][$k] ?? ''), $errors) ?></div>
        </section>
        <div class="rounded-2xl bg-brand-50 p-5 text-sm text-brand-900 ring-1 ring-brand-100">
          <p class="font-semibold"><?= e(payments_enabled() || gcash_enabled() ? t('Reserve, then pay securely') : t('Reserve now, pay later')) ?></p>
          <p class="mt-1 text-brand-800"><?= e(payments_enabled() || gcash_enabled()
              ? t("Nothing is charged yet. We'll email you a link to review your trip details and pay securely.")
              : t("Nothing is charged yet. We'll email you a link to review your trip details, and a travel assistant will contact you within 24 hours to arrange payment.")) ?></p>
        </div>
        <p class="text-xs text-slate-500"><?= th('By reserving you agree to our {terms}, including the airline and hotel rules for changes and refunds.', [], ['terms' => '<a class="font-semibold text-brand-700 hover:underline" href="' . e(url('terms.php')) . '#bookings">' . e(t('booking terms')) . '</a>']) ?></p>
        <?php if ($user['verified']): ?>
          <?= submit_button(t('Reserve trip · {total}', ['total' => money($quote['total'])]), t('Reserving…')) ?>
          <?php if (($hint = price_hint($quote['total'])) !== ''): ?><p class="text-center text-xs text-slate-500"><?= e($hint) ?><?php if (current_currency() !== 'USD'): ?> · <?= e(t('You will be charged in US dollars.')) ?><?php endif; ?></p><?php endif; ?>
        <?php else: ?>
          <button type="button" disabled class="w-full cursor-not-allowed rounded-xl bg-slate-300 py-3.5 font-bold text-white"><?= e(t('Confirm your email to reserve')) ?></button>
        <?php endif; ?>
      </form>
      <?php if (!$user['verified']): ?>
        <form method="post" action="<?= e(url('verify-email.php')) ?>" class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200"><?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
          <p class="font-semibold"><?= e(t('One last step: confirm your email address')) ?></p>
          <p class="mt-1"><?= th('We sent a link to {email}. Open it, then come back to this page to reserve. Check your spam folder too.', [], ['email' => '<strong>' . e($user['email']) . '</strong>']) ?></p>
          <button type="submit" class="mt-3 rounded-lg bg-amber-500 px-4 py-2 font-semibold text-white hover:bg-amber-600"><?= e(t('Send me a new link')) ?></button>
        </form>
      <?php endif; ?>
    </div>
    <aside class="h-fit space-y-3 lg:sticky lg:top-20"><?= admin_cost_line($quote['supplier'], $quote['subtotal']) ?><?= trip_summary($quote) ?></aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
