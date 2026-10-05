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
    if ($action === 'pay' && $booking['status'] === 'reserved' && payments_enabled()) {
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
$canPay = $status === 'reserved' && payments_enabled();
$provider = payment_provider();
$title = "Trip $ref";
require __DIR__ . '/includes/header.php';
$check = '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">' . icon('check', 20) . '</span>';
?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url('account.php')) ?>" class="hover:text-brand-700">← My trips</a></nav>
  <?php if ($isNew): ?>
    <div class="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><?= $check ?>
      <div><p class="text-lg font-semibold text-emerald-900">Your trip is reserved!</p>
        <p class="mt-1 text-sm text-emerald-800"><?= e($canPay ? 'Pay securely below to confirm it now, or a travel assistant will contact you within 24 hours.' : "A travel assistant will contact you at {$booking['contact_email']} within 24 hours to confirm availability and arrange payment.") ?>
          We've emailed your confirmation to <?= e($booking['contact_email']) ?>. Reference: <strong><?= e($ref) ?></strong></p></div>
    </div>
  <?php endif; ?>
  <?php if ($justPaid): ?>
    <div class="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><?= $check ?>
      <div><p class="text-lg font-semibold text-emerald-900">Payment received — thank you!</p><p class="mt-1 text-sm text-emerald-800">We've emailed your receipt to <?= e($booking['contact_email']) ?>. A travel assistant is now issuing your <?= $booking['kind'] === 'hotel' ? 'room booking' : 'tickets' ?> — you'll get your confirmation code by email, usually within a few hours.</p></div>
    </div>
  <?php endif; ?>
  <?php if (($_GET['payment'] ?? '') === 'error' && $status === 'reserved'): ?>
    <div class="mb-6"><?= alert_box("We couldn't start the payment just now. Please try again in a moment.") ?>
      <?php if (is_admin() && ($pe = last_payment_error()) && $pe['ref'] === $ref): ?>
        <p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Admin only — what PayPal said:</strong> <?= e($pe['message']) ?></p>
      <?php endif; ?></div>
  <?php elseif (($_GET['payment'] ?? '') === 'cancelled' && $status === 'reserved'): ?>
    <div class="mb-6"><?= alert_box("Payment cancelled — you haven't been charged. You can pay whenever you're ready.") ?></div>
  <?php elseif ($payFailed): ?>
    <div class="mb-6"><?= alert_box("We couldn't confirm your payment yet. If money was taken, it will show here shortly — otherwise please try again.") ?></div>
  <?php endif; ?>

  <div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold text-slate-900">Trip <?= e($ref) ?></h1>
    <?php [$badgeCls, $badgeText] = match ($status) {
        'cancelled' => ['bg-slate-200 text-slate-600', 'Cancelled'],
        'ticketed' => ['bg-emerald-100 text-emerald-800', 'Confirmed · ' . ($booking['kind'] === 'hotel' ? 'booked' : 'ticket issued')],
        'paid' => ['bg-sky-100 text-sky-800', 'Paid · ' . ($booking['kind'] === 'hotel' ? 'booking your room' : 'issuing tickets')],
        default => ['bg-amber-100 text-amber-800', 'Reserved · awaiting payment'],
    }; ?>
    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide <?= $badgeCls ?>"><?= e($badgeText) ?></span>
  </div>
  <p class="mt-1 text-sm text-slate-500">Booked <?= local_time($booking['created_at'], true) ?><?= $booking['paid_at'] ? ' · Paid ' . local_time($booking['paid_at'], true) : '' ?><?= $booking['ticketed_at'] ? ' · Confirmed ' . local_time($booking['ticketed_at'], true) : '' ?><?= $booking['cancelled_at'] ? ' · Cancelled ' . local_time($booking['cancelled_at'], true) : '' ?></p>

  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <?php if ($status === 'ticketed'): ?>
        <section class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-6">
          <h2 class="text-lg font-semibold text-emerald-900"><?= $booking['kind'] === 'hotel' ? 'Your room is booked' : 'Your ticket is issued' ?></h2>
          <p class="mt-3 text-sm text-emerald-800"><?= e(['flight' => 'Airline booking code', 'hotel' => 'Hotel confirmation number', 'package' => 'Booking code'][$booking['kind']]) ?></p>
          <p class="font-mono text-3xl font-extrabold tracking-wider text-emerald-950"><?= e((string) $booking['supplier_ref']) ?></p>
          <?php if ($booking['ticket_note']): ?><p class="mt-4 whitespace-pre-line text-sm text-emerald-900"><?= e($booking['ticket_note']) ?></p><?php endif; ?>
          <p class="mt-4 text-xs text-emerald-800"><?= $booking['kind'] === 'hotel' ? 'Show this number at check-in.' : 'Use this code to check in and manage your booking on the airline\'s website.' ?></p>
        </section>
      <?php elseif ($status === 'paid'): ?>
        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-6">
          <h2 class="text-lg font-semibold text-sky-900">Payment received — we're issuing your <?= $booking['kind'] === 'hotel' ? 'room booking' : 'tickets' ?></h2>
          <p class="mt-1 text-sm text-sky-800">A travel assistant is confirming your trip with the <?= $booking['kind'] === 'hotel' ? 'hotel' : 'airline' ?>. You'll get an email with your confirmation code, usually within a few hours. It will also appear right here.</p>
        </section>
      <?php endif; ?>
      <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Travelers</h2>
        <ul class="mt-4 divide-y divide-slate-100">
          <?php foreach ($booking['travelers'] as $i => $t): ?>
            <li class="flex items-center justify-between py-3 text-sm"><span class="font-medium text-slate-900"><?= e("{$t['first']} {$t['last']}") ?></span>
              <span class="text-slate-500"><?= e($booking['quote']['slots'][$i]['label'] ?? '') ?><?= !empty($t['dob']) ? ' · born ' . e(fmt_dob($t['dob'])) : '' ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Contact</h2>
        <dl class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-900"><?= e($booking['contact_email']) ?></dd></div>
          <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="font-medium text-slate-900"><?= e($booking['contact_phone']) ?></dd></div>
        </dl>
      </section>
      <?php if ($canPay): ?>
        <section id="pay" class="scroll-mt-24 rounded-2xl border-2 bg-white p-6 <?= ($_GET['pay'] ?? '') === '1' ? 'border-accent-500 ring-4 ring-accent-500/20' : 'border-brand-200' ?>"<?= ($_GET['pay'] ?? '') === '1' ? ' data-scroll-into-view' : '' ?>>
          <h2 class="text-lg font-semibold text-slate-900">Pay now to confirm</h2>
          <p class="mt-1 text-sm text-slate-600"><?= $provider === 'paypal'
              ? 'Pay ' . money($booking['total']) . ' securely with your PayPal account or any debit/credit card. You\'ll be taken to PayPal and brought back here afterwards.'
              : 'Pay ' . money($booking['total']) . ' securely by card. You\'ll be taken to our payment partner Stripe and brought back here afterwards.' ?></p>
          <form method="post" class="mt-4 sm:w-64"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
            <button type="submit" class="w-full rounded-xl bg-accent-500 py-3 font-bold text-white shadow-sm hover:bg-accent-600">Pay <?= money($booking['total']) ?><?= $provider === 'paypal' ? ' with PayPal' : '' ?></button>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($status === 'paid' || $status === 'ticketed'): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900">Need to change plans?</h2>
          <p class="mt-1 text-sm text-slate-600">Your trip is paid. To change or cancel it, contact our travel assistants with your reference <?= e($ref) ?> — refunds depend on the fare and hotel rules.</p>
        </section>
      <?php elseif ($status === 'reserved'): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900">Need to change plans?</h2>
          <p class="mt-1 text-sm text-slate-600">Reservations can be cancelled free of charge until payment is made.</p>
          <form method="post" class="mt-4 sm:w-64" data-confirm="Cancel this reservation? This can't be undone."><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
            <button type="submit" class="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Cancel reservation</button>
          </form>
        </section>
      <?php endif; ?>
    </div>
    <aside class="h-fit lg:sticky lg:top-20"><?= trip_summary($booking['quote']) ?></aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
