import "server-only";
import { safeRedirectPath } from "./dal";

/** Validated ?next= path from search params, or undefined. */
export function readNextParam(value: string | string[] | undefined) {
  const raw = Array.isArray(value) ? value[0] : value;
  const safe = safeRedirectPath(raw, "");
  return safe || undefined;
}

/** Friendly explanation of why the visitor was sent to sign in. */
export function noticeFor(next: string | undefined) {
  if (!next) return undefined;
  if (next.startsWith("/flights")) return "Sign in or create a free account to book this flight.";
  if (next.startsWith("/packages")) return "Sign in or create a free account to book this package.";
  if (next.startsWith("/book")) return "Sign in or create a free account to complete your booking.";
  if (next.startsWith("/account")) return "Please sign in to view your account.";
  return undefined;
}
