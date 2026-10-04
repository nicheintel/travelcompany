import "server-only";
import { randomInt } from "node:crypto";
import type { Quote } from "../quote";
import { db } from "./db";

export type Traveler = { firstName: string; lastName: string; dob?: string };
export type BookingStatus = "reserved" | "paid" | "cancelled";

export type Booking = {
  reference: string;
  kind: Quote["kind"];
  status: BookingStatus;
  quote: Quote;
  travelers: Traveler[];
  contactEmail: string;
  contactPhone: string;
  total: number;
  startDate: string;
  createdAt: number;
  cancelledAt: number | null;
  paidAt: number | null;
  paymentMethod: string | null;
};

type BookingRow = {
  reference: string;
  kind: Quote["kind"];
  status: BookingStatus;
  quote_json: string;
  travelers_json: string;
  contact_email: string;
  contact_phone: string;
  total: number;
  start_date: string;
  created_at: number;
  cancelled_at: number | null;
  paid_at: number | null;
  payment_method: string | null;
  user_id: number;
};

function toBooking(r: BookingRow): Booking {
  return {
    reference: r.reference,
    kind: r.kind,
    status: r.status,
    quote: JSON.parse(r.quote_json),
    travelers: JSON.parse(r.travelers_json),
    contactEmail: r.contact_email,
    contactPhone: r.contact_phone,
    total: r.total,
    startDate: r.start_date,
    createdAt: r.created_at,
    cancelledAt: r.cancelled_at,
    paidAt: r.paid_at,
    paymentMethod: r.payment_method,
  };
}

// No 0/O/1/I so references are easy to read out over the phone.
const ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
function newReference() {
  return "TC-" + Array.from({ length: 6 }, () => ALPHABET[randomInt(ALPHABET.length)]).join("");
}

