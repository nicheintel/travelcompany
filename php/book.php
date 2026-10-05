<?php
require __DIR__ . '/includes/bootstrap.php';

$kind = (string) ($_GET['kind'] ?? '');
if (!in_array($kind, BOOKING_KINDS, true)) not_found();
$user = require_user();
if (ip_throttled('quote', 60, 600)) {
    http_response_code(429);
    exit('Too many requests. Please wait a few minutes and try again.');
}
$params = $_GET;
unset($params['kind']);
$quote = build_quote($kind, $params);

$errors = [];
$message = null;
$values = [];
if ($quote && is_post()) {
    verify_csrf();
    $values = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
    if ((int) post('expected_total') !== $quote['total']) {
        $message = 'The price has changed to ' . money($quote['total']) . '. Please review and confirm again.';
    } else {
        $travelers = [];
        $nameRe = '/^\p{L}[\p{L}\p{M}\' .-]*$/u';
        foreach ($quote['slots'] as $i => $slot) {
            $first = $values["t{$i}_first"] ?? '';
            $last = $values["t{$i}_last"] ?? '';
            $dob = $values["t{$i}_dob"] ?? '';
            if ($first === '' || mb_strlen($first) > 50 || !preg_match($nameRe, $first)) $errors["t{$i}_first"] = 'Enter a first name as it appears on the passport.';
            if ($last === '' || mb_strlen($last) > 50 || !preg_match($nameRe, $last)) $errors["t{$i}_last"] = 'Enter a last name as it appears on the passport.';
            if ($slot['dob']) {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
                $start = new DateTimeImmutable($quote['start_date']);
                // Age on the travel date; -1 for invalid or future dates.
                $age = $d && $d->format('Y-m-d') === $dob && $d <= $start ? $d->diff($start)->y : -1;
                if ($age < 0 || $age > 120) $errors["t{$i}_dob"] = 'Enter a valid date of birth.';
                elseif (str_starts_with($slot['label'], 'Child') && ($age < 2 || $age > 11)) $errors["t{$i}_dob"] = 'Children must be 2–11 years old on the travel date.';
                elseif (str_starts_with($slot['label'], 'Adult') && $age < 12) $errors["t{$i}_dob"] = 'Adults must be 12 or older on the travel date.';
            }
            $travelers[] = ['first' => $first, 'last' => $last] + ($slot['dob'] ? ['dob' => $dob] : []);
        }
        $email = normalize_email($values['email'] ?? '');
        $phone = $values['phone'] ?? '';
        if (!valid_email($email)) $errors['email'] = 'Enter a valid email address.';
        if (!preg_match('/^\+?[0-9][0-9\s().-]{6,19}$/', $phone)) $errors['phone'] = 'Enter a valid phone number, including country code.';
        if ($errors) {
            $message = 'Please fix the highlighted fields.';
        } elseif (rate_limited('book:' . $user['id'], 20) || ip_throttled('book', 30, 86400)) {
            $message = "You've made a lot of reservations today. Please contact us if you need more.";
        } else {
            rate_hit('book:' . $user['id'], 86400);
            $ref = create_booking($user['id'], $quote, $travelers, $email, $phone);
            notify_booking('reserved', $ref);
            redirect(url('trip.php', ['ref' => $ref, 'new' => 1]));
        }
    }
}

$back = ['flight' => ['flights.php', 'flight results'], 'hotel' => ['hotels.php', 'hotel results'], 'package' => ['packages.php', 'packages']][$kind];
$title = 'Complete your booking';
require __DIR__ . '/includes/header.php';

if (!$quote): ?>
  <div class="mx-auto max-w-xl px-4 py-24 text-center">
    <h1 class="text-2xl font-bold text-slate-900">This deal is no longer available</h1>
    <p class="mt-2 text-slate-600">Prices and availability change quickly. Please search again.</p>
    <a href="<?= e(url($back[0])) ?>" class="mt-6 inline-block rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white">Search again</a>
  </div>
<?php require __DIR__ . '/includes/footer.php'; exit; endif;

