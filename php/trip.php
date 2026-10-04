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
    if ($action === 'pay' && $booking['status'] === 'reserved' && stripe_enabled()) {
        try {
            $session = create_checkout_session($booking);
            db_run('UPDATE bookings SET stripe_session_id = ? WHERE reference = ?', [$session['id'], $ref]);
            redirect($session['url']);
        } catch (Throwable $e) {
            error_log("[payments] checkout for $ref failed: " . $e->getMessage());
            redirect(url('trip.php', ['ref' => $ref, 'payment' => 'error']));
        }
    }
    redirect(url('trip.php', ['ref' => $ref]));
}

// Back from Stripe: confirm with Stripe directly (don't trust the URL), in case the webhook is late.
$sessionId = (string) ($_GET['session_id'] ?? '');
if ($sessionId !== '' && $booking['status'] === 'reserved' && stripe_enabled()) {
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

$status = $booking['status'];
$isNew = ($_GET['new'] ?? '') === '1' && $status === 'reserved';
$justPaid = $sessionId !== '' && $status === 'paid';
$canPay = $status === 'reserved' && stripe_enabled();
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
      <div><p class="text-lg font-semibold text-emerald-900">Payment received — you're all set!</p><p class="mt-1 text-sm text-emerald-800">We've emailed your receipt to <?= e($booking['contact_email']) ?>.</p></div>
    </div>
  <?php endif; ?>
  <?php if (($_GET['payment'] ?? '') === 'error' && $status === 'reserved'): ?>
    <div class="mb-6"><?= alert_box("We couldn't start the payment just now. Please try again in a moment.") ?></div>
  <?php endif; ?>

  <div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-bold text-slate-900">Trip <?= e($ref) ?></h1>
    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide <?= $status === 'cancelled' ? 'bg-slate-200 text-slate-600' : ($status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
      <?= $status === 'cancelled' ? 'Cancelled' : ($status === 'paid' ? 'Paid · confirmed' : 'Reserved · awaiting payment') ?></span>
  </div>
  <p class="mt-1 text-sm text-slate-500">Booked <?= local_time($booking['created_at'], true) ?><?= $booking['paid_at'] ? ' · Paid ' . local_time($booking['paid_at'], true) : '' ?><?= $booking['cancelled_at'] ? ' · Cancelled ' . local_time($booking['cancelled_at'], true) : '' ?></p>

  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
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
        <section class="rounded-2xl border-2 border-brand-200 bg-white p-6">
          <h2 class="text-lg font-semibold text-slate-900">Pay now to confirm</h2>
          <p class="mt-1 text-sm text-slate-600">Pay <?= money($booking['total']) ?> securely by card. You'll be taken to our payment partner Stripe and brought back here afterwards.</p>
          <form method="post" class="mt-4 sm:w-64"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
            <button type="submit" class="w-full rounded-xl bg-accent-500 py-3 font-bold text-white shadow-sm hover:bg-accent-600">Pay <?= money($booking['total']) ?></button>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($status === 'paid'): ?>
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
