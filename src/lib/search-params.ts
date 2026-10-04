import { addDays } from "./format";

export type RawParams = Record<string, string | string[] | undefined>;

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export function str(v: string | string[] | undefined) {
  return Array.isArray(v) ? v[0] : v;
}

export function int(v: string | undefined, fallback: number, min: number, max: number) {
  const n = Number.parseInt(v ?? "", 10);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : fallback;
}

/** Today in UTC (YYYY-MM-DD). Visitors ahead of UTC may be a day later — callers allow for that. */
export function serverToday() {
  return new Date().toISOString().slice(0, 10);
}

/** A valid YYYY-MM-DD that is not before `min`, otherwise undefined. */
export function dateParam(v: string | undefined, min: string) {
  if (!v || !DATE_RE.test(v)) return undefined;
  const [y, m, d] = v.split("-").map(Number);
  const date = new Date(Date.UTC(y, m - 1, d));
  if (date.toISOString().slice(0, 10) !== v) return undefined; // e.g. 2026-02-31
  return v >= min ? v : undefined;
}

/** Earliest date we accept: yesterday in UTC, so visitors in any timezone can pick "today". */
export function earliestDate() {
  return addDays(serverToday(), -1);
}
