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
// Checked bag not included in the fare (real allowance from the supplier): travelers can ask us to add one.
// When the supplier says what's included we show it; otherwise the travel assistant confirms it.
$bagInfo = $quote && $kind === 'flight' ? ($quote['baggage'] ?? null) : null;
$bagOffer = $quote && $kind !== 'hotel' && empty($bagInfo['checked']);
if ($quote && is_post()) {
    verify_csrf();
    $values = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
    if ((int) post('expected_total') !== $quote['total']) {
        $message = t('The price has changed to {price}. Please review and confirm again.', ['price' => money($quote['total'])]);
    } else {
        [$travelers, $errors] = validate_travelers($quote, $values, $airTravel, $bagOffer);
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
        <?php if ($airTravel): $bag = $bagInfo; ?>
          <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Baggage allowance')) ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= e($bag ? t('What the airline includes in this fare, for each traveler.') : t("Your travel assistant will confirm what this fare includes. Need a checked bag? Add it below and we'll confirm the airline's price.")) ?></p>
            <div class="mt-4 overflow-x-auto">
              <table class="w-full min-w-[28rem] text-sm">
                <thead><tr class="border-b border-slate-200 text-left text-slate-500">
                  <th class="py-2 pr-3 font-medium"><?= e(t('Traveler')) ?></th>
                  <?php if ($bag): ?><th class="py-2 pr-3 font-medium"><?= e(t('Carry-on bag')) ?></th><?php endif; ?>
                  <th class="py-2 font-medium"><?= e(t('Checked bag')) ?></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($quote['slots'] as $i => $slot): $infant = str_starts_with($slot['label'], 'Infant'); ?>
                    <tr>
                      <td class="py-3 pr-3 font-medium text-slate-900"><?= e(slot_label($slot['label'])) ?></td>
                      <?php if ($bag): ?><td class="py-3 pr-3"><?= $infant ? '<span class="text-slate-500">' . e(t('Not included for infants')) . '</span>' : ($bag['carry_on'] ? '<span class="font-semibold text-emerald-700">✓ ' . e(t('Included')) . '</span>' : '<span class="text-slate-600">' . e(t('Not included')) . '</span>') ?></td><?php endif; ?>
                      <td class="py-3">
                        <?php if ($infant): ?><span class="text-slate-500"><?= e(t('Not included for infants')) ?></span>
                        <?php elseif (!empty($bag['checked'])): ?><span class="font-semibold text-emerald-700">✓ <?= e(t('Included')) ?></span>
                        <?php else: ?>
                          <div class="group/bag">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                              <span class="font-semibold text-accent-700 group-has-[:checked]/bag:hidden"><?= e($bag ? t('No free checked bag') : t('Not confirmed yet')) ?></span>
                              <span class="hidden font-semibold text-brand-700 group-has-[:checked]/bag:inline">✓ <?= e(t('Checked bag requested')) ?></span>
                              <label class="relative inline-flex cursor-pointer select-none items-center rounded-lg border border-brand-300 bg-white text-sm font-semibold text-brand-700 hover:bg-brand-50 has-[:checked]:border-slate-300 has-[:checked]:text-slate-600 has-[:checked]:hover:bg-slate-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-300">
                                <input type="checkbox" name="t<?= $i ?>_bag" value="1"<?= !empty($values["t{$i}_bag"]) ? ' checked' : '' ?> class="peer sr-only" data-bag-toggle aria-label="<?= e(t('Add a checked bag for {traveler}', ['traveler' => slot_label($slot['label'])])) ?>">
                                <span class="px-3 py-1.5 peer-checked:hidden">＋ <?= e(t('Add checked bag')) ?></span>
                                <span class="hidden px-3 py-1.5 peer-checked:inline"><?= e(t('Remove')) ?></span>
                              </label>
                            </div>
                            <?php $from = isset($bag['checked_from']) ? price((int) ceil($bag['checked_from'])) : null; ?>
                            <span class="mt-1 block text-xs text-slate-500 group-has-[:checked]/bag:hidden"><?= e($from ? t('Airline price from {price} if you add one', ['price' => $from]) : t("Add one and we'll confirm the airline's price")) ?></span>
                            <span class="mt-1 hidden text-xs text-slate-600 group-has-[:checked]/bag:block"><?= e($from ? t("We'll confirm the airline's price (from {price}) and add it to your total before you pay.", ['price' => $from]) : t("We'll confirm the airline's price and add it to your total before you pay.")) ?></span>
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($bagOffer): ?><p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600"><?= e(t("Bag prices are set by the airline. If you request a bag, we'll confirm the exact price and add it to your total before you pay — nothing is charged for it without your OK.")) ?></p><?php endif; ?>
          </section>
        <?php endif; ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Contact details')) ?></h2>
          <p class="mt-1 text-sm text-slate-500"><?= e(t("We'll send your confirmation and updates here.")) ?></p>
          <div class="mt-5"><?= contact_fields(fn(string $k) => $v($k, ['contact_name' => $user['name'], 'email' => $user['email']][$k] ?? ''), $errors) ?></div>
        </section>
        <div class="rounded-2xl bg-brand-50 p-5 text-sm text-brand-900 ring-1 ring-brand-100">
          <p class="font-semibold"><?= e(payments_enabled() ? t('Reserve, then pay securely') : t('Reserve now, pay later')) ?></p>
          <p class="mt-1 text-brand-800"><?= e(payments_enabled()
              ? (payment_provider() === 'paypal'
                  ? t('Nothing is charged yet. Next you can pay online with PayPal or a card to confirm right away — or pay later with help from a travel assistant.')
                  : t('Nothing is charged yet. Next you can pay online by card to confirm right away — or pay later with help from a travel assistant.'))
              : t('No payment is taken today. A travel assistant will contact you within 24 hours to confirm availability and arrange payment.')) ?></p>
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
