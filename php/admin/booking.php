<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$ref = (string) ($_GET['ref'] ?? '');
$booking = admin_booking($ref);
if (!$booking) not_found();
$methods = ['Bank transfer', 'Card over the phone', 'Cash at office', 'Other'];
$self = url('admin/booking.php', ['ref' => $ref]);
$error = null;

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'note') {
        $note = mb_substr(post('note'), 0, 1000);
        if ($note === '') $error = 'Write a note first.';
        else { add_event($ref, $admin['id'], 'note', $note); flash('Note added.'); redirect($self); }
    } elseif ($action === 'paid') {
        $method = post('method');
        $note = mb_substr(post('note'), 0, 300);
        if (!in_array($method, $methods, true)) $error = 'Choose how the customer paid.';
        elseif (!mark_paid($ref, $method, $admin['id'], "Marked as paid — $method." . ($note ? " $note" : ''))) $error = 'Only reserved (unpaid) bookings can be marked as paid.';
        else { notify_booking('paid', $ref); flash('Marked as paid. The customer has been emailed a receipt.'); redirect($self); }
    } elseif ($action === 'cancel') {
        $reason = post('reason');
        if (mb_strlen($reason) < 3) $error = "Please give a reason (it's saved in the activity log).";
        elseif (!admin_cancel($ref, $admin['id'], mb_substr($reason, 0, 300))) $error = 'This booking is already cancelled.';
        else { notify_booking('cancelled', $ref); flash('Booking cancelled. The customer has been emailed.'); redirect($self); }
    }
}

