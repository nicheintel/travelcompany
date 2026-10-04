import "server-only";
import { randomInt } from "node:crypto";
import type { Quote } from "../quote";
import { db } from "./db";

export type Traveler = { firstName: string; lastName: string; dob?: string };
export type BookingStatus = "reserved" | "cancelled";

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
  return info.changes > 0;
}
