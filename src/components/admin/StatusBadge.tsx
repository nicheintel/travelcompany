import type { BookingStatus } from "@/lib/server/bookings";

const STYLES: Record<BookingStatus, string> = {
  reserved: "bg-amber-100 text-amber-800",
  paid: "bg-emerald-100 text-emerald-800",
  cancelled: "bg-slate-200 text-slate-600",
};
const LABELS: Record<BookingStatus, string> = { reserved: "Unpaid", paid: "Paid", cancelled: "Cancelled" };

export function StatusBadge({ status }: { status: BookingStatus }) {
  return (
    <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-bold ${STYLES[status]}`}>
      {LABELS[status]}
    </span>
  );
}
