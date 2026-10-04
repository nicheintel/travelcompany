import Link from "next/link";
import { formatDate, formatPrice } from "@/lib/format";
import type { AdminBooking } from "@/lib/server/bookings";
import { LocalTime } from "./LocalTime";
import { StatusBadge } from "./StatusBadge";

const KIND = { flight: "Flight", hotel: "Hotel", package: "Package" } as const;

export function BookingsTable({ bookings, empty = "No bookings found." }: { bookings: AdminBooking[]; empty?: string }) {
  if (!bookings.length) {
    return <p className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">{empty}</p>;
  }
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table className="w-full min-w-[820px] text-left text-sm">
        <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th className="px-4 py-3 font-semibold">Reference</th>
            <th className="px-4 py-3 font-semibold">Customer</th>
            <th className="px-4 py-3 font-semibold">Trip</th>
            <th className="px-4 py-3 font-semibold">Travel dates</th>
            <th className="px-4 py-3 text-right font-semibold">Total</th>
            <th className="px-4 py-3 font-semibold">Status</th>
            <th className="px-4 py-3 font-semibold">Booked</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {bookings.map((b) => (
            <tr key={b.reference} className="hover:bg-brand-50/50">
              <td className="px-4 py-3">
                <Link href={`/admin/bookings/${b.reference}`} className="font-mono font-semibold text-brand-700 hover:underline">
                  {b.reference}
                </Link>
              </td>
              <td className="px-4 py-3">
                <p className="font-medium text-slate-900">{b.customer.name}</p>
                <p className="text-xs text-slate-500">{b.contactPhone}</p>
              </td>
              <td className="max-w-64 px-4 py-3">
                <p className="truncate font-medium text-slate-900">{b.quote.title}</p>
                <p className="text-xs text-slate-500">
                  {KIND[b.kind]} · {b.travelers.length} traveler{b.travelers.length === 1 ? "" : "s"}
                </p>
              </td>
              <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                {formatDate(b.quote.startDate)}
                {b.quote.endDate ? ` – ${formatDate(b.quote.endDate)}` : ""}
              </td>
              <td className="px-4 py-3 text-right font-semibold text-slate-900">{formatPrice(b.total)}</td>
              <td className="px-4 py-3">
                <StatusBadge status={b.status} />
              </td>
              <td className="whitespace-nowrap px-4 py-3 text-slate-500">
                <LocalTime ms={b.createdAt} dateOnly />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
