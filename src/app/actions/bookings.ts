"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { formatPrice } from "@/lib/format";
import { buildQuote } from "@/lib/quote";
import { cancelBooking, createBooking, getBooking, setStripeSession, type Traveler } from "@/lib/server/bookings";
import { appUrl } from "@/lib/server/email";
import { createCheckoutSession, paymentsEnabled } from "@/lib/server/payments";
import { getCurrentUser } from "@/lib/server/dal";
import { notifyBooking } from "@/lib/server/notify";

export type BookingFormState =
  | {
      message?: string;
      errors?: Record<string, string>;
      values?: Record<string, string>;
    }
  | undefined;

const NAME_RE = /^[\p{L}][\p{L}\p{M}' .-]*$/u;
const PHONE_RE = /^\+?[0-9][0-9\s().-]{6,19}$/;

function field(formData: FormData, key: string) {
  const v = formData.get(key);
  return typeof v === "string" ? v.trim() : "";
}

/** Whole years between two YYYY-MM-DD dates. */
function ageOn(dob: string, on: string) {
  const [by, bm, bd] = dob.split("-").map(Number);
  const [y, m, d] = on.split("-").map(Number);
  return y - by - (m < bm || (m === bm && d < bd) ? 1 : 0);
}

export async function createBookingAction(
  _prev: BookingFormState,
  formData: FormData,
): Promise<BookingFormState> {
  const user = await getCurrentUser();
  const kind = field(formData, "kind");
  const query = field(formData, "query");
  if (!user) redirect(`/signin?next=${encodeURIComponent(`/book/${kind}?${query}`)}`);

  const values: Record<string, string> = {};
  for (const [k, v] of formData.entries()) {
    if (typeof v === "string" && !k.startsWith("$")) values[k] = v;
  }

  // Re-price on the server from the canonical query; the browser only tells us *what* to book.
  const quote = buildQuote(kind, Object.fromEntries(new URLSearchParams(query)));
  if (!quote) return { message: "Sorry, this deal is no longer available. Please search again.", values };

  if (Number(field(formData, "expectedTotal")) !== quote.total) {
    return {
      message: `The price has changed to ${formatPrice(quote.total)}. Please review and confirm again.`,
      values,
    };
  }

  const errors: Record<string, string> = {};
  const travelers: Traveler[] = quote.travelerSlots.map((slot, i) => {
    const firstName = field(formData, `t${i}_first`);
    const lastName = field(formData, `t${i}_last`);
    const dob = field(formData, `t${i}_dob`);

    if (!firstName || firstName.length > 50 || !NAME_RE.test(firstName))
      errors[`t${i}_first`] = "Enter a first name as it appears on the passport.";
    if (!lastName || lastName.length > 50 || !NAME_RE.test(lastName))
      errors[`t${i}_last`] = "Enter a last name as it appears on the passport.";

    if (slot.needsDob) {
      const valid = /^\d{4}-\d{2}-\d{2}$/.test(dob) && !Number.isNaN(Date.parse(dob));
      const age = valid ? ageOn(dob, quote.startDate) : -1;
      if (!valid || age < 0 || age > 120) errors[`t${i}_dob`] = "Enter a valid date of birth.";
      else if (slot.label.startsWith("Child") && (age < 2 || age > 11))
        errors[`t${i}_dob`] = "Children must be 2–11 years old on the travel date.";
      else if (slot.label.startsWith("Adult") && age < 12)
        errors[`t${i}_dob`] = "Adults must be 12 or older on the travel date.";
    }
    return { firstName, lastName, ...(slot.needsDob ? { dob } : {}) };
  });

  const contactEmail = field(formData, "email").toLowerCase();
  const contactPhone = field(formData, "phone");
  if (!z.email().safeParse(contactEmail).success) errors.email = "Enter a valid email address.";
  if (!PHONE_RE.test(contactPhone)) errors.phone = "Enter a valid phone number, including country code.";

  if (Object.keys(errors).length) {
    return { message: "Please fix the highlighted fields.", errors, values };
  }

  const reference = createBooking(user.id, { quote, travelers, contactEmail, contactPhone });
  await notifyBooking("reserved", user.id, reference);
  redirect(`/account/trips/${reference}?new=1`);
}

export async function cancelBookingAction(formData: FormData) {
  const user = await getCurrentUser();
  if (!user) redirect("/signin");
  const reference = String(formData.get("reference") ?? "");
  if (cancelBooking(user.id, reference)) await notifyBooking("cancelled", user.id, reference);
  redirect(`/account/trips/${encodeURIComponent(reference)}`);
}

export async function payBookingAction(formData: FormData) {
  const user = await getCurrentUser();
  if (!user) redirect("/signin");
  const reference = String(formData.get("reference") ?? "");
  const booking = getBooking(user.id, reference);
  const tripUrl = `/account/trips/${encodeURIComponent(reference)}`;
  if (!booking || booking.status !== "reserved" || !paymentsEnabled()) redirect(tripUrl);

  let checkoutUrl: string | null = null;
  try {
    const session = await createCheckoutSession(booking, await appUrl());
    setStripeSession(user.id, reference, session.id);
    checkoutUrl = session.url;
  } catch (err) {
    console.error(`[payments] Could not start checkout for ${reference}:`, err);
  }
  redirect(checkoutUrl ?? `${tripUrl}?payment=error`);
}
