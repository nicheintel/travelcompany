<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * GCash (manual): the customer sends the peso amount to the business GCash account, then enters the
 * GCash reference number on their trip page. An admin checks it arrived in the GCash app and confirms.
 */

/** On when a GCash number is saved and today's peso exchange rate is known (the amount must be real). */
function gcash_enabled(): bool
{
    return trim((string) config('gcash_number')) !== '' && isset(fx_rates()['rates']['PHP']);
}

/** The US-dollar total in whole pesos at today's rate, rounded up. */
function gcash_amount(int $usd): ?int
{
    $rate = fx_rates()['rates']['PHP'] ?? null;
    return $rate ? (int) ceil($usd * $rate) : null;
}

/** Customer: "I've paid" with the GCash reference number. Re-sending replaces the previous number. */
function submit_gcash(string $ref, int $userId, string $gcashRef, int $pesos): bool
{
    $n = db_run(
        "UPDATE bookings SET gcash_ref = ?, gcash_php = ?, gcash_sent_at = ? WHERE reference = ? AND user_id = ? AND status = 'reserved'",
        [$gcashRef, $pesos, now_utc(), $ref, $userId],
    );
    if (!$n) return false;
    add_event($ref, $userId, 'note', "Customer says they paid ₱" . number_format($pesos) . " by GCash — reference $gcashRef. Check the GCash app, then confirm.");
    notify_admins_gcash($ref, $gcashRef, $pesos);
    return true;
}

/** Admin: the GCash payment wasn't found — clear it so the customer can try again. */
function reject_gcash(string $ref, int $adminId): bool
{
    $r = db_one("SELECT gcash_ref FROM bookings WHERE reference = ? AND status = 'reserved' AND gcash_ref IS NOT NULL", [$ref]);
    if (!$r) return false;
    db_run('UPDATE bookings SET gcash_ref = NULL, gcash_php = NULL, gcash_sent_at = NULL WHERE reference = ?', [$ref]);
    add_event($ref, $adminId, 'note', "GCash reference {$r['gcash_ref']} not found in the GCash account — the customer was asked to check it.");
    return true;
}

/** Emails every admin that a GCash payment is waiting to be checked. */
function notify_admins_gcash(string $ref, string $gcashRef, int $pesos): void
{
    in_english(function () use ($ref, $gcashRef, $pesos) {
        $admins = array_unique(array_merge(configured_admins(), array_column(db_all("SELECT email FROM users WHERE role = 'admin'"), 'email')));
        if (!$admins) return;
        try {
            $link = app_url() . '/admin/booking.php?ref=' . rawurlencode($ref);
        } catch (RuntimeException $e) {
            $link = url('admin/booking.php', ['ref' => $ref]);
        }
        $mail = simple_email("GCash payment to check: $ref (₱" . number_format($pesos) . ')', 'Check a GCash payment', 'there', [
            "A customer says they sent ₱" . number_format($pesos) . " by GCash for booking $ref.",
            "GCash reference: $gcashRef",
            'Open your GCash app → Transactions and check the money arrived before confirming. Never confirm from a screenshot alone.',
        ], [], $link, 'Open the booking');
        foreach ($admins as $to) {
            try { send_email($to, $mail); } catch (Throwable $e) { error_log('[gcash] admin email failed: ' . $e->getMessage()); }
        }
    });
}

/** Customer: the GCash payment couldn't be found. */
function email_gcash_not_found(array $b): void
{
    in_english(function () use ($b) {
        $mail = simple_email("We couldn't find your GCash payment ({$b['reference']})", 'Please check your GCash payment', $b['travelers'][0]['first'] ?? 'there', [
            "We couldn't find a GCash payment with the reference number you entered for trip {$b['reference']}.",
            'Please check the reference number in your GCash app (Transactions) and enter it again on your trip page — or reply to this email and we\'ll help.',
        ], trip_rows($b), pay_link($b['reference']), 'Open my trip');
        send_email($b['contact_email'], $mail);
    });
}