export function createBooking(
  userId: number,
  input: { quote: Quote; travelers: Traveler[]; contactEmail: string; contactPhone: string },
) {
  const insert = db.prepare(`
    INSERT INTO bookings (reference, user_id, kind, quote_json, travelers_json, contact_email,
                          contact_phone, total, start_date, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  for (let attempt = 0; attempt < 5; attempt++) {
    const reference = newReference();
    try {
      insert.run(
        reference,
        userId,
        input.quote.kind,
        JSON.stringify(input.quote),
        JSON.stringify(input.travelers),
        input.contactEmail,
        input.contactPhone,
        input.quote.total,
        input.quote.startDate,
        Date.now(),
      );
      addEvent(reference, userId, "created", "Reserved by the customer online.");
      return reference;
    } catch (err) {
      if ((err as { code?: string }).code !== "SQLITE_CONSTRAINT_UNIQUE") throw err;
    }
  }
  throw new Error("Could not allocate a booking reference");
}

export function listBookings(userId: number): Booking[] {
  const rows = db
    .prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY start_date ASC, created_at DESC")
    .all(userId) as BookingRow[];
  return rows.map(toBooking);
}

/** Only returns the booking if it belongs to `userId`. */
export function getBooking(userId: number, reference: string): Booking | null {
  const row = db
    .prepare("SELECT * FROM bookings WHERE reference = ? AND user_id = ?")
    .get(reference, userId) as BookingRow | undefined;
  return row ? toBooking(row) : null;
}

export function cancelBooking(userId: number, reference: string) {
  const info = db
    .prepare(
      "UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE reference = ? AND user_id = ? AND status = 'reserved'",
    )
    .run(Date.now(), reference, userId);
  if (info.changes > 0) addEvent(reference, userId, "cancelled", "Cancelled by the customer.");
  return info.changes > 0;
}

/** Remember the Stripe Checkout session started for a reserved booking. */
export function setStripeSession(userId: number, reference: string, sessionId: string) {
  db.prepare("UPDATE bookings SET stripe_session_id = ? WHERE reference = ? AND user_id = ?").run(
    sessionId,
    reference,
    userId,
  );
}

/** Owner and amount due, for checking a payment against the booking (no user context). */
export function getBookingForPayment(reference: string) {
  const row = db
    .prepare("SELECT user_id, total, status FROM bookings WHERE reference = ?")
    .get(reference) as { user_id: number; total: number; status: BookingStatus } | undefined;
  return row ? { userId: row.user_id, total: row.total, status: row.status } : null;
}

/**
 * reserved → paid. Returns true only for the call that made the change, so the
 * receipt email is sent once even if the webhook and the return page both report it.
 */
export function markBookingPaid(reference: string, sessionId: string) {
  const info = db
    .prepare(
      "UPDATE bookings SET status = 'paid', paid_at = ?, stripe_session_id = ?, payment_method = 'Card (Stripe)' WHERE reference = ? AND status = 'reserved'",
    )
    .run(Date.now(), sessionId, reference);
  if (info.changes > 0) addEvent(reference, null, "paid", `Paid by card via Stripe (session ${sessionId}).`);
  return info.changes > 0;
}

// ---------------------------------------------------------------------------
// Activity log & admin

export type BookingEventType = "created" | "paid" | "cancelled" | "note";
export type BookingEvent = {
  id: number;
  type: BookingEventType;
  message: string;
  createdAt: number;
  actorName: string | null;
};

export function addEvent(reference: string, actorId: number | null, type: BookingEventType, message: string) {
  db.prepare(
    "INSERT INTO booking_events (reference, actor_id, type, message, created_at) VALUES (?, ?, ?, ?, ?)",
  ).run(reference, actorId, type, message, Date.now());
}

export function listEvents(reference: string): BookingEvent[] {
  const rows = db
    .prepare(
      `SELECT e.id, e.type, e.message, e.created_at, u.name AS actor_name
       FROM booking_events e LEFT JOIN users u ON u.id = e.actor_id
       WHERE e.reference = ? ORDER BY e.created_at DESC, e.id DESC`,
    )
    .all(reference) as { id: number; type: BookingEventType; message: string; created_at: number; actor_name: string | null }[];
  return rows.map((r) => ({ id: r.id, type: r.type, message: r.message, createdAt: r.created_at, actorName: r.actor_name }));
}

export type AdminBooking = Booking & { customer: { id: number; name: string; email: string } };
type AdminRow = BookingRow & { customer_name: string; customer_email: string };

function toAdminBooking(r: AdminRow): AdminBooking {
  return { ...toBooking(r), customer: { id: r.user_id, name: r.customer_name, email: r.customer_email } };
}

const ADMIN_SELECT = `SELECT b.*, u.name AS customer_name, u.email AS customer_email
  FROM bookings b JOIN users u ON u.id = b.user_id`;

export function adminGetBooking(reference: string): AdminBooking | null {
  const row = db.prepare(`${ADMIN_SELECT} WHERE b.reference = ?`).get(reference) as AdminRow | undefined;
  return row ? toAdminBooking(row) : null;
}

export type BookingFilters = { q?: string; status?: string; kind?: string; userId?: number; page?: number };
const PAGE_SIZE = 50;

export function adminSearchBookings({ q, status, kind, userId, page = 1 }: BookingFilters) {
  const where: string[] = [];
  const args: (string | number)[] = [];
  if (q?.trim()) {
    const like = `%${q.trim().toLowerCase()}%`;
    where.push(
      "(lower(b.reference) LIKE ? OR lower(u.name) LIKE ? OR u.email LIKE ? OR b.contact_email LIKE ? OR lower(b.travelers_json) LIKE ? OR b.contact_phone LIKE ?)",
    );
    args.push(like, like, like, like, like, like);
  }
  if (status && ["reserved", "paid", "cancelled"].includes(status)) {
    where.push("b.status = ?");
    args.push(status);
  }
  if (kind && ["flight", "hotel", "package"].includes(kind)) {
    where.push("b.kind = ?");
    args.push(kind);
  }
  if (userId) {
    where.push("b.user_id = ?");
    args.push(userId);
  }
  const rows = db
    .prepare(
      `${ADMIN_SELECT} ${where.length ? `WHERE ${where.join(" AND ")}` : ""}
       ORDER BY b.created_at DESC LIMIT ? OFFSET ?`,
    )
    .all(...args, PAGE_SIZE + 1, (page - 1) * PAGE_SIZE) as AdminRow[];
  return { bookings: rows.slice(0, PAGE_SIZE).map(toAdminBooking), hasMore: rows.length > PAGE_SIZE };
}

const DAY = 24 * 60 * 60 * 1000;

export function adminStats(today: string) {
  const now = Date.now();
  const one = <T>(sql: string, ...args: (string | number)[]) => db.prepare(sql).get(...args) as T;
  return {
    awaitingPayment: one<{ n: number; total: number | null }>(
      "SELECT COUNT(*) n, SUM(total) total FROM bookings WHERE status = 'reserved' AND start_date >= ?",
      today,
    ),
    bookings7d: one<{ n: number }>("SELECT COUNT(*) n FROM bookings WHERE created_at >= ?", now - 7 * DAY).n,
    revenue30d:
      one<{ total: number | null }>(
        "SELECT SUM(total) total FROM bookings WHERE status = 'paid' AND paid_at >= ?",
        now - 30 * DAY,
      ).total ?? 0,
    newUsers7d: one<{ n: number }>("SELECT COUNT(*) n FROM users WHERE created_at >= ?", now - 7 * DAY).n,
  };
}

/**
 * Unpaid trips that need a call: departing within 14 days, or reserved more than
 * 24 hours ago (the promised call-back window). Soonest departure first.
 */
export function adminNeedsAttention(today: string, soonDate: string) {
  const rows = db
    .prepare(
      `${ADMIN_SELECT} WHERE b.status = 'reserved' AND b.start_date >= ?
         AND (b.start_date <= ? OR b.created_at <= ?)
       ORDER BY b.start_date ASC LIMIT 20`,
    )
    .all(today, soonDate, Date.now() - DAY) as AdminRow[];
  return rows.map(toAdminBooking);
}

/** Staff recorded a payment taken by phone, bank transfer, etc. */
export function adminMarkPaid(reference: string, actorId: number, method: string, note: string) {
  const info = db
    .prepare(
      "UPDATE bookings SET status = 'paid', paid_at = ?, payment_method = ? WHERE reference = ? AND status = 'reserved'",
    )
    .run(Date.now(), method, reference);
  if (info.changes > 0) addEvent(reference, actorId, "paid", `Marked as paid — ${method}.${note ? ` ${note}` : ""}`);
  return info.changes > 0;
}

export function adminCancel(reference: string, actorId: number, reason: string) {
  const before = db.prepare("SELECT status FROM bookings WHERE reference = ?").get(reference) as
    | { status: BookingStatus }
    | undefined;
  if (!before || before.status === "cancelled") return false;
  db.prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE reference = ?").run(Date.now(), reference);
  addEvent(
    reference,
    actorId,
    "cancelled",
    `Cancelled by staff${before.status === "paid" ? " (was paid — refund to be processed)" : ""}. Reason: ${reason}`,
  );
  return true;
}
