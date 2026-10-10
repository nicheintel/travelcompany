<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_user();
$ref = (string) ($_GET['ref'] ?? '');
$booking = user_booking($user['id'], $ref);
if (!$booking) not_found();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'cancel' && customer_cancel($user['id'], $ref)) {
        notify_booking('cancelled', $ref);
        redirect(url('trip.php', ['ref' => $ref]));
    }
    // Before payment the customer can still correct the travelers and contact person, and ask for checked bags.
    $canEdit = $booking['status'] === 'reserved' && empty($booking['gcash_ref']);
    if ($action === 'travelers' && $canEdit) {
        $values = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
        [$travelers, $errors] = validate_travelers($booking['quote'], $values, $booking['kind'] !== 'hotel');
        if ($errors) {
            $_SESSION['trip_form'] = ['travelers', $values, $errors];
            redirect(url('trip.php', ['ref' => $ref, 'edit' => 'travelers']) . '#travelers');
        }
        foreach ($travelers as $i => &$t) if (!empty($booking['travelers'][$i]['extra_bag'])) $t['extra_bag'] = true; // bag requests stay
        unset($t);
        db_run("UPDATE bookings SET travelers_json = ? WHERE reference = ? AND status = 'reserved'", [json_encode($travelers, JSON_UNESCAPED_UNICODE), $ref]);
        $names = fn(array $list) => implode(', ', array_map(fn($x) => trim("{$x['first']} {$x['last']}"), $list));
        add_event($ref, $user['id'], 'note', 'Customer updated the traveler details before paying. Now: ' . $names($travelers) . ' (was: ' . $names($booking['travelers']) . ').');
        flash(t('Traveler details saved.'));
        redirect(url('trip.php', ['ref' => $ref]) . '#travelers');
    }
    if ($action === 'contact' && $canEdit) {
        $values = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
        [[$name, $email, $phone], $errors] = validate_contact($values);
        if ($errors) {
            $_SESSION['trip_form'] = ['contact', $values, $errors];
            redirect(url('trip.php', ['ref' => $ref, 'edit' => 'contact']) . '#contact');
        }
        db_run("UPDATE bookings SET contact_name = ?, contact_email = ?, contact_phone = ? WHERE reference = ? AND status = 'reserved'", [$name, $email, $phone, $ref]);
        add_event($ref, $user['id'], 'note', "Customer updated the contact person: $name, $email, $phone.");
        flash(t('Contact details saved.'));
        redirect(url('trip.php', ['ref' => $ref]) . '#contact');
    }
    if ($action === 'add_bag' && $canEdit && $booking['kind'] !== 'hotel' && empty($booking['quote']['baggage']['checked']) && ($booking['bag_status'] ?? null) === null) {
        $travelers = $booking['travelers'];
        $added = 0;
        foreach ((array) ($_POST['bag'] ?? []) as $i) {
            $i = (int) $i;
            if (isset($travelers[$i]) && empty($travelers[$i]['extra_bag']) && !str_starts_with($booking['quote']['slots'][$i]['label'] ?? '', 'Infant')) {
                $travelers[$i]['extra_bag'] = true;
                $added++;
            }
        }
        if (!$added) {
            flash(t('Choose who needs a checked bag.'), 'error');
            redirect(url('trip.php', ['ref' => $ref]) . '#baggage');
        }
        db_run("UPDATE bookings SET travelers_json = ?, bag_status = 'pending' WHERE reference = ? AND status = 'reserved'", [json_encode($travelers, JSON_UNESCAPED_UNICODE), $ref]);
        add_event($ref, $user['id'], 'note', 'Customer requested ' . plural($added, 'checked bag') . ' on the trip page — set the price before the customer pays.');
        notify_admins_bag($ref, $added);
        flash(t("Thanks! We'll confirm the airline's bag price and email you your new total, usually within a few hours."));
        redirect(url('trip.php', ['ref' => $ref]) . '#baggage');
    }
    // "Pay securely now": the customer agreed to the terms (and chose Travel Care and a tip or not), then pays.
    if ($action === 'checkout' && $booking['status'] === 'reserved' && empty($booking['gcash_ref'])) {
        if (empty($_POST['agree'])) {
            flash(t('Please tick the box to confirm you have checked your trip details.'), 'error');
            redirect(url('trip.php', ['ref' => $ref]) . '#pay');
        }
        $tip = post('tip') === 'other' ? trim(post('tip_other')) : post('tip');
        if (post('tip') === 'other' && (!preg_match('/^\d{1,4}$/', $tip) || (int) $tip < 1 || (int) $tip > TIP_MAX)) {
            flash(t('Enter a tip in whole US dollars, from 1 to {max}, or choose No tip.', ['max' => TIP_MAX]), 'error');
            redirect(url('trip.php', ['ref' => $ref]) . '#tip');
        }
        review_booking($ref, $user['id'], !empty($_POST['care']), preg_match('/^\d{1,4}$/', $tip) ? (int) $tip : 0);
        $booking = user_booking($user['id'], $ref);
        $wantsGcash = post('method') === 'gcash' || !payments_enabled();
        if ($wantsGcash && gcash_enabled() && gcash_for_booking($booking)) {
            redirect(url('trip.php', ['ref' => $ref, 'method' => 'gcash']) . '#gcash');
        }
        $action = 'pay';
    }
    if (in_array($action, ['pay', 'gcash'], true) && empty($booking['quote']['reviewed_at'])) {
        flash(t('Please check your trip details below before paying.'), 'error');
        redirect(url('trip.php', ['ref' => $ref]) . '#pay');
    }
    if ($action === 'gcash' && $booking['status'] === 'reserved' && gcash_enabled() && gcash_for_booking($booking) && ($booking['bag_status'] ?? null) !== 'pending') {
        // GCash reference numbers are 13 digits; allow spaces and a little slack for other formats.
        $gref = preg_replace('/[\s-]/', '', post('gcash_ref'));
        $pesos = gcash_amount($booking['total']);
        if (!preg_match('/^[0-9]{8,20}$/', $gref) || !$pesos) {
            flash(t('Enter the reference number from your GCash receipt (numbers only).'), 'error');
        } elseif (ip_throttled('gcash', 10, 3600)) {
            flash(t('Too many requests. Please wait a few minutes and try again.'), 'error');
        } elseif (submit_gcash($ref, $user['id'], $gref, $pesos)) {
            flash(t("Thanks! We'll check your GCash payment and confirm by email, usually within a few hours."));
        }
        redirect(url('trip.php', ['ref' => $ref, 'method' => 'gcash']) . '#pay');
    }
    if ($action === 'pay' && $booking['status'] === 'reserved' && payments_enabled() && ($booking['bag_status'] ?? null) !== 'pending') {
        try {
            if (payment_provider() === 'paypal') {
                [$orderId, $approveUrl] = paypal_create_order($booking);
                db_run("DELETE FROM settings WHERE name = 'last_payment_error'"); // working again
                db_run('UPDATE bookings SET payment_ref = ? WHERE reference = ?', [$orderId, $ref]);
                redirect($approveUrl);
            }
            $session = create_checkout_session($booking);
            db_run('UPDATE bookings SET payment_ref = ? WHERE reference = ?', [$session['id'], $ref]);
            redirect($session['url']);
        } catch (Throwable $e) {
            record_payment_error($ref, $e->getMessage());
            redirect(url('trip.php', ['ref' => $ref, 'payment' => 'error']));
        }
    }
    redirect(url('trip.php', ['ref' => $ref]));
}

