import Link from "next/link";
import { formatDate, formatPrice } from "@/lib/format";
import type { Booking } from "@/lib/server/bookings";
import { BedIcon, PackageIcon, PlaneIcon } from "./icons";

const ICON = { flight: PlaneIcon, package: PackageIcon, hotel: BedIcon } as const;

export function TripCard({ booking }: { booking: Booking }) {
  const Icon = ICON[booking.kind];
  const { quote } = booking;
  const cancelled = booking.status === "cancelled";
  return (
    <Link
      href={`/account/trips/${booking.reference}`}
      className={`flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 hover:shadow-md ${
        cancelled ? "opacity-60" : ""
      }`}
    >
      <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
        <Icon width={22} height={22} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate font-semibold text-slate-900">{quote.title}</p>
        <p className="truncate text-sm text-slate-500">
          {formatDate(quote.startDate)}
          {quote.endDate ? ` – ${formatDate(quote.endDate)}` : ""} · {booking.reference}
        </p>
      </div>
      <div className="text-right">
        <p className="font-bold text-slate-900">{formatPrice(booking.total)}</p>
        <p className={`text-xs font-semibold ${cancelled ? "text-slate-500" : "text-amber-700"}`}>
          {cancelled ? "Cancelled" : "Reserved"}
        </p>
      </div>
    </Link>
  );
}
