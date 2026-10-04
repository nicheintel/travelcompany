import "server-only";

/**
 * Minimal in-memory limiter for failed sign-ins. Resets on restart and isn't
 * shared between server instances — replace with Redis or a DB table in production.
 */
const WINDOW_MS = 15 * 60 * 1000;
const MAX_FAILURES = 5;
const failures = new Map<string, { count: number; resetAt: number }>();

export function isLocked(key: string) {
  const entry = failures.get(key);
  if (!entry) return false;
  if (entry.resetAt < Date.now()) {
    failures.delete(key);
    return false;
  }
  return entry.count >= MAX_FAILURES;
}

export function recordFailure(key: string) {
  const now = Date.now();
  const entry = failures.get(key);
  if (!entry || entry.resetAt < now) failures.set(key, { count: 1, resetAt: now + WINDOW_MS });
  else entry.count++;
}

export function clearFailures(key: string) {
  failures.delete(key);
}
