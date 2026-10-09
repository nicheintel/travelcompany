<?php
declare(strict_types=1);
defined('TC_APP') || exit;

function to_booking(array $r): array
{
    return [
        'reference' => $r['reference'], 'user_id' => (int) $r['user_id'], 'kind' => $r['kind'], 'status' => $r['status'],
        'quote' => json_decode($r['quote_json'], true), 'travelers' => json_decode($r['travelers_json'], true),
        'contact_email' => $r['contact_email'], 'contact_phone' => $r['contact_phone'], 'contact_name' => $r['contact_name'] ?? null,
        'bag_status' => $r['bag_status'] ?? null, 'total' => (int) $r['total'],
        'start_date' => $r['start_date'], 'created_at' => $r['created_at'], 'paid_at' => $r['paid_at'],
        'cancelled_at' => $r['cancelled_at'], 'payment_method' => $r['payment_method'],
        'ticketed_at' => $r['ticketed_at'] ?? null, 'supplier_ref' => $r['supplier_ref'] ?? null, 'ticket_note' => $r['ticket_note'] ?? null,
        // Admin-only: where the ticket was bought and what it cost (never shown to customers).
        'gcash_ref' => $r['gcash_ref'] ?? null, 'gcash_php' => isset($r['gcash_php']) ? (int) $r['gcash_php'] : null, 'gcash_sent_at' => $r['gcash_sent_at'] ?? null,
        'bought_from' => $r['bought_from'] ?? null, 'bought_cost' => isset($r['bought_cost']) ? (float) $r['bought_cost'] : null,
        'customer_name' => $r['customer_name'] ?? null, 'customer_email' => $r['customer_email'] ?? null,
    ];
}

/**
 * A traveler slot label from a quote ("Adult 1", "Child 2", "Infant 1", "Lead guest"), translated for display.
 * The stored label stays English; unknown labels are shown as they are.
 */
function slot_label(string $label): string
{
    if ($label === 'Lead guest') return t('Lead guest');
    if (!preg_match('/^(Adult|Child|Infant) (\d+)$/', $label, $m)) return $label;
    return match ($m[1]) {
        'Adult' => t('Adult {n}', ['n' => $m[2]]),
        'Child' => t('Child {n}', ['n' => $m[2]]),
        'Infant' => t('Infant {n}', ['n' => $m[2]]),
    };
}

function add_event(string $reference, ?int $actorId, string $type, string $message): void
{
    db_run('INSERT INTO booking_events (reference, actor_id, type, message, created_at) VALUES (?, ?, ?, ?, ?)', [$reference, $actorId, $type, $message, now_utc()]);
}

function list_events(string $reference): array
{
    return db_all(
        'SELECT e.*, u.name AS actor_name FROM booking_events e LEFT JOIN users u ON u.id = e.actor_id
         WHERE e.reference = ? ORDER BY e.created_at DESC, e.id DESC',
        [$reference],
    );
}

/** Saves a booking with a reference like TC-K7MP2Q (no 0/O/1/I, easy to read on the phone). */
function create_booking(int $userId, array $quote, array $travelers, string $email, string $phone, ?string $contactName = null): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bags = count(array_filter($travelers, fn($t) => !empty($t['extra_bag'])));
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $ref = 'FF-'; // FareFinders (older bookings start with TC-)
        for ($i = 0; $i < 6; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        try {
            db_run(
                'INSERT INTO bookings (reference, user_id, kind, quote_json, travelers_json, contact_email, contact_phone, contact_name, bag_status, total, start_date, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$ref, $userId, $quote['kind'], json_encode($quote, JSON_UNESCAPED_UNICODE), json_encode($travelers, JSON_UNESCAPED_UNICODE), $email, $phone, $contactName, $bags ? 'pending' : null, $quote['total'], $quote['start_date'], now_utc()],
            );
            add_event($ref, $userId, 'created', 'Reserved by the customer online.' . ($bags ? ' Requested ' . plural($bags, 'checked bag') . ' — set the price before the customer pays.' : ''));
            return $ref;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e; // 1062 = duplicate reference, try again
        }
    }
    throw new RuntimeException('Could not allocate a booking reference');
}

