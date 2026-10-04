import "server-only";
import { redirect } from "next/navigation";
import { cache } from "react";
import { readSession } from "./session";
import { findUserById, type User } from "./users";

/** The signed-in user for this request, or null. Deduplicated per request. */
export const getCurrentUser = cache(async (): Promise<User | null> => {
  const userId = await readSession();
  return userId ? findUserById(userId) : null;
});

/** Use in protected pages: redirects to sign-in (and back afterwards) when signed out. */
export async function requireUser(returnTo: string): Promise<User> {
  const user = await getCurrentUser();
  if (!user) redirect(`/signin?next=${encodeURIComponent(returnTo)}`);
  return user;
}

/**
 * Only allow redirects to paths on this site. Blocks "//evil.com" and
 * "/\evil.com", which browsers treat as other origins.
 */
export function safeRedirectPath(next: unknown, fallback = "/account") {
  if (typeof next !== "string" || !next.startsWith("/") || next.startsWith("//") || next.includes("\\")) {
    return fallback;
  }
  return next;
}
