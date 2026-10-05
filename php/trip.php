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
    if ($action === 'gcash' && $booking['status'] === 'reserved' && gcash_enabled() && ($booking['bag_status'] ?? null) !== 'pending') {
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
        redirect(url('trip.php', ['ref' => $ref]) . '#gcash');
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
$canGcash = $status === 'reserved' && !$bagPending && gcash_enabled();
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
      <?php if ($canPay): ?>
        <section id="pay" class="scroll-mt-24 rounded-2xl border-2 bg-white p-6 <?= ($_GET['pay'] ?? '') === '1' ? 'border-accent-500 ring-4 ring-accent-500/20' : 'border-brand-200' ?>"<?= ($_GET['pay'] ?? '') === '1' ? ' data-scroll-into-view' : '' ?>>
          <h2 class="text-lg font-semibold text-slate-900"><?= e(t('Pay now to confirm')) ?></h2>
          <p class="mt-1 text-sm text-slate-600"><?= e($provider === 'paypal'
              ? t("Pay {total} securely with your PayPal account or any debit/credit card. You'll be taken to PayPal and brought back here afterwards.", ['total' => money($booking['total'])])
              : t("Pay {total} securely by card. You'll be taken to our payment partner Stripe and brought back here afterwards.", ['total' => money($booking['total'])])) ?></p>
          <form method="post" class="mt-4 sm:w-64"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
            <button type="submit" class="w-full rounded-xl bg-accent-500 py-3 font-bold text-white shadow-sm hover:bg-accent-600"><?= e($provider === 'paypal' ? t('Pay {total} with PayPal', ['total' => money($booking['total'])]) : t('Pay {total}', ['total' => money($booking['total'])])) ?></button>
          </form>
          <?php if (($hint = price_hint($booking['total'])) !== ''): ?><p class="mt-2 text-xs text-slate-500"><?= e($hint) ?><?php if (current_currency() !== 'USD'): ?> · <?= e(t('You will be charged in US dollars.')) ?><?php endif; ?></p><?php endif; ?>
        </section>
      <?php endif; ?>
      <?php if ($canGcash): $pesos = gcash_amount($booking['total']); $qr = site_image('gcash_qr'); ?>
        <section id="gcash" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-slate-900"><?= e($canPay ? t('Or pay with GCash') : t('Pay with GCash')) ?></h2>
            <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200"><?= e(t('For payments from the Philippines')) ?></span>
          </div>
          <?php if ($booking['gcash_ref']): ?>
            <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
              <p class="font-semibold"><?= e(t("We're checking your GCash payment")) ?></p>
              <p class="mt-1"><?= e(t('Reference {ref} · ₱{amount}. A travel assistant will confirm it by email, usually within a few hours.', ['ref' => $booking['gcash_ref'], 'amount' => number_format((int) $booking['gcash_php'])])) ?></p>
            </div>
          <?php else: ?>
            <div class="mt-4 grid gap-5 sm:grid-cols-[auto_1fr]">
              <?php if ($qr): ?><img src="<?= e($qr) ?>" alt="<?= e(t('GCash QR code')) ?>" class="mx-auto h-44 w-44 rounded-xl object-contain ring-1 ring-slate-200 sm:mx-0"><?php endif; ?>
              <div class="space-y-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-4">
                  <p class="text-slate-500"><?= e(t('Send exactly')) ?></p>
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
          <?php endif; ?>
        </section>
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
    <aside class="h-fit lg:sticky lg:top-20"><?= trip_summary($booking['quote']) ?></aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