function user_bookings(int $userId): array
{
    return array_map('to_booking', db_all('SELECT * FROM bookings WHERE user_id = ? ORDER BY start_date ASC, created_at DESC', [$userId]));
}

/** Bookings that aren't cancelled and whose trip hasn't ended yet. */
function upcoming_bookings(int $userId): array
{
    return array_values(array_filter(user_bookings($userId), fn($b) => $b['status'] !== 'cancelled' && ($b['quote']['end_date'] ?? $b['start_date']) >= today()));
}

/** Only returns the booking if it belongs to the user. */
function user_booking(int $userId, string $reference): ?array
{
    $r = db_one('SELECT * FROM bookings WHERE reference = ? AND user_id = ?', [$reference, $userId]);
    return $r ? to_booking($r) : null;
}

function customer_cancel(int $userId, string $reference): bool
{
    $n = db_run("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE reference = ? AND user_id = ? AND status = 'reserved'", [now_utc(), $reference, $userId]);
    if ($n) add_event($reference, $userId, 'cancelled', 'Cancelled by the customer.');
    return $n > 0;
}

/** reserved → paid, once. Returns true only for the call that made the change. */
function mark_paid(string $reference, string $method, ?int $actorId, string $eventMessage, ?string $paymentRef = null): bool
{
    $n = db_run(
        "UPDATE bookings SET status = 'paid', paid_at = ?, payment_method = ?, payment_ref = COALESCE(?, payment_ref)
         WHERE reference = ? AND status = 'reserved'",
        [now_utc(), $method, $paymentRef, $reference],
    );
    if ($n) add_event($reference, $actorId, 'paid', $eventMessage);
    return $n > 0;
}

// ---------- Admin ----------

const ADMIN_SELECT = 'SELECT b.*, u.name AS customer_name, u.email AS customer_email FROM bookings b JOIN users u ON u.id = b.user_id';

function admin_booking(string $reference): ?array
{
    $r = db_one(ADMIN_SELECT . ' WHERE b.reference = ?', [$reference]);
    return $r ? to_booking($r) : null;
}

function admin_search_bookings(array $f, int $page = 1, int $perPage = 50): array
{
    $where = [];
    $args = [];
    if (($q = trim($f['q'] ?? '')) !== '') {
        $like = '%' . mb_strtolower($q) . '%';
        $where[] = '(LOWER(b.reference) LIKE ? OR LOWER(u.name) LIKE ? OR u.email LIKE ? OR b.contact_email LIKE ? OR LOWER(b.travelers_json) LIKE ? OR b.contact_phone LIKE ?)';
        array_push($args, $like, $like, $like, $like, $like, $like);
    }
    if (in_array($f['status'] ?? '', ['reserved', 'paid', 'ticketed', 'cancelled'], true)) {
        $where[] = 'b.status = ?';
        $args[] = $f['status'];
    }
    if (in_array($f['kind'] ?? '', BOOKING_KINDS, true)) {
        $where[] = 'b.kind = ?';
        $args[] = $f['kind'];
    }
    if (!empty($f['user'])) {
        $where[] = 'b.user_id = ?';
        $args[] = (int) $f['user'];
    }
    $sql = ADMIN_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.created_at DESC LIMIT ' . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage);
    $rows = db_all($sql, $args);
    return ['bookings' => array_map('to_booking', array_slice($rows, 0, $perPage)), 'has_more' => count($rows) > $perPage];
}

function admin_stats(): array
{
    $awaiting = db_one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) total FROM bookings WHERE status = 'reserved' AND start_date >= ?", [today()]);
    return [
        'awaiting' => (int) $awaiting['n'],
        'awaiting_total' => (int) $awaiting['total'],
        'bookings_7d' => (int) db_one('SELECT COUNT(*) n FROM bookings WHERE created_at >= ?', [gmdate('Y-m-d H:i:s', time() - 7 * 86400)])['n'],
        'to_ticket' => (int) db_one("SELECT COUNT(*) n FROM bookings WHERE status = 'paid'")['n'],
        'revenue_30d' => (int) db_one("SELECT COALESCE(SUM(total), 0) t FROM bookings WHERE status IN ('paid', 'ticketed') AND paid_at >= ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)])['t'],
        'users_7d' => (int) db_one('SELECT COUNT(*) n FROM users WHERE created_at >= ?', [gmdate('Y-m-d H:i:s', time() - 7 * 86400)])['n'],
    ];
}