// Back from PayPal / Stripe: confirm with the provider directly (never trust the URL),
// in case the webhook is late or not set up.
$returned = false;
$paypalOrder = (string) ($_GET['token'] ?? '');
if (($_GET['paypal'] ?? '') === 'return' && $paypalOrder !== '' && $booking['status'] === 'reserved' && paypal_enabled()) {
    $returned = true;
    try {
        if (paypal_capture($paypalOrder, $ref)) $booking = user_booking($user['id'], $ref);
    } catch (Throwable $e) {
        error_log("[paypal] capture for $ref failed: " . $e->getMessage());
    }
}
$sessionId = (string) ($_GET['session_id'] ?? '');
if ($sessionId !== '' && $booking['status'] === 'reserved' && stripe_enabled()) {
    $returned = true;
    try {
        $session = retrieve_checkout_session($sessionId);
        if (($session['metadata']['reference'] ?? $session['client_reference_id'] ?? null) === $ref) {
            apply_paid_session($session);
            $booking = user_booking($user['id'], $ref);
        }
    } catch (Throwable $e) {
        error_log("[payments] verify for $ref failed: " . $e->getMessage());
    }
}
$returned = $returned || $sessionId !== '' || ($_GET['paypal'] ?? '') === 'return';

$status = $booking['status'];
$isNew = ($_GET['new'] ?? '') === '1' && $status === 'reserved';
$justPaid = $returned && $status === 'paid';
$payFailed = $returned && $status === 'reserved';
$bagPending = $status === 'reserved' && ($booking['bag_status'] ?? null) === 'pending';
$canPay = $status === 'reserved' && payments_enabled() && !$bagPending;
$canGcash = $status === 'reserved' && !$bagPending && gcash_enabled() && (gcash_for_booking($booking) || $booking['gcash_ref']);
$provider = payment_provider();
$title = t('Trip {ref}', ['ref' => $ref]);
require __DIR__ . '/includes/header.php';
$check = '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">' . icon('check', 20) . '</span>';
?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url('account.php')) ?>" class="hover:text-brand-700">← <?= e(t('My trips')) ?></a></nav>
  <?php if ($isNew): ?>
    <div class="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><?= $check ?>
      <div><p class="text-lg font-semibold text-emerald-900"><?= e(t('Your trip is reserved!')) ?></p>
        <p class="mt-1 text-sm text-emerald-800"><?= e($canPay ? t('Pay securely below to confirm it now, or a travel assistant will contact you within 24 hours.') : t('A travel assistant will contact you at {email} within 24 hours to confirm availability and arrange payment.', ['email' => $booking['contact_email']])) ?>
          <?= th("We've emailed your confirmation to {email}. Reference: {ref}", ['email' => $booking['contact_email']], ['ref' => '<strong>' . e($ref) . '</strong>']) ?></p></div>
    </div>
  <?php endif; ?>
  <?php if ($justPaid): ?>
    <div class="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><?= $check ?>
      <div><p class="text-lg font-semibold text-emerald-900"><?= e(t('Payment received — thank you!')) ?></p><p class="mt-1 text-sm text-emerald-800"><?= e($booking['kind'] === 'hotel'
          ? t("We've emailed your receipt to {email}. A travel assistant is now issuing your room booking — you'll get your confirmation code by email, usually within a few hours.", ['email' => $booking['contact_email']])
          : t("We've emailed your receipt to {email}. A travel assistant is now issuing your tickets — you'll get your confirmation code by email, usually within a few hours.", ['email' => $booking['contact_email']])) ?></p></div>
    </div>
  <?php endif; ?>
  <?php if (($_GET['payment'] ?? '') === 'error' && $status === 'reserved'): ?>
    <div class="mb-6"><?= alert_box(t("We couldn't start the payment just now. Please try again in a moment.")) ?>
      <?php if (is_admin() && ($pe = last_payment_error()) && $pe['ref'] === $ref): ?>
        <p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Admin only — what PayPal said:</strong> <?= e($pe['message']) ?></p>
      <?php endif; ?></div>
  <?php elseif (($_GET['payment'] ?? '') === 'cancelled' && $status === 'reserved'): ?>
    <div class="mb-6"><?= alert_box(t("Payment cancelled — you haven't been charged. You can pay whenever you're ready.")) ?></div>
  <?php elseif ($payFailed): ?>
    <div class="mb-6"><?= alert_box(t("We couldn't confirm your payment yet. If money was taken, it will show here shortly — otherwise please try again.")) ?></div>
  <?php endif; ?>

  <div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold text-slate-900"><?= e(t('Trip {ref}', ['ref' => $ref])) ?></h1>
    <?php // Status labels are translated here, for display only; the stored status stays as it is.
    [$badgeCls, $badgeText] = match ($status) {
        'cancelled' => ['bg-slate-200 text-slate-600', t('Cancelled')],
        'ticketed' => ['bg-emerald-100 text-emerald-800', $booking['kind'] === 'hotel' ? t('Confirmed · booked') : t('Confirmed · ticket issued')],
        'paid' => ['bg-sky-100 text-sky-800', $booking['kind'] === 'hotel' ? t('Paid · booking your room') : t('Paid · issuing tickets')],
        default => ['bg-amber-100 text-amber-800', t('Reserved · awaiting payment')],
    }; ?>
    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide <?= $badgeCls ?>"><?= e($badgeText) ?></span>
  </div>
  <p class="mt-1 text-sm text-slate-500"><?= th('Booked {date}', [], ['date' => local_time($booking['created_at'], true)]) ?><?= $booking['paid_at'] ? ' · ' . th('Paid {date}', [], ['date' => local_time($booking['paid_at'], true)]) : '' ?><?= $booking['ticketed_at'] ? ' · ' . th('Confirmed {date}', [], ['date' => local_time($booking['ticketed_at'], true)]) : '' ?><?= $booking['cancelled_at'] ? ' · ' . th('Cancelled {date}', [], ['date' => local_time($booking['cancelled_at'], true)]) : '' ?></p>

  <?php if ($status === 'reserved'):
      $canEdit = empty($booking['gcash_ref']);
      $edit = $canEdit ? (string) ($_GET['edit'] ?? '') : '';
      // A form that didn't pass the checks comes back with what was typed and the errors.
      $saved = $_SESSION['trip_form'] ?? null;
      unset($_SESSION['trip_form']);
      $formFor = fn(string $which, array $stored) => $edit !== $which ? null : (($saved[0] ?? '') === $which ? [$saved[1], $saved[2]] : [$stored, []]);
      [$pc, $pn] = split_phone($booking['contact_phone']);
      $self = url('trip.php', ['ref' => $ref]);
      $method = (string) ($_GET['method'] ?? '');
      $gcashStep = $canGcash && !$booking['gcash_ref'] && !empty($booking['quote']['reviewed_at']) && ($method === 'gcash' || !$canPay); ?>
    <div class="mt-6"><?= checkout_banner() ?></div>
    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
      <div class="min-w-0 space-y-6">
        <?= checkout_trip($booking) ?>
        <?php if ($booking['kind'] !== 'hotel'): ?><?= checkout_baggage($booking, $canEdit) ?><?php endif; ?>
        <?= checkout_travelers($booking, $canEdit, $formFor('travelers', traveler_form_values($booking['travelers'])), url('trip.php', ['ref' => $ref, 'edit' => 'travelers']) . '#travelers', $self . '#travelers') ?>
        <?= checkout_contact($booking, $canEdit, $formFor('contact', ['contact_name' => (string) $booking['contact_name'], 'email' => $booking['contact_email'], 'phone_country' => $pc, 'phone' => $pn]), url('trip.php', ['ref' => $ref, 'edit' => 'contact']) . '#contact', $self . '#contact') ?>
        <?php if ($bagPending): ?>
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
          <h2 class="text-lg font-semibold text-amber-900"><?= e(t("We're checking the price of your checked bag")) ?></h2>
          <p class="mt-1 text-sm text-amber-900"><?= e(t("Bag prices are set by the airline. A travel assistant will add the exact price to your total and email you a payment link — usually within a few hours. You don't pay anything for the bag without seeing the price first.")) ?></p>
        </section>
      <?php elseif (($booking['bag_status'] ?? null) === 'included' && $status === 'reserved'): ?>
        <div><?= alert_box(t("Good news: a checked bag is already included in your fare, so there's nothing extra to pay."), 'success') ?></div>
      <?php elseif (($booking['bag_status'] ?? null) === 'declined' && $status === 'reserved'): ?>
        <div><?= alert_box(t("The airline couldn't add a checked bag to this booking, so your total hasn't changed. Contact us if you'd like other options."), 'success') ?></div>
      <?php endif; ?>
        <?php if ($booking['gcash_ref']): ?>
          <section id="pay" class="scroll-mt-24 rounded-2xl border-2 border-brand-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Payment information')) ?></h2>
            <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
              <p class="font-semibold"><?= e(t("We're checking your GCash payment")) ?></p>
              <p class="mt-1"><?= e(t('Reference {ref} · ₱{amount}. A travel assistant will confirm it by email, usually within a few hours.', ['ref' => $booking['gcash_ref'], 'amount' => number_format((int) $booking['gcash_php'])])) ?></p>
            </div>
          </section>
        <?php elseif ($canPay || $canGcash): ?>
          <?= checkout_good_to_know($booking) ?>
          <form method="post" action="<?= e($self) ?>" class="space-y-6" data-pending-form><?= csrf_field() ?><input type="hidden" name="action" value="checkout">
            <?php if (travel_care_available($booking)): ?><?= checkout_care($booking) ?><?php endif; ?>
            <?= checkout_payment($booking, $canPay, $canGcash, $gcashStep ? 'gcash' : $method) ?>
          </form>
          <?php if ($gcashStep):
              $pesos = gcash_amount($booking['total']);
              $qr = site_image('gcash_qr'); ?>
            <section id="gcash" class="scroll-mt-24 rounded-2xl border-2 border-sky-300 bg-white p-6" data-scroll-into-view>
              <h2 class="mb-4 text-lg font-semibold text-slate-900"><?= e(t('Pay with GCash')) ?></h2>
                <div class="grid gap-5 sm:grid-cols-[auto_1fr]">
                  <?php if ($qr): ?><img src="<?= e($qr) ?>" alt="<?= e(t('GCash QR code')) ?>" class="mx-auto h-40 w-40 rounded-xl object-contain ring-1 ring-slate-200 sm:mx-0"><?php endif; ?>
                  <div class="space-y-3 text-sm">
                    <div class="rounded-xl bg-sky-50 p-4 ring-1 ring-sky-100">
                      <p class="text-sky-800"><?= e(t('Send exactly')) ?></p>
                      <p class="text-2xl font-extrabold text-slate-900">₱<?= number_format((int) $pesos) ?></p>
                      <p class="mt-1 text-xs text-slate-500"><?= e(t("{usd} at today's exchange rate. If you pay on another day, refresh this page for the current amount.", ['usd' => money($booking['total'])])) ?></p>
                    </div>
                    <dl class="space-y-1.5">
                      <div class="flex justify-between gap-4"><dt class="text-slate-500"><?= e(t('GCash number')) ?></dt><dd class="font-mono font-semibold text-slate-900"><?= e((string) config('gcash_number')) ?></dd></div>
                      <?php if (config('gcash_name')): ?><div class="flex justify-between gap-4"><dt class="text-slate-500"><?= e(t('Account name')) ?></dt><dd class="font-semibold text-slate-900"><?= e((string) config('gcash_name')) ?></dd></div><?php endif; ?>
                      <div class="flex justify-between gap-4"><dt class="text-slate-500"><?= e(t('Message / note')) ?></dt><dd class="font-mono font-semibold text-slate-900"><?= e($ref) ?></dd></div>
                    </dl>
                  </div>
                </div>
                <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                  <li><?= e($qr ? t('Scan the QR code in your GCash app, or send to the number above.') : t('In your GCash app, choose Send Money and send to the number above.')) ?></li>
                  <li><?= e(t('Add your trip reference {ref} as the message.', ['ref' => $ref])) ?></li>
                  <li><?= e(t('Enter the reference number from your GCash receipt below.')) ?></li>
                </ol>
                <form method="post" class="mt-4 flex flex-col gap-2 sm:flex-row"><?= csrf_field() ?><input type="hidden" name="action" value="gcash">
                  <input name="gcash_ref" inputmode="numeric" autocomplete="off" required placeholder="<?= e(t('GCash reference no., e.g. 1234 567 890123')) ?>" aria-label="<?= e(t('GCash reference number')) ?>" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                  <button type="submit" class="rounded-xl bg-sky-600 px-5 py-3 font-bold text-white hover:bg-sky-700"><?= e(t("I've sent the payment")) ?></button>
                </form>
            </section>
          <?php endif; ?>
        <?php elseif (!$bagPending): ?>
          <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600"><?= e(t('A travel assistant will contact you at {email} within 24 hours to confirm availability and arrange payment.', ['email' => $booking['contact_email']])) ?></section>
        <?php endif; ?>
        <?php if ($status === 'paid' || $status === 'ticketed'): ?>
          <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Need to change plans?')) ?></h2>
            <p class="mt-1 text-sm text-slate-600"><?= e(t('Your trip is paid. To change or cancel it, contact our travel assistants with your reference {ref} — refunds depend on the fare and hotel rules.', ['ref' => $ref])) ?></p>
          </section>
        <?php elseif ($status === 'reserved'): ?>
          <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Need to change plans?')) ?></h2>
            <p class="mt-1 text-sm text-slate-600"><?= e(t('Reservations can be cancelled free of charge until payment is made.')) ?></p>
            <form method="post" class="mt-4 sm:w-64" data-confirm="<?= e(t("Cancel this reservation? This can't be undone.")) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                <button type="submit" class="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50"><?= e(t('Cancel reservation')) ?></button>
            </form>
          </section>
        <?php endif; ?>
      </div>
      <aside class="h-fit lg:sticky lg:top-20"><?= checkout_summary($booking) ?></aside>
    </div>
  <?php else: ?>
  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <?php if ($status === 'ticketed'): ?>
        <section class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-6">
          <h2 class="text-lg font-semibold text-emerald-900"><?= e($booking['kind'] === 'hotel' ? t('Your room is booked') : t('Your ticket is issued')) ?></h2>
          <p class="mt-3 text-sm text-emerald-800"><?= e(['flight' => t('Airline booking code'), 'hotel' => t('Hotel confirmation number'), 'package' => t('Booking code')][$booking['kind']]) ?></p>
          <p class="font-mono text-3xl font-extrabold tracking-wider text-emerald-950"><?= e((string) $booking['supplier_ref']) ?></p>
          <?php if ($booking['ticket_note']): ?><p class="mt-4 whitespace-pre-line text-sm text-emerald-900"><?= e($booking['ticket_note']) ?></p><?php endif; ?>
          <p class="mt-4 text-xs text-emerald-800"><?= e($booking['kind'] === 'hotel' ? t('Show this number at check-in.') : t("Use this code to check in and manage your booking on the airline's website.")) ?></p>
        </section>
      <?php elseif ($status === 'paid'): ?>
        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-6">
          <h2 class="text-lg font-semibold text-sky-900"><?= e($booking['kind'] === 'hotel' ? t("Payment received — we're issuing your room booking") : t("Payment received — we're issuing your tickets")) ?></h2>
          <p class="mt-1 text-sm text-sky-800"><?= e($booking['kind'] === 'hotel'
              ? t("A travel assistant is confirming your trip with the hotel. You'll get an email with your confirmation code, usually within a few hours. It will also appear right here.")
              : t("A travel assistant is confirming your trip with the airline. You'll get an email with your confirmation code, usually within a few hours. It will also appear right here.")) ?></p>
        </section>
      <?php endif; ?>
      <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Travelers')) ?></h2>
        <ul class="mt-4 divide-y divide-slate-100">
          <?php foreach ($booking['travelers'] as $i => $t): ?>
            <li class="py-3 text-sm"><div class="flex items-center justify-between gap-3"><span class="font-medium text-slate-900"><?= e(trim("{$t['first']} {$t['last']}")) ?></span>
              <span class="text-right text-slate-500"><?= e(slot_label($booking['quote']['slots'][$i]['label'] ?? '')) ?><?= !empty($t['dob']) ? ' · ' . e(t('born {date}', ['date' => fmt_dob($t['dob'])])) : '' ?></span></div>
              <?php
              $more = array_filter([
                  isset($t['gender']) ? ($t['gender'] === 'F' ? t('Female') : t('Male')) : null,
                  !empty($t['nationality']) ? country_name($t['nationality']) : null,
                  !empty($t['frequent_flyer']) ? t('Frequent flyer {number}', ['number' => $t['frequent_flyer']]) : null,
                  !empty($t['extra_bag']) ? t('Checked bag requested') : null,
              ]);
              if ($more): ?><p class="mt-1 text-xs text-slate-500"><?= e(implode(' · ', $more)) ?></p><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Contact')) ?></h2>
        <dl class="mt-4 space-y-2 text-sm">
          <?php if (!empty($booking['contact_name'])): ?><div class="flex justify-between"><dt class="text-slate-500"><?= e(t('Name')) ?></dt><dd class="font-medium text-slate-900"><?= e($booking['contact_name']) ?></dd></div><?php endif; ?>
          <div class="flex justify-between"><dt class="text-slate-500"><?= e(t('Email')) ?></dt><dd class="font-medium text-slate-900"><?= e($booking['contact_email']) ?></dd></div>
          <div class="flex justify-between"><dt class="text-slate-500"><?= e(t('Phone')) ?></dt><dd class="font-medium text-slate-900"><?= e($booking['contact_phone']) ?></dd></div>
        </dl>
      </section>
      <?php if ($status === 'paid' || $status === 'ticketed'): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Need to change plans?')) ?></h2>
          <p class="mt-1 text-sm text-slate-600"><?= e(t('Your trip is paid. To change or cancel it, contact our travel assistants with your reference {ref} — refunds depend on the fare and hotel rules.', ['ref' => $ref])) ?></p>
        </section>
      <?php elseif ($status === 'reserved'): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Need to change plans?')) ?></h2>
          <p class="mt-1 text-sm text-slate-600"><?= e(t('Reservations can be cancelled free of charge until payment is made.')) ?></p>
          <form method="post" class="mt-4 sm:w-64" data-confirm="<?= e(t("Cancel this reservation? This can't be undone.")) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
            <button type="submit" class="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50"><?= e(t('Cancel reservation')) ?></button>
          </form>
        </section>
      <?php endif; ?>
    </div>
    <aside class="h-fit lg:sticky lg:top-20"><?= trip_summary($booking['quote']) ?></aside>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
