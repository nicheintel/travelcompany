import "server-only";
import { headers } from "next/headers";
import { SITE_NAME } from "../site";

type Email = { to: string; subject: string; text: string; html: string };

/**
 * Sends email through Resend (https://resend.com) when RESEND_API_KEY is set.
 * Without a key, development prints the email to the server log instead.
 */
export async function sendEmail(email: Email) {
  const apiKey = process.env.RESEND_API_KEY;
  if (!apiKey) {
    if (process.env.NODE_ENV === "production") {
      console.error(`[email] RESEND_API_KEY is not set — could not send "${email.subject}".`);
      return;
    }
    console.info(`\n[email] To: ${email.to}\n[email] Subject: ${email.subject}\n${email.text}\n`);
    return;
  }

  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      from: process.env.EMAIL_FROM ?? `${SITE_NAME} <onboarding@resend.dev>`,
      to: email.to,
      subject: email.subject,
      text: email.text,
      html: email.html,
    }),
  });
  if (!res.ok) console.error(`[email] Resend error ${res.status}: ${await res.text()}`);
}

/**
 * Absolute site URL for links in emails. Production must set APP_URL: building links
 * from the request's Host header would let an attacker point reset links at their own site.
 */
export async function appUrl() {
  const configured = process.env.APP_URL;
  if (configured) return configured.replace(/\/$/, "");
  if (process.env.NODE_ENV === "production") {
    throw new Error("APP_URL must be set in production (e.g. https://www.example.com).");
  }
  const h = await headers();
  return `${h.get("x-forwarded-proto") ?? "http"}://${h.get("host") ?? "localhost:3000"}`;
}

export function escapeHtml(s: string) {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}