/** Paid trips whose tickets/rooms haven't been issued yet. Paid longest ago first. */
/** Unpaid bookings where the customer says they sent a GCash payment. */
function admin_gcash_to_check(): array
{
    return array_map('to_booking', db_all(ADMIN_SELECT . " WHERE b.status = 'reserved' AND b.gcash_ref IS NOT NULL ORDER BY b.gcash_sent_at ASC LIMIT 50"));
}

function admin_needs_ticket(): array
{
    return array_map('to_booking', db_all(ADMIN_SELECT . " WHERE b.status = 'paid' ORDER BY b.paid_at ASC LIMIT 50"));
}

/**
 * Records the airline/hotel confirmation after staff bought it from the supplier.
 * Also used to correct the details later (the customer is emailed again).
 */
function mark_ticketed(string $reference, int $actorId, string $code, string $note, ?string $boughtFrom = null, ?float $boughtCost = null): bool
{
    $before = db_one('SELECT status FROM bookings WHERE reference = ?', [$reference]);
    if (!$before || !in_array($before['status'], ['paid', 'ticketed'], true)) return false;
    db_run(
        "UPDATE bookings SET status = 'ticketed', supplier_ref = ?, ticket_note = ?, bought_from = ?, bought_cost = ?, ticketed_at = COALESCE(ticketed_at, ?) WHERE reference = ?",
        [$code, $note !== '' ? $note : null, $boughtFrom !== '' ? $boughtFrom : null, $boughtCost, now_utc(), $reference],
    );
    add_event($reference, $actorId, 'ticketed', ($before['status'] === 'ticketed' ? 'Ticket details updated' : 'Ticket issued') . " — confirmation $code." . ($note !== '' ? " Note to customer: $note" : ''));
    return true;
}

/** Unpaid trips departing within 14 days, or reserved over 24 hours ago. Soonest first. */
function admin_needs_attention(): array
{
    return array_map('to_booking', db_all(
        ADMIN_SELECT . " WHERE b.status = 'reserved' AND b.start_date >= ? AND (b.start_date <= ? OR b.created_at <= ?) ORDER BY b.start_date ASC LIMIT 20",
        [today(), add_days(today(), 14), gmdate('Y-m-d H:i:s', time() - 86400)],
    ));
}

function admin_cancel(string $reference, int $actorId, string $reason): bool
{
    $before = db_one('SELECT status FROM bookings WHERE reference = ?', [$reference]);
    if (!$before || $before['status'] === 'cancelled') return false;
    db_run("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE reference = ?", [now_utc(), $reference]);
    add_event($reference, $actorId, 'cancelled', 'Cancelled by staff' . (in_array($before['status'], ['paid', 'ticketed'], true) ? ' (was paid — refund to be processed)' : '') . ". Reason: $reason");
    return true;
}

function search_users(string $q, int $page, int $perPage = 50): array
{
    $where = '';
    $args = [];
    if (trim($q) !== '') {
        $like = '%' . mb_strtolower(trim($q)) . '%';
        $where = 'WHERE LOWER(u.name) LIKE ? OR u.email LIKE ?';
        $args = [$like, $like];
    }
    $rows = db_all(
        "SELECT u.*, COUNT(b.id) AS booking_count FROM users u LEFT JOIN bookings b ON b.user_id = u.id $where
         GROUP BY u.id ORDER BY u.created_at DESC LIMIT " . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
        $args,
    );
    $users = array_map(fn($r) => to_user($r) + ['booking_count' => (int) $r['booking_count']], array_slice($rows, 0, $perPage));
    return ['users' => $users, 'has_more' => count($rows) > $perPage];
}

/**
 * Admin: the airline's price for the requested checked bags is known. Adds it to the unpaid booking
 * (total and price breakdown), or with $amount = null records that bags can't be added.
 */
