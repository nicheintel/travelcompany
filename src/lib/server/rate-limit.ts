import "server-only";

/**
 * Minimal in-memory fixed-window limiter. Resets on restart and isn't shared
 * between server instances — replace with Redis or a DB table in production.
 */
export function createLimiter({ max, windowMs }: { max: number; windowMs: number }) {
  const hits = new Map<string, { count: number; resetAt: number }>();

  return {
    isLimited(key: string) {
      const entry = hits.get(key);
      if (!entry) return false;
      if (entry.resetAt < Date.now()) {
        hits.delete(key);
        return false;
      }
      return entry.count >= max;
    },
    hit(key: string) {
      const now = Date.now();
      const entry = hits.get(key);
      if (!entry || entry.resetAt < now) hits.set(key, { count: 1, resetAt: now + windowMs });
      else entry.count++;
    },
    clear(key: string) {
      hits.delete(key);
    },
  };
}

/** 5 failed sign-ins per email locks it for 15 minutes. */
export const signInFailures = createLimiter({ max: 5, windowMs: 15 * 60 * 1000 });

/** At most 3 reset emails per address per hour. */
export const resetRequests = createLimiter({ max: 3, windowMs: 60 * 60 * 1000 });
