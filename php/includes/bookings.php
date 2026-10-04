<?php
declare(strict_types=1);

function to_booking(array $r): array
{
    return [
        'reference' => $r['reference'], 'user_id' => (int) $r['user_id'], 'kind' => $r['kind'], 'status' => $r['status'],
        'quote' => json_decode($r['quote_json'], true), 'travelers' => json_decode($r['travelers_json'], true),
        'contact_email' => $r['contact_email'], 'contact_phone' => $r['contact_phone'], 'total' => (int) $r['total'],
        'start_date' => $r['start_date'], 'created_at' => $r['created_at'], 'paid_at' => $r['paid_at'],
        'cancelled_at' => $r['cancelled_at'], 'payment_method' => $r['payment_method'],
        'customer_name' => $r['customer_name'] ?? null, 'customer_email' => $r['customer_email'] ?? null,
    ];
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
function create_booking(int $userId, array $quote, array $travelers, string $email, string $phone): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $ref = 'TC-';
        for ($i = 0; $i < 6; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        try {
            db_run(
                'INSERT INTO bookings (reference, user_id, kind, quote_json, travelers_json, contact_email, contact_phone, total, start_date, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$ref, $userId, $quote['kind'], json_encode($quote, JSON_UNESCAPED_UNICODE), json_encode($travelers, JSON_UNESCAPED_UNICODE), $email, $phone, $quote['total'], $quote['start_date'], now_utc()],
            );
            add_event($ref, $userId, 'created', 'Reserved by the customer online.');
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
    if (in_array($f['status'] ?? '', ['reserved', 'paid', 'cancelled'], true)) {
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
        'revenue_30d' => (int) db_one("SELECT COALESCE(SUM(total), 0) t FROM bookings WHERE status = 'paid' AND paid_at >= ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)])['t'],
        'users_7d' => (int) db_one('SELECT COUNT(*) n FROM users WHERE created_at >= ?', [gmdate('Y-m-d H:i:s', time() - 7 * 86400)])['n'],
    ];
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
    add_event($reference, $actorId, 'cancelled', 'Cancelled by staff' . ($before['status'] === 'paid' ? ' (was paid — refund to be processed)' : '') . ". Reason: $reason");
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
