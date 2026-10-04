import "server-only";
import { createHmac, timingSafeEqual } from "node:crypto";
import type { Booking } from "./bookings";
import { getBookingForPayment, markBookingPaid } from "./bookings";
import { notifyBooking } from "./notify";

/**
 * Stripe Checkout over Stripe's REST API (no SDK needed).
 * STRIPE_API_BASE exists only so tests can point at a local fake Stripe.
 */
const API_BASE = process.env.STRIPE_API_BASE ?? "https://api.stripe.com";
const CURRENCY = "usd";

/** Online card payments are on when a Stripe secret key is configured. */
export function paymentsEnabled() {
  return !!process.env.STRIPE_SECRET_KEY;
}

type CheckoutSession = {
  id: string;
  url: string | null;
  payment_status: "paid" | "unpaid" | "no_payment_required";
  amount_total: number | null;
  currency: string | null;
  client_reference_id: string | null;
  metadata: Record<string, string> | null;
};

async function stripe<T>(method: "GET" | "POST", path: string, form?: Record<string, string>): Promise<T> {
  const res = await fetch(`${API_BASE}/v1/${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${process.env.STRIPE_SECRET_KEY}`,
      ...(form ? { "Content-Type": "application/x-www-form-urlencoded" } : {}),
    },
    body: form ? new URLSearchParams(form).toString() : undefined,
    cache: "no-store",
  });
  const json = await res.json();
  if (!res.ok) throw new Error(`Stripe ${res.status}: ${json?.error?.message ?? "request failed"}`);
  return json as T;
}

export async function createCheckoutSession(booking: Booking, baseUrl: string) {
  const tripUrl = `${baseUrl}/account/trips/${booking.reference}`;
  return stripe<CheckoutSession>("POST", "checkout/sessions", {
    mode: "payment",
    success_url: `${tripUrl}?session_id={CHECKOUT_SESSION_ID}`,
    cancel_url: tripUrl,
    customer_email: booking.contactEmail,
    client_reference_id: booking.reference,
    "metadata[reference]": booking.reference,
    "payment_intent_data[metadata][reference]": booking.reference,
    "line_items[0][quantity]": "1",
    "line_items[0][price_data][currency]": CURRENCY,
    "line_items[0][price_data][unit_amount]": String(booking.total * 100),
    "line_items[0][price_data][product_data][name]": `${booking.quote.title} (${booking.reference})`,
    "line_items[0][price_data][product_data][description]": booking.quote.subtitle,
  });
}

export async function retrieveCheckoutSession(id: string) {
  return stripe<CheckoutSession>("GET", `checkout/sessions/${encodeURIComponent(id)}`);
}

/**
 * Marks the booking paid if the session really paid the right amount for it.
 * Safe to call repeatedly (webhook + return page); emails the receipt once.
 */
export async function applyPaidSession(session: CheckoutSession) {
  const reference = session.metadata?.reference ?? session.client_reference_id;
  if (!reference || session.payment_status !== "paid") return false;
  const booking = getBookingForPayment(reference);
  if (!booking) return false;
  if (session.currency !== CURRENCY || session.amount_total !== booking.total * 100) {
    console.error(`[payments] Amount mismatch for ${reference}: got ${session.amount_total} ${session.currency}`);
    return false;
  }
  if (markBookingPaid(reference, session.id)) await notifyBooking("paid", booking.userId, reference);
  return true;
}

const TOLERANCE_SECONDS = 300;

/** Verifies a Stripe-Signature header (HMAC-SHA256 over "timestamp.body"). */
export function verifyStripeSignature(rawBody: string, header: string | null, secret: string) {
  if (!header) return false;
  const parts = header.split(",").map((p) => p.split("=") as [string, string]);
  const timestamp = Number(parts.find(([k]) => k === "t")?.[1]);
  const signatures = parts.filter(([k]) => k === "v1").map(([, v]) => v);
  if (!timestamp || !signatures.length) return false;
  if (Math.abs(Date.now() / 1000 - timestamp) > TOLERANCE_SECONDS) return false;

  const expected = createHmac("sha256", secret).update(`${timestamp}.${rawBody}`).digest();
  return signatures.some((sig) => {
    const given = Buffer.from(sig, "hex");
    return given.length === expected.length && timingSafeEqual(given, expected);
  });
}
