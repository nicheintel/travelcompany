<?php
require __DIR__ . '/includes/bootstrap.php';

$kind = (string) ($_GET['kind'] ?? '');
if (!in_array($kind, BOOKING_KINDS, true)) not_found();
$user = require_user();
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
        $travelers = [];
        $nameRe = '/^\p{L}[\p{L}\p{M}\' .-]*$/u';
        foreach ($quote['slots'] as $i => $slot) {
            $first = $values["t{$i}_first"] ?? '';
            $noLast = $airTravel && !empty($values["t{$i}_nolast"]);
            $last = $noLast ? '' : ($values["t{$i}_last"] ?? '');
            $dob = $values["t{$i}_dob"] ?? '';
            if ($first === '' || mb_strlen($first) > 50 || !preg_match($nameRe, $first)) $errors["t{$i}_first"] = t('Enter a first name as it appears on the passport.');
            if (!$noLast && ($last === '' || mb_strlen($last) > 50 || !preg_match($nameRe, $last))) $errors["t{$i}_last"] = t('Enter a last name as it appears on the passport.');
            $extra = [];
            if ($airTravel) {
                // What airlines need to issue a ticket (and check-in needs to match).
                $gender = $values["t{$i}_gender"] ?? '';
                $nat = strtoupper($values["t{$i}_nationality"] ?? '');
                if (!in_array($gender, ['M', 'F'], true)) $errors["t{$i}_gender"] = t('Choose the gender shown on the passport or ID.');
                if (!isset(COUNTRY_DIAL[$nat])) $errors["t{$i}_nationality"] = t('Choose a nationality.');
                $extra = ['gender' => $gender, 'nationality' => $nat] + ($noLast ? ['no_last_name' => true] : []);
                $ff = strtoupper(trim($values["t{$i}_ff"] ?? ''));
                if ($ff !== '') {
                    if (!preg_match('/^[A-Z0-9][A-Z0-9 -]{3,29}$/', $ff)) $errors["t{$i}_ff"] = t('Enter the frequent flyer number (letters and numbers only), or leave it empty.');
                    $extra['frequent_flyer'] = $ff;
                }
                if ($bagOffer && !str_starts_with($slot['label'], 'Infant') && !empty($values["t{$i}_bag"])) $extra['extra_bag'] = true;
            }
            if ($slot['dob']) {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
                $start = new DateTimeImmutable($quote['start_date']);
                // Age on the travel date; -1 for invalid or future dates.
                $age = $d && $d->format('Y-m-d') === $dob && $d <= $start ? $d->diff($start)->y : -1;
                if ($age < 0 || $age > 120) $errors["t{$i}_dob"] = t('Enter a valid date of birth.');
                elseif (str_starts_with($slot['label'], 'Child') && ($age < 2 || $age > 11)) $errors["t{$i}_dob"] = t('Children must be 2–11 years old on the travel date.');
                elseif (str_starts_with($slot['label'], 'Adult') && $age < 12) $errors["t{$i}_dob"] = t('Adults must be 12 or older on the travel date.');
                elseif (str_starts_with($slot['label'], 'Infant') && $d->diff(new DateTimeImmutable($quote['end_date'] ?? $quote['start_date']))->y >= 2) $errors["t{$i}_dob"] = t('Infants must be under 2 for the whole trip — book them as a child instead.');
            }
            $travelers[] = ['first' => $first, 'last' => $last] + ($slot['dob'] ? ['dob' => $dob] : []) + $extra;
        }
        $contactName = preg_replace('/\s+/', ' ', $values['contact_name'] ?? '');
        $email = normalize_email($values['email'] ?? '');
        $phoneCountry = strtoupper($values['phone_country'] ?? '');
        $phoneNumber = preg_replace('/[\s().-]/', '', $values['phone'] ?? '');
        if (mb_strlen($contactName) < 2 || mb_strlen($contactName) > 100 || !preg_match($nameRe, $contactName)) $errors['contact_name'] = t('Enter the name of the person we should contact.');
        if (!valid_email($email)) $errors['email'] = t('Enter a valid email address.');
        if (!isset(COUNTRY_DIAL[$phoneCountry])) $errors['phone'] = t('Choose the country code.');
        elseif (!preg_match('/^\+?[0-9]{4,15}$/', $phoneNumber)) $errors['phone'] = t('Enter a valid mobile number.');
        // Stored as "+63 9171234567" (numbers typed with their own +code are kept as typed).
        $phone = str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . (COUNTRY_DIAL[$phoneCountry] ?? '') . ' ' . ltrim($phoneNumber, '0');
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
              <fieldset class="space-y-4 border-t border-slate-100 pt-5 first:border-0 first:pt-0">
                <legend class="mb-3 text-sm font-semibold text-brand-700"><?= e(slot_label($slot['label'])) ?></legend>
                <div class="grid gap-4 sm:grid-cols-2">
                  <?= text_field("t{$i}_first", $airTravel ? t('First name (as on passport)') : t('First name'), $v("t{$i}_first", $i === 0 ? $firstName : ''), 'text', $errors["t{$i}_first"] ?? null, ['autocomplete' => $i === 0 ? 'given-name' : 'off']) ?>
                  <div class="space-y-1.5">
                    <?= text_field("t{$i}_last", $airTravel ? t('Last name (surname)') : t('Last name'), $v("t{$i}_last", $i === 0 ? $lastName : ''), 'text', $errors["t{$i}_last"] ?? null, ['autocomplete' => $i === 0 ? 'family-name' : 'off']) ?>
                    <?php if ($airTravel): ?><label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="t<?= $i ?>_nolast" value="1"<?= !empty($values["t{$i}_nolast"]) ? ' checked' : '' ?> class="h-4 w-4 accent-brand-600"> <?= e(t('No surname on passport')) ?></label><?php endif; ?>
                  </div>
                </div>
                <?php if ($airTravel): ?>
                  <div class="grid gap-4 sm:grid-cols-3">
                    <?= select_field("t{$i}_gender", t('Gender on passport/ID'), ['M' => t('Male'), 'F' => t('Female')], $v("t{$i}_gender"), $errors["t{$i}_gender"] ?? null, t('Choose…')) ?>
                    <?= text_field("t{$i}_dob", t('Date of birth'), $v("t{$i}_dob"), 'date', $errors["t{$i}_dob"] ?? null) ?>
                    <?= select_field("t{$i}_nationality", t('Nationality'), country_list(), $v("t{$i}_nationality"), $errors["t{$i}_nationality"] ?? null, t('Choose…')) ?>
                  </div>
                  <?php if ($kind === 'flight'): ?>
                    <details class="group"<?= $v("t{$i}_ff") !== '' || isset($errors["t{$i}_ff"]) ? ' open' : '' ?>>
                      <summary class="cursor-pointer text-sm font-semibold text-brand-700 hover:underline"><?= e(t('Frequent flyer number (optional)')) ?></summary>
                      <div class="mt-3 sm:w-1/2 sm:pr-2"><?= text_field("t{$i}_ff", t('Airline and number, e.g. PR 1234567'), $v("t{$i}_ff"), 'text', $errors["t{$i}_ff"] ?? null, ['autocomplete' => 'off']) ?></div>
                    </details>
                  <?php endif; ?>
                <?php elseif ($slot['dob']): ?><div class="sm:w-1/2 sm:pr-2"><?= text_field("t{$i}_dob", t('Date of birth'), $v("t{$i}_dob"), 'date', $errors["t{$i}_dob"] ?? null) ?></div><?php endif; ?>
              </fieldset>
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
                          <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <?php if ($bag): ?><span class="font-semibold text-accent-600"><?= e(t('No free checked bag')) ?></span><?php endif; ?>
                            <label class="relative inline-flex cursor-pointer select-none items-center rounded-lg border border-brand-300 bg-white text-sm font-semibold text-brand-700 hover:bg-brand-50 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-300">
                              <input type="checkbox" name="t<?= $i ?>_bag" value="1"<?= !empty($values["t{$i}_bag"]) ? ' checked' : '' ?> class="peer sr-only" aria-label="<?= e(t('Add a checked bag for {traveler}', ['traveler' => slot_label($slot['label'])])) ?>">
                              <span class="px-3 py-1.5 peer-checked:hidden">＋ <?= e(t('Add checked bag')) ?></span>
                              <span class="hidden px-3 py-1.5 peer-checked:inline">✓ <?= e(t('Checked bag added')) ?></span>
                            </label>
                          </div>
                          <span class="mt-1 block text-xs text-slate-500"><?= e(isset($bag['checked_from']) ? t('Airline price from {price} — confirmed before you pay', ['price' => price((int) ceil($bag['checked_from']))]) : t('Airline price confirmed before you pay')) ?></span>
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
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <?= text_field('contact_name', t('Contact name'), $v('contact_name', $user['name']), 'text', $errors['contact_name'] ?? null, ['autocomplete' => 'name']) ?>
            <?= text_field('email', t('Email'), $v('email', $user['email']), 'email', $errors['email'] ?? null, ['autocomplete' => 'email']) ?>
            <div class="sm:col-span-2">
              <label for="f_phone" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e(t('Mobile phone')) ?></label>
              <div class="flex gap-2">
                <select name="phone_country" aria-label="<?= e(t('Country code')) ?>" class="w-32 shrink-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 sm:w-44">
                  <?php $pc = $v('phone_country', default_phone_country()); foreach (country_list() as $cc => $cname): ?><option value="<?= e($cc) ?>"<?= $cc === $pc ? ' selected' : '' ?>><?= e("+" . COUNTRY_DIAL[$cc] . " · $cname") ?></option><?php endforeach; ?>
                </select>
                <input id="f_phone" name="phone" type="tel" value="<?= e($v('phone')) ?>" autocomplete="tel-national" placeholder="917 123 4567"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="f_phone_err"' : '' ?> class="min-w-0 flex-1 rounded-xl border bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-2 <?= isset($errors['phone']) ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100' ?>">
              </div>
              <?php if (isset($errors['phone'])): ?><p id="f_phone_err" class="mt-1.5 text-sm text-red-600"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
          </div>
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
