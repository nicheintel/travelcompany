import "server-only";
import { formatDate, formatPrice } from "../format";
import { SITE_NAME } from "../site";
import type { Booking } from "./bookings";
import { type Email, escapeHtml as e } from "./email";

const BRAND = "#1c54f0";

function layout(heading: string, body: string) {
  return `<!doctype html><html><body style="margin:0;background:#f6f8fc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="background:${BRAND};padding:20px 28px;color:#fff;font-size:20px;font-weight:bold">${e(SITE_NAME)}</td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 16px;font-size:22px">${e(heading)}</h1>
${body}
</td></tr>
<tr><td style="padding:16px 28px;background:#f1f5f9;color:#64748b;font-size:12px">You're receiving this because you have an account with ${e(SITE_NAME)}.</td></tr>
</table></td></tr></table></body></html>`;
}

function button(href: string, label: string) {
  return `<p style="margin:24px 0"><a href="${e(href)}" style="display:inline-block;padding:12px 20px;background:${BRAND};color:#fff;border-radius:10px;text-decoration:none;font-weight:bold">${e(label)}</a></p>`;
}

function p(text: string) {
  return `<p style="margin:0 0 12px;line-height:1.5">${text}</p>`;
}

function rows(items: [string, string][]) {
  return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-top:1px solid #e2e8f0">${items
    .map(
      ([k, v]) =>
        `<tr><td style="padding:8px 0;color:#64748b;border-bottom:1px solid #e2e8f0">${e(k)}</td><td align="right" style="padding:8px 0;font-weight:bold;border-bottom:1px solid #e2e8f0">${e(v)}</td></tr>`,
    )
    .join("")}</table>`;
}

function tripRows(b: Booking): [string, string][] {
  const dates = b.quote.endDate
    ? `${formatDate(b.quote.startDate)} – ${formatDate(b.quote.endDate)}`
    : formatDate(b.quote.startDate);
  return [
    ["Reference", b.reference],
    ["Trip", b.quote.title],
    ["Dates", dates],
    ["Travelers", b.travelers.map((t) => `${t.firstName} ${t.lastName}`).join(", ")],
    ["Total", formatPrice(b.total)],
  ];
}

function textRows(items: [string, string][]) {
  return items.map(([k, v]) => `${k}: ${v}`).join("\n");
}

const signoff = `— The ${SITE_NAME} team`;

export function passwordResetEmail(firstName: string, link: string): Omit<Email, "to"> {
  return {
    subject: `Reset your ${SITE_NAME} password`,
    text: `Hi ${firstName},\n\nWe received a request to reset your password. Open this link to choose a new one (it expires in 1 hour):\n\n${link}\n\nIf you didn't ask for this, you can ignore this email — your password won't change.\n\n${signoff}`,
    html: layout(
      "Reset your password",
      p(`Hi ${e(firstName)},`) +
        p("We received a request to reset your password. This link expires in 1 hour.") +
        button(link, "Choose a new password") +
        p("If you didn't ask for this, you can ignore this email — your password won't change."),
    ),
  };
}

export function bookingReservedEmail(firstName: string, b: Booking, link: string, canPayOnline: boolean): Omit<Email, "to"> {
  const next = canPayOnline
    ? "You can pay securely online from your trip page, or a travel assistant will contact you within 24 hours."
    : "A travel assistant will contact you within 24 hours to confirm availability and arrange payment.";
  const items = tripRows(b);
  return {
    subject: `Trip reserved: ${b.quote.title} (${b.reference})`,
    text: `Hi ${firstName},\n\nYour trip is reserved. ${next}\n\n${textRows(items)}\n\nView your trip: ${link}\n\n${signoff}`,
    html: layout(
      "Your trip is reserved!",
      p(`Hi ${e(firstName)},`) + p(e(next)) + rows(items) + button(link, "View my trip"),
    ),
  };
}

export function bookingPaidEmail(firstName: string, b: Booking, link: string): Omit<Email, "to"> {
  const items = tripRows(b);
  return {
    subject: `Payment received: ${b.quote.title} (${b.reference})`,
    text: `Hi ${firstName},\n\nThanks — we've received your payment of ${formatPrice(b.total)}. Your trip is confirmed.\n\n${textRows(items)}\n\nView your trip: ${link}\n\n${signoff}`,
    html: layout(
      "Payment received — you're all set",
      p(`Hi ${e(firstName)},`) +
        p(`Thanks — we've received your payment of <strong>${e(formatPrice(b.total))}</strong>. Your trip is confirmed.`) +
        rows(items) +
        button(link, "View my trip"),
    ),
  };
}

export function bookingCancelledEmail(firstName: string, b: Booking, link: string): Omit<Email, "to"> {
  const items = tripRows(b);
  const money = b.paidAt
    ? "Our team will contact you about your refund, which depends on the airline and hotel rules."
    : "You haven't been charged.";
  return {
    subject: `Reservation cancelled: ${b.quote.title} (${b.reference})`,
    text: `Hi ${firstName},\n\nYour reservation has been cancelled. ${money}\n\n${textRows(items)}\n\nDetails: ${link}\n\n${signoff}`,
    html: layout(
      "Reservation cancelled",
      p(`Hi ${e(firstName)},`) + p(`Your reservation has been cancelled. ${e(money)}`) + rows(items) + button(link, "View details"),
    ),
  };
}

export function passwordChangedEmail(firstName: string, resetLink: string): Omit<Email, "to"> {
  return {
    subject: `Your ${SITE_NAME} password was changed`,
    text: `Hi ${firstName},\n\nYour password was just changed and other devices were signed out.\n\nIf this wasn't you, reset your password now: ${resetLink}\n\n${signoff}`,
    html: layout(
      "Your password was changed",
      p(`Hi ${e(firstName)},`) +
        p("Your password was just changed and you were signed out on other devices.") +
        p("If this wasn't you, reset your password right away:") +
        button(resetLink, "Reset password"),
    ),
  };
}

export function emailChangedEmail(firstName: string, newEmail: string, resetLink: string): Omit<Email, "to"> {
  return {
    subject: `Your ${SITE_NAME} email address was changed`,
    text: `Hi ${firstName},\n\nThe email address on your account was changed to ${newEmail}. Future emails will go there.\n\nIf this wasn't you, reset your password now: ${resetLink} and contact our support team.\n\n${signoff}`,
    html: layout(
      "Your email address was changed",
      p(`Hi ${e(firstName)},`) +
        p(`The email address on your account was changed to <strong>${e(newEmail)}</strong>. Future emails will go there.`) +
        p("If this wasn't you, reset your password right away and contact our support team.") +
        button(resetLink, "Reset password"),
    ),
  };
}