function settle_bag_request(string $ref, ?int $amount, int $adminId, bool $alreadyIncluded = false): bool
{
    $r = db_one("SELECT * FROM bookings WHERE reference = ? AND status = 'reserved' AND bag_status = 'pending'", [$ref]);
    if (!$r) return false;
    $bags = count(array_filter(json_decode($r['travelers_json'], true), fn($t) => !empty($t['extra_bag'])));
    if ($amount === null && $alreadyIncluded) {
        db_run("UPDATE bookings SET bag_status = 'included' WHERE reference = ?", [$ref]);
        add_event($ref, $adminId, 'note', 'The requested checked bag is already included in the fare — no extra charge.');
        return true;
    }
    if ($amount === null) {
        db_run("UPDATE bookings SET bag_status = 'declined' WHERE reference = ?", [$ref]);
        add_event($ref, $adminId, 'note', 'Checked bags could not be added — the booking total is unchanged.');
        return true;
    }
    $quote = json_decode($r['quote_json'], true);
    $quote['lines'][] = ['label' => 'Checked bag × ' . $bags, 'amount' => $amount];
    $quote['subtotal'] += $amount;
    $quote['total'] += $amount;
    db_run("UPDATE bookings SET bag_status = 'added', total = total + ?, quote_json = ? WHERE reference = ? AND status = 'reserved'",
        [$amount, json_encode($quote, JSON_UNESCAPED_UNICODE), $ref]);
    add_event($ref, $adminId, 'note', 'Added ' . plural($bags, 'checked bag') . ' for ' . money($amount) . '. New total ' . money($quote['total']) . '.');
    return true;
}

// ---------- Before paying: the checklist and Travel Care Protection ----------

const TRAVEL_CARE_LABEL = 'Travel Care Protection';

function travel_care_rate(): float
{
    return max(0.0, min(1.0, (float) config('travel_care_rate')));
}

/** Our own fee (USD) per change or cancellation of a paid booking; Travel Care waives it. */
function change_service_fee(): int
{
    return max(0, (int) config('change_service_fee'));
}

/** Travel Care is offered on flights, before payment. */
function travel_care_available(array $b): bool
{
    return $b['kind'] === 'flight' && $b['status'] === 'reserved' && travel_care_rate() > 0 && empty($b['gcash_ref']);
}

/** The ticket price Travel Care is a share of: the total without checked bags or Travel Care itself. */
function travel_care_price(array $b): int
{
    $extras = array_sum(array_map(fn($l) => preg_match('/^(Checked bag × \d+|' . TRAVEL_CARE_LABEL . ')$/', $l['label']) ? $l['amount'] : 0, $b['quote']['lines']));
    return (int) ceil(max(0, $b['total'] - $extras) * travel_care_rate());
}

/**
 * The customer went through the "Before you pay" checklist and agreed: records that, and adds or removes
 * Travel Care Protection on the unpaid booking (total and price breakdown). False if it can't be changed.
 */
function review_booking(string $ref, int $userId, bool $care): bool
{
    $r = db_one("SELECT * FROM bookings WHERE reference = ? AND user_id = ? AND status = 'reserved' AND gcash_ref IS NULL", [$ref, $userId]);
    if (!$r) return false;
    $b = to_booking($r);
    $q = $b['quote'];
    $old = (int) ($q['care'] ?? 0);
    $q['lines'] = array_values(array_filter($q['lines'], fn($l) => $l['label'] !== TRAVEL_CARE_LABEL));
    $b['quote'] = $q;
    $b['total'] -= $old;
    $new = $care && travel_care_available($b) ? travel_care_price($b) : 0;
    if ($new) $q['lines'][] = ['label' => TRAVEL_CARE_LABEL, 'amount' => $new];
    $q['subtotal'] += $new - $old;
    $q['total'] = $b['total'] + $new;
    $q['care'] = $new;
    $q['reviewed_at'] = now_utc();
    db_run("UPDATE bookings SET total = ?, quote_json = ? WHERE reference = ? AND status = 'reserved'", [$q['total'], json_encode($q, JSON_UNESCAPED_UNICODE), $ref]);
    if ($new !== $old) {
        add_event($ref, $userId, 'note', ($new ? 'Customer added Travel Care Protection (' . money($new) . ')' : 'Customer removed Travel Care Protection') . '. New total ' . money($q['total']) . '.');
    }
    return true;
}

/** Admin: permanently removes a booking and its activity log. */
function delete_booking(string $ref): bool
{
    return db_run('DELETE FROM bookings WHERE reference = ?', [$ref]) > 0;
}
