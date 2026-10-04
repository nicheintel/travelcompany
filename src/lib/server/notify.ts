import "server-only";
import { getBooking } from "./bookings";
import { appUrl, sendEmail } from "./email";
import { bookingCancelledEmail, bookingPaidEmail, bookingReservedEmail } from "./email-templates";
import { paymentsEnabled } from "./payments";
import { findUserById } from "./users";

/** Emails the booking's contact address about a status change. Never throws. */
export async function notifyBooking(event: "reserved" | "paid" | "cancelled", userId: number, reference: string) {
  try {
    const user = findUserById(userId);
    const booking = getBooking(userId, reference);
    if (!user || !booking) return;
    const firstName = booking.travelers[0]?.firstName || user.name.split(" ")[0];
    const link = `${await appUrl()}/account/trips/${reference}`;
    const content =
      event === "reserved"
        ? bookingReservedEmail(firstName, booking, link, paymentsEnabled())
        : event === "paid"
          ? bookingPaidEmail(firstName, booking, link)
          : bookingCancelledEmail(firstName, booking, link);
    await sendEmail({ to: booking.contactEmail, ...content });
  } catch (err) {
    console.error(`[email] Could not send ${event} email for ${reference}:`, err);
  }
}