[$firstName, $lastName] = array_pad(explode(' ', $user['name'], 2), 2, '');
$v = fn(string $k, string $default = '') => $values[$k] ?? $default;
$actionUrl = url('book.php') . '?kind=' . $kind . '&' . $quote['query'];
parse_str($quote['query'], $qp);
?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url($back[0]) . ($kind === 'package' ? '' : '?' . $quote['query'])) ?>" class="hover:text-brand-700">← Back to <?= $back[1] ?></a></nav>
  <h1 class="text-3xl font-bold text-slate-900">Complete your booking</h1>
  <p class="mt-1 text-slate-600">Almost there — just a few details and your trip is reserved.</p>
  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <?php if ($kind === 'package'): ?>
        <form method="get" action="<?= e(url('book.php')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900">Trip options</h2>
          <input type="hidden" name="kind" value="package"><input type="hidden" name="id" value="<?= e($qp['id']) ?>">
          <p class="mt-1 text-sm text-slate-500">Choose any departure date from <?= e(fmt_date($quote['package']['first'])) ?> to <?= e(fmt_date($quote['package']['last'])) ?>.</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_0.8fr_auto]">
            <label class="<?= FIELD_BOX ?>"><span class="<?= FIELD_LABEL ?>">Departure</span>
              <span class="flex items-center gap-2"><?= icon('calendar', 16, 'shrink-0 text-brand-500') ?>
              <input type="date" name="depart" value="<?= e($qp['depart']) ?>" min="<?= e($quote['package']['first']) ?>" max="<?= e($quote['package']['last']) ?>" required class="<?= FIELD_INPUT ?>"></span></label>
            <label class="flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5">
              <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Travelers</span>
              <select name="adults" class="bg-transparent text-base font-semibold text-slate-900 outline-none">
                <?php for ($n = 1; $n <= 6; $n++): ?><option value="<?= $n ?>"<?= (int) $qp['adults'] === $n ? ' selected' : '' ?>><?= plural($n, 'adult') ?></option><?php endfor; ?>
              </select>
            </label>
            <button type="submit" class="rounded-xl px-5 py-3 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">Update price</button>
          </div>
        </form>
      <?php endif; ?>

      <form method="post" action="<?= e($actionUrl) ?>" class="space-y-6" novalidate data-pending-form>
        <?= csrf_field() ?><input type="hidden" name="expected_total" value="<?= $quote['total'] ?>">
        <?= alert_box($message) ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= count($quote['slots']) === 1 && !$quote['slots'][0]['dob'] ? 'Lead guest' : 'Traveler details' ?></h2>
          <p class="mt-1 text-sm text-slate-500">Names must match each traveler's passport or government ID.</p>
          <div class="mt-5 space-y-6">
            <?php foreach ($quote['slots'] as $i => $slot): ?>
              <fieldset class="space-y-4 border-t border-slate-100 pt-5 first:border-0 first:pt-0">
                <legend class="mb-3 text-sm font-semibold text-brand-700"><?= e($slot['label']) ?></legend>
                <div class="grid gap-4 sm:grid-cols-2">
                  <?= text_field("t{$i}_first", 'First name', $v("t{$i}_first", $i === 0 ? $firstName : ''), 'text', $errors["t{$i}_first"] ?? null, ['autocomplete' => $i === 0 ? 'given-name' : 'off']) ?>
                  <?= text_field("t{$i}_last", 'Last name', $v("t{$i}_last", $i === 0 ? $lastName : ''), 'text', $errors["t{$i}_last"] ?? null, ['autocomplete' => $i === 0 ? 'family-name' : 'off']) ?>
                </div>
                <?php if ($slot['dob']): ?><div class="sm:w-1/2 sm:pr-2"><?= text_field("t{$i}_dob", 'Date of birth', $v("t{$i}_dob"), 'date', $errors["t{$i}_dob"] ?? null) ?></div><?php endif; ?>
              </fieldset>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900">Contact details</h2>
          <p class="mt-1 text-sm text-slate-500">We'll send your confirmation and updates here.</p>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <?= text_field('email', 'Email', $v('email', $user['email']), 'email', $errors['email'] ?? null, ['autocomplete' => 'email']) ?>
            <?= text_field('phone', 'Mobile phone', $v('phone'), 'tel', $errors['phone'] ?? null, ['autocomplete' => 'tel', 'placeholder' => '+1 555 123 4567']) ?>
          </div>
        </section>
        <div class="rounded-2xl bg-brand-50 p-5 text-sm text-brand-900 ring-1 ring-brand-100">
          <p class="font-semibold"><?= payments_enabled() ? 'Reserve, then pay securely' : 'Reserve now, pay later' ?></p>
          <p class="mt-1 text-brand-800"><?= payments_enabled()
              ? 'Nothing is charged yet. Next you can pay online' . (payment_provider() === 'paypal' ? ' with PayPal or a card' : ' by card') . ' to confirm right away — or pay later with help from a travel assistant.'
              : 'No payment is taken today. A travel assistant will contact you within 24 hours to confirm availability and arrange payment.' ?></p>
        </div>
        <?= submit_button('Reserve trip · ' . money($quote['total']), 'Reserving…') ?>
      </form>
    </div>
    <aside class="h-fit space-y-3 lg:sticky lg:top-20"><?= admin_cost_line($quote['supplier'], $quote['subtotal']) ?><?= trip_summary($quote) ?></aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
