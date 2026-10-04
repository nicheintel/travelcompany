export function formatPrice(amount: number, currency = "USD") {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatDuration(minutes: number) {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${h}h ${m.toString().padStart(2, "0")}m`;
}

export function formatTime(minutesFromMidnight: number) {
  const total = ((minutesFromMidnight % 1440) + 1440) % 1440;
  const h = Math.floor(total / 60);
  const m = total % 60;
  const suffix = h >= 12 ? "PM" : "AM";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${m.toString().padStart(2, "0")} ${suffix}`;
}

/** Format a YYYY-MM-DD string without timezone drift. */
export function formatDate(iso: string) {
  const [y, mo, d] = iso.split("-").map(Number);
  if (!y || !mo || !d) return iso;
  return new Date(Date.UTC(y, mo - 1, d)).toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    timeZone: "UTC",
  });
}

/** Add days to a YYYY-MM-DD string. */
export function addDays(iso: string, days: number) {
  const [y, mo, d] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(y, mo - 1, d + days));
  return date.toISOString().slice(0, 10);
}
