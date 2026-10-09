<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$ref = (string) ($_GET['ref'] ?? '');
$booking = admin_booking($ref);
if (!$booking) not_found();
$methods = ['GCash', 'Bank transfer', 'Card over the phone', 'Cash at office', 'Other'];
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
    } elseif ($action === 'paylink') {
        $message = mb_substr(post('message'), 0, 500);
        if ($booking['status'] !== 'reserved') $error = 'Only unpaid bookings can get a payment link.';
        elseif (!payments_enabled()) $error = 'Connect PayPal on Site settings first.';
        else {
            send_payment_link($booking, $message);
            add_event($ref, $admin['id'], 'email', "Payment link emailed to {$booking['contact_email']}." . ($message !== '' ? " Message: $message" : ''));
            flash("Payment link sent to {$booking['contact_email']}.");
            redirect($self);
        }
    } elseif (in_array($action, ['bag_price', 'bag_decline', 'bag_included'], true)) {
        $amount = $action === 'bag_price' ? filter_var(post('bag_amount'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]) : null;
        if ($action === 'bag_price' && $amount === false) $error = 'Enter the total bag price for the customer in whole US dollars (e.g. 45).';
        elseif (!settle_bag_request($ref, $amount === false ? null : $amount, $admin['id'], $action === 'bag_included')) $error = 'This bag request was already handled, or the booking is no longer unpaid.';
        else {
            $fresh = admin_booking($ref);
            $msg = match ($action) {
                'bag_price' => 'We\'ve added your checked bag(s) for ' . money($amount) . '. Your new total is ' . money($fresh['total']) . '.',
                'bag_included' => 'Good news: a checked bag is already included in your fare, so there\'s nothing extra to pay.',
                default => 'Unfortunately the airline couldn\'t add a checked bag to this booking, so your total is unchanged.',
            };
            if (payments_enabled()) {
                send_payment_link($fresh, $msg);
                add_event($ref, $admin['id'], 'email', "Payment link emailed to {$fresh['contact_email']}. Message: $msg");
                flash('Saved. The customer has been emailed the ' . ($action === 'bag_price' ? 'new total' : 'news') . ' and a payment link.');
            } else {
                flash('Saved. Payments are off, so please tell the customer yourself: ' . $msg);
            }
            redirect($self);
        }
    } elseif ($action === 'ticket') {
        $code = mb_strtoupper(trim(post('code')));
        $note = mb_substr(trim(post('ticket_note')), 0, 1000);
        $boughtFrom = mb_substr(trim(post('bought_from')), 0, 100);
        $costRaw = str_replace([',', '$'], '', trim(post('bought_cost')));
        $boughtCost = $costRaw === '' ? null : (is_numeric($costRaw) && (float) $costRaw >= 0 && (float) $costRaw < 1000000 ? round((float) $costRaw, 2) : false);
        if ($boughtCost === false) $error = 'Enter what you paid as a number in US dollars (e.g. 389.50), or leave it empty.';
        elseif (!preg_match('/^[A-Z0-9][A-Z0-9 ,\/-]{1,98}$/', $code)) $error = 'Enter the confirmation code from the airline or hotel (letters and numbers, e.g. ABC123).';
        elseif (!mark_ticketed($ref, $admin['id'], $code, $note, $boughtFrom, $boughtCost)) $error = 'Only paid bookings can be marked as ticketed.';
        else { notify_booking('ticketed', $ref); flash("Saved. {$booking['contact_email']} has been emailed the confirmation $code."); redirect($self); }
    } elseif ($action === 'gcash_ok') {
        if (!$booking['gcash_ref']) $error = 'There is no GCash payment to confirm.';
        elseif (!mark_paid($ref, 'GCash', $admin['id'], "GCash payment confirmed — ₱" . number_format((int) $booking['gcash_php']) . ", reference {$booking['gcash_ref']}.", 'GCash ' . $booking['gcash_ref'])) $error = 'Only reserved (unpaid) bookings can be marked as paid.';
        else { notify_booking('paid', $ref); flash('GCash payment confirmed. The customer has been emailed a receipt.'); redirect($self); }
    } elseif ($action === 'gcash_missing') {
        if (!reject_gcash($ref, $admin['id'])) $error = 'There is no GCash payment waiting on this booking.';
        else { email_gcash_not_found($booking); flash('Done. The customer was asked to check the reference number.'); redirect($self); }
    } elseif ($action === 'delete') {
        if (strtoupper(post('confirm_ref')) !== $ref) $error = "To delete, type the reference $ref exactly.";
        elseif (!delete_booking($ref)) $error = 'This booking was already deleted.';
        else {
            error_log("[admin] booking $ref deleted by admin #{$admin['id']}");
            flash("Booking $ref was deleted.");
            redirect(url('admin/bookings.php'));
        }
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
$dot = ['created' => 'bg-brand-500', 'paid' => 'bg-emerald-500', 'ticketed' => 'bg-emerald-700', 'cancelled' => 'bg-red-500', 'note' => 'bg-slate-400', 'email' => 'bg-accent-500'];
$input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900';
echo admin_open('bookings');
?>
<div class="space-y-6">
  <nav class="text-sm text-slate-500"><a href="<?= e(url('admin/bookings.php')) ?>" class="hover:text-brand-700">← All bookings</a></nav>
  <?= alert_box($error) ?>
  <div class="flex flex-wrap items-center gap-3">
    <h1 class="font-mono text-2xl font-bold text-slate-900"><?= e($ref) ?></h1><?= status_badge($booking['status']) ?><?= !empty($q['care']) ? travel_care_badge() : '' ?>
    <span class="text-sm text-slate-500">Booked <?= local_time($booking['created_at']) ?>
      <?= $booking['paid_at'] ? ' · Paid ' . local_time($booking['paid_at']) . ' (' . e($booking['payment_method']) . ')' : '' ?>
      <?= $booking['ticketed_at'] ? ' · Ticketed ' . local_time($booking['ticketed_at']) : '' ?>
      <?= $booking['cancelled_at'] ? ' · Cancelled ' . local_time($booking['cancelled_at']) : '' ?></span>
  </div>
  <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
      <section class="grid gap-6 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div><h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contact for this trip</h2>
          <?php if (!empty($booking['contact_name'])): ?><p class="mt-2 font-medium text-slate-900"><?= e($booking['contact_name']) ?></p><?php endif; ?>
          <p class="mt-2"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $booking['contact_phone'])) ?>" class="text-lg font-semibold text-brand-700 hover:underline"><?= e($booking['contact_phone']) ?></a></p>
          <p><a href="mailto:<?= e($booking['contact_email']) ?>?subject=<?= rawurlencode("Your trip $ref") ?>" class="text-brand-700 hover:underline"><?= e($booking['contact_email']) ?></a></p></div>
        <div><h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Account</h2>
          <p class="mt-2 font-medium text-slate-900"><?= e($booking['customer_name']) ?></p><?php if (!is_deleted_account(['email' => $booking['customer_email']])): ?><p class="text-sm text-slate-600"><?= e($booking['customer_email']) ?></p><?php endif; ?>
          <a href="<?= e(url('admin/bookings.php', ['user' => $booking['user_id']])) ?>" class="text-sm font-semibold text-brand-700 hover:underline">All bookings by this customer →</a></div>
      </section>
      <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Travelers</h2>
        <table class="mt-3 w-full text-sm"><tbody class="divide-y divide-slate-100">
          <?php foreach ($booking['travelers'] as $i => $t): ?>
            <tr class="align-top"><td class="py-2 text-slate-500"><?= e($q['slots'][$i]['label'] ?? '') ?></td>
              <td class="py-2"><p class="font-medium text-slate-900"><?= e(($t['last'] !== '' ? mb_strtoupper($t['last']) . ', ' : '') . $t['first']) ?><?= !empty($t['no_last_name']) ? ' <span class="text-xs font-normal text-amber-700">(no surname)</span>' : '' ?></p>
                <p class="text-xs text-slate-600"><?= e(implode(' · ', array_filter([
                    isset($t['gender']) ? ($t['gender'] === 'F' ? 'Female' : 'Male') : null,
                    !empty($t['nationality']) ? 'Nationality ' . country_name($t['nationality']) : null,
                    !empty($t['frequent_flyer']) ? 'Frequent flyer ' . $t['frequent_flyer'] : null,
                ]))) ?></p>
                <?php if (!empty($t['extra_bag'])): ?><p class="text-xs font-semibold text-accent-700">+ Checked bag requested</p><?php endif; ?></td>
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
      <?php if ($booking['status'] === 'reserved' && $booking['gcash_ref']): ?>
        <section class="rounded-xl border-2 border-sky-400 bg-white p-5 ring-4 ring-sky-100">
          <h2 class="font-semibold text-slate-900">GCash payment to check</h2>
          <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-bold text-slate-900">₱<?= number_format((int) $booking['gcash_php']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">GCash reference</dt><dd class="font-mono font-semibold text-slate-900"><?= e($booking['gcash_ref']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Sent</dt><dd class="text-slate-700"><?= local_time($booking['gcash_sent_at']) ?></dd></div>
          </dl>
          <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 ring-1 ring-amber-200">Open <strong>your GCash app → Transactions</strong> and find this reference number and amount <strong>before</strong> confirming. Never confirm from a screenshot — they're easy to fake.</p>
          <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="gcash_ok">
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">I received it — mark as paid</button></form>
          <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="gcash_missing">
            <button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Not in my GCash — ask the customer to check</button></form>
        </section>
      <?php endif; ?>
      <?php if ($booking['status'] === 'reserved' && $booking['bag_status'] === 'pending'):
          $bagCount = count(array_filter($booking['travelers'], fn($t) => !empty($t['extra_bag'])));
          $from = $q['baggage']['checked_from'] ?? null; ?>
        <section class="rounded-xl border-2 border-red-300 bg-white p-5 ring-4 ring-red-100">
          <h2 class="font-semibold text-slate-900">Checked bag request — set the price</h2>
          <p class="mb-3 mt-1 text-sm text-slate-600">The customer asked for <strong><?= plural($bagCount, 'checked bag') ?></strong>. Online payment is paused until you answer.
            Check the airline's bag price<?= $from !== null ? ' (LiteAPI said from ' . money((int) ceil($from)) . ' per bag)' : '' ?>, then enter what the customer pays for all bags. It's added to the total and the customer is emailed a payment link.</p>
          <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="bag_price">
            <label class="block text-xs font-medium text-slate-500">Total for <?= plural($bagCount, 'bag') ?> (USD)
              <input name="bag_amount" type="number" min="1" max="5000" step="1" required placeholder="e.g. 45" class="<?= $input ?> mt-1"></label>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Add to total &amp; email customer</button>
          </form>
          <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="bag_included">
            <button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">Bag is already included in the fare — no charge</button>
          </form>
          <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="bag_decline">
            <button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Bags can't be added — keep the current total</button>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($booking['status'] === 'reserved'): ?>
        <section class="rounded-xl border-2 border-accent-500/40 bg-white p-5">
          <h2 class="font-semibold text-slate-900">Send payment link</h2>
          <?php if (payments_enabled()): ?>
            <p class="mb-3 mt-1 text-sm text-slate-500">Emails <?= e($booking['contact_email']) ?> a <strong>Pay now — <?= money($booking['total']) ?></strong> button.</p>
            <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="paylink">
              <label class="block text-xs font-medium text-slate-500">Personal message (optional)
                <textarea name="message" rows="2" maxlength="500" placeholder="e.g. Great talking to you! Here's the link to confirm your trip." class="<?= $input ?> mt-1"></textarea></label>
              <button type="submit" class="w-full rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-600">Email payment link</button>
            </form>
          <?php else: ?>
            <p class="mt-1 text-sm text-slate-500">Connect PayPal on <a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('admin/settings.php')) ?>">Site settings</a> to email customers a payment link.</p>
          <?php endif; ?>
        </section>
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
      <?php if (in_array($booking['status'], ['paid', 'ticketed'], true)):
          $codeLabel = ['flight' => 'Airline booking code (PNR)', 'hotel' => 'Hotel confirmation number', 'package' => 'Booking code(s)'][$booking['kind']];
          $isPaid = $booking['status'] === 'paid'; ?>
        <section class="rounded-xl border-2 bg-white p-5 <?= $isPaid ? 'border-red-300 ring-4 ring-red-100' : 'border-emerald-200' ?>">
          <h2 class="font-semibold text-slate-900"><?= $isPaid ? 'Issue the ticket' : 'Ticket issued' ?></h2>
          <?php if ($isPaid): ?>
            <ol class="mb-3 mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
              <li>Buy this <?= $booking['kind'] === 'hotel' ? 'room' : 'trip' ?> for the travelers listed here — from <?= ['liteapi' => 'LiteAPI', 'liteapi_flights' => 'LiteAPI', 'duffel' => 'Duffel'][$q['supplier']['provider'] ?? ''] ?? 'the supplier' ?> or any other site (Agoda, CheapOair, the airline…). Check it's the same <?= $booking['kind'] === 'hotel' ? 'hotel, room and dates' : 'flights, dates and cabin' ?>.</li>
              <li>Copy the confirmation code below and save — the customer is emailed straight away.</li>
            </ol>
          <?php else: ?>
            <?php if ($booking['bought_cost'] !== null): $profit = $booking['total'] - $booking['bought_cost']; ?>
              <p class="mt-2 rounded-lg bg-slate-900 px-3 py-2 text-sm text-slate-200">Customer paid <strong class="text-white"><?= money($booking['total']) ?></strong> · you paid <strong class="text-white">$<?= number_format($booking['bought_cost'], 2) ?></strong><?= $booking['bought_from'] ? ' on ' . e($booking['bought_from']) : '' ?> · profit <strong class="<?= $profit >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= $profit < 0 ? '−' : '' ?>$<?= number_format(abs($profit), 2) ?></strong> <span class="text-slate-400">(before PayPal fees)</span></p>
            <?php endif; ?>
            <p class="mb-3 mt-1 text-sm text-slate-600">Confirmation <strong class="font-mono"><?= e($booking['supplier_ref']) ?></strong> was emailed to the customer. Fix a mistake below — they'll get the corrected details by email.</p>
          <?php endif; ?>
          <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="ticket">
            <label class="block text-xs font-medium text-slate-500"><?= e($codeLabel) ?>
              <input name="code" required maxlength="99" value="<?= e((string) ($booking['supplier_ref'] ?? '')) ?>" placeholder="e.g. ABC123" class="<?= $input ?> mt-1 font-mono uppercase"></label>
            <label class="block text-xs font-medium text-slate-500">Message for the customer (optional)
              <textarea name="ticket_note" rows="3" maxlength="1000" placeholder="e.g. E-ticket numbers 075-1234567890. Check in online 24 hours before departure. 1 checked bag included." class="<?= $input ?> mt-1"><?= e((string) ($booking['ticket_note'] ?? '')) ?></textarea></label>
            <div class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200">
              <p class="mb-2 text-xs font-semibold text-slate-600">🔒 Private — only admins see this, never the customer</p>
              <div class="grid grid-cols-2 gap-2">
                <label class="block text-xs font-medium text-slate-500">Bought from
                  <input name="bought_from" maxlength="100" value="<?= e((string) ($booking['bought_from'] ?? '')) ?>" placeholder="e.g. Agoda" class="<?= $input ?> mt-1"></label>
                <label class="block text-xs font-medium text-slate-500">What I paid (USD)
                  <input name="bought_cost" inputmode="decimal" value="<?= $booking['bought_cost'] !== null ? e(number_format($booking['bought_cost'], 2, '.', '')) : '' ?>" placeholder="e.g. 389.50" class="<?= $input ?> mt-1"></label>
              </div>
            </div>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><?= $isPaid ? 'Mark as ticketed &amp; email customer' : 'Save &amp; email corrected details' ?></button>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($booking['status'] !== 'cancelled'): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-5">
          <h2 class="mb-3 font-semibold text-slate-900">Cancel booking</h2>
          <form method="post" class="space-y-3" data-confirm="Cancel <?= e($ref) ?>? The customer will be emailed."><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
            <label class="block text-xs font-medium text-slate-500">Reason<input name="reason" maxlength="300" placeholder="e.g. Customer asked to cancel by phone" class="<?= $input ?> mt-1"></label>
            <?php if ($booking['status'] !== 'reserved'): ?><p class="text-xs text-amber-700">This trip is paid. Cancelling doesn't refund automatically — issue the refund in PayPal, Stripe or your bank.</p><?php endif; ?>
            <button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Cancel booking</button>
          </form>
        </section>
      <?php endif; ?>
      <?php if (!empty($q['supplier'])): $s = $q['supplier']; $margin = $booking['total'] - $s['net_usd']; ?>
        <section class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
          <h2 class="font-semibold text-slate-900">Supplier &amp; margin</h2>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Supplier</dt><dd class="font-medium text-slate-900"><?= ['duffel' => 'Duffel (flights)', 'liteapi_flights' => 'LiteAPI (flights)', 'liteapi' => 'LiteAPI (hotels)'][$s['provider']] ?? e($s['provider']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Offer ID</dt><dd class="truncate font-mono text-xs text-slate-700" title="<?= e($s['offer_id']) ?>"><?= e($s['offer_id']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Cost when booked</dt><dd class="font-medium text-slate-900"><?= $s['net_currency'] === 'USD' ? money($s['net_amount']) : e(number_format($s['net_amount'], 2) . ' ' . $s['net_currency']) . ' (≈' . money($s['net_usd']) . ')' ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer pays</dt><dd class="font-medium text-slate-900"><?= money($booking['total']) ?></dd></div>
            <div class="flex justify-between gap-3 border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Gross margin</dt>
              <dd class="font-bold <?= $margin >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= money($margin) ?> (<?= round($margin / $booking['total'] * 100) ?>%)</dd></div>
          </dl>
          <p class="mt-3 text-xs text-slate-500">Before supplier and card fees. Issue the ticket or room with <?= $s['provider'] === 'duffel' ? 'Duffel' : 'LiteAPI' ?> after payment — supplier prices can change until then.</p>
        </section>
      <?php endif; ?>
      <?= trip_summary($q) ?>
      <details class="rounded-xl border border-red-200 bg-white p-5"<?= str_contains((string) $error, 'To delete') ? ' open' : '' ?>>
        <summary class="cursor-pointer font-semibold text-red-700">Delete booking</summary>
        <p class="mt-2 text-sm text-slate-600">Permanently removes this booking and its activity log — use it for test or duplicate bookings.
          <?= in_array($booking['status'], ['paid', 'ticketed'], true) ? '<strong class="text-red-700">This booking was paid.</strong> Deleting it removes it from your records; the payment itself stays in PayPal and is not refunded.' : 'The customer is not emailed.' ?></p>
        <form method="post" class="mt-3 space-y-2" data-confirm="Delete <?= e($ref) ?> permanently? This can't be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <label class="block text-xs font-medium text-slate-500">Type <span class="font-mono font-semibold text-slate-700"><?= e($ref) ?></span> to confirm
            <input name="confirm_ref" autocomplete="off" spellcheck="false" class="<?= $input ?> mt-1 font-mono uppercase"></label>
          <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete permanently</button>
        </form>
      </details>
    </aside>
  </div>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