$events = list_events($ref);
$q = $booking['quote'];
$title = $ref;
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
$dot = ['created' => 'bg-brand-500', 'paid' => 'bg-emerald-500', 'cancelled' => 'bg-red-500', 'note' => 'bg-slate-400'];
$input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900';
echo admin_open('bookings');
?>
<div class="space-y-6">
  <nav class="text-sm text-slate-500"><a href="<?= e(url('admin/bookings.php')) ?>" class="hover:text-brand-700">← All bookings</a></nav>
  <?= alert_box($error) ?>
  <div class="flex flex-wrap items-center gap-3">
    <h1 class="font-mono text-2xl font-bold text-slate-900"><?= e($ref) ?></h1><?= status_badge($booking['status']) ?>
    <span class="text-sm text-slate-500">Booked <?= local_time($booking['created_at']) ?>
      <?= $booking['paid_at'] ? ' · Paid ' . local_time($booking['paid_at']) . ' (' . e($booking['payment_method']) . ')' : '' ?>
      <?= $booking['cancelled_at'] ? ' · Cancelled ' . local_time($booking['cancelled_at']) : '' ?></span>
  </div>
  <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <section class="grid gap-6 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div><h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contact for this trip</h2>
          <p class="mt-2"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $booking['contact_phone'])) ?>" class="text-lg font-semibold text-brand-700 hover:underline"><?= e($booking['contact_phone']) ?></a></p>
          <p><a href="mailto:<?= e($booking['contact_email']) ?>?subject=<?= rawurlencode("Your trip $ref") ?>" class="text-brand-700 hover:underline"><?= e($booking['contact_email']) ?></a></p></div>
        <div><h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Account</h2>
          <p class="mt-2 font-medium text-slate-900"><?= e($booking['customer_name']) ?></p><p class="text-sm text-slate-600"><?= e($booking['customer_email']) ?></p>
          <a href="<?= e(url('admin/bookings.php', ['user' => $booking['user_id']])) ?>" class="text-sm font-semibold text-brand-700 hover:underline">All bookings by this customer →</a></div>
      </section>
      <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Travelers</h2>
        <table class="mt-3 w-full text-sm"><tbody class="divide-y divide-slate-100">
          <?php foreach ($booking['travelers'] as $i => $t): ?>
            <tr><td class="py-2 text-slate-500"><?= e($q['slots'][$i]['label'] ?? '') ?></td><td class="py-2 font-medium text-slate-900"><?= e(mb_strtoupper($t['last']) . ', ' . $t['first']) ?></td>
              <td class="py-2 text-right text-slate-600"><?= !empty($t['dob']) ? 'DOB ' . e(fmt_dob($t['dob'])) : '' ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      </section>
      <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Activity</h2>
        <form method="post" class="mt-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="note">
          <textarea name="note" rows="3" maxlength="1000" placeholder="Internal note — e.g. Called customer, prefers window seats. Not visible to the customer." class="<?= $input ?>"></textarea>
          <div class="flex justify-end"><button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Add note</button></div>
        </form>
        <ol class="mt-5 space-y-4">
          <?php foreach ($events as $ev): ?>
            <li class="flex gap-3"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full <?= $dot[$ev['type']] ?? 'bg-slate-400' ?>"></span>
              <div class="text-sm"><p class="text-slate-900"><?= e($ev['message']) ?></p><p class="text-xs text-slate-500"><?= e($ev['actor_name'] ?? 'System') ?> · <?= local_time($ev['created_at']) ?></p></div></li>
          <?php endforeach; ?>
          <?php if (!$events): ?><li class="text-sm text-slate-500">No activity yet.</li><?php endif; ?>
        </ol>
      </section>
    </div>
    <aside class="space-y-6">
      <?php if ($booking['status'] === 'reserved'): ?>
        <section class="rounded-xl border-2 border-emerald-200 bg-white p-5">
          <h2 class="font-semibold text-slate-900">Record a payment</h2>
          <p class="mb-3 mt-1 text-sm text-slate-500">For payments taken outside the website. The customer gets a receipt email.</p>
          <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="paid">
            <label class="block text-xs font-medium text-slate-500">Payment method
              <select name="method" class="<?= $input ?> mt-1"><option value="">Choose…</option><?php foreach ($methods as $m): ?><option><?= $m ?></option><?php endforeach; ?></select></label>
            <label class="block text-xs font-medium text-slate-500">Note (optional)<input name="note" maxlength="300" placeholder="e.g. transfer ref 12345" class="<?= $input ?> mt-1"></label>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark <?= money($booking['total']) ?> as paid</button>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($booking['status'] !== 'cancelled'): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-5">
          <h2 class="mb-3 font-semibold text-slate-900">Cancel booking</h2>
          <form method="post" class="space-y-3" data-confirm="Cancel <?= e($ref) ?>? The customer will be emailed."><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
            <label class="block text-xs font-medium text-slate-500">Reason<input name="reason" maxlength="300" placeholder="e.g. Customer asked to cancel by phone" class="<?= $input ?> mt-1"></label>
            <?php if ($booking['status'] === 'paid'): ?><p class="text-xs text-amber-700">This trip is paid. Cancelling doesn't refund automatically — issue the refund in Stripe or your bank.</p><?php endif; ?>
            <button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Cancel booking</button>
          </form>
        </section>
      <?php endif; ?>
      <?php if (!empty($q['supplier'])): $s = $q['supplier']; $margin = $booking['total'] - $s['net_usd']; ?>
        <section class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
          <h2 class="font-semibold text-slate-900">Supplier &amp; margin</h2>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Supplier</dt><dd class="font-medium text-slate-900"><?= $s['provider'] === 'duffel' ? 'Duffel (flights)' : 'LiteAPI (hotels)' ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Offer ID</dt><dd class="truncate font-mono text-xs text-slate-700" title="<?= e($s['offer_id']) ?>"><?= e($s['offer_id']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Cost when booked</dt><dd class="font-medium text-slate-900"><?= $s['net_currency'] === 'USD' ? money($s['net_amount']) : e(number_format($s['net_amount'], 2) . ' ' . $s['net_currency']) . ' (≈' . money($s['net_usd']) . ')' ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer pays</dt><dd class="font-medium text-slate-900"><?= money($booking['total']) ?></dd></div>
            <div class="flex justify-between gap-3 border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Gross margin</dt>
              <dd class="font-bold <?= $margin >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= money($margin) ?> (<?= round($margin / $booking['total'] * 100) ?>%)</dd></div>
          </dl>
          <p class="mt-3 text-xs text-slate-500">Before supplier and card fees. Issue the ticket or room in the <?= $s['provider'] === 'duffel' ? 'Duffel' : 'LiteAPI' ?> dashboard after payment — supplier prices can change until then.</p>
        </section>
      <?php endif; ?>
      <?= trip_summary($q) ?>
    </aside>
  </div>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
