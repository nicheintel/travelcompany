import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { LocalTime } from "@/components/admin/LocalTime";
import { StatusBadge } from "@/components/admin/StatusBadge";
import { TripSummary } from "@/components/TripSummary";
import { formatDob, formatPrice } from "@/lib/format";
import { type AdminBooking, adminGetBooking, listEvents } from "@/lib/server/bookings";
import { requireAdmin } from "@/lib/server/dal";
import { CancelForm, MarkPaidForm, NoteForm } from "./BookingActions";

export async function generateMetadata({ params }: PageProps<"/admin/bookings/[ref]">): Promise<Metadata> {
  return { title: (await params).ref };
}

const EVENT_DOT = {
  created: "bg-brand-500",
  paid: "bg-emerald-500",
  cancelled: "bg-red-500",
  note: "bg-slate-400",
} as const;

export default async function AdminBookingPage({ params, searchParams }: PageProps<"/admin/bookings/[ref]">) {
  const { ref } = await params;
  await requireAdmin(`/admin/bookings/${ref}`);
  const booking = adminGetBooking(ref);
  if (!booking) notFound();
  const events = listEvents(ref);
  const done = (await searchParams).done;
  const banner =
    done === "paid" && booking.status === "paid"
      ? "Marked as paid. The customer has been emailed a receipt."
      : done === "cancelled" && booking.status === "cancelled"
        ? "Booking cancelled. The customer has been emailed."
        : null;

  return (
    <div className="space-y-6">
      <nav className="text-sm text-slate-500">
        <Link href="/admin/bookings" className="hover:text-brand-700">
          ← All bookings
        </Link>
      </nav>
      {banner && (
        <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
          {banner}
        </p>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-mono text-2xl font-bold text-slate-900">{booking.reference}</h1>
        <StatusBadge status={booking.status} />
        <span className="text-sm text-slate-500">
          Booked <LocalTime ms={booking.createdAt} />
          {booking.paidAt && (
            <>
              {" "}· Paid <LocalTime ms={booking.paidAt} /> ({booking.paymentMethod})
            </>
          )}
          {booking.cancelledAt && (
            <>
              {" "}· Cancelled <LocalTime ms={booking.cancelledAt} />
            </>
          )}
        </span>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          <section className="grid gap-6 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Contact for this trip</h2>
              <p className="mt-2">
                <a href={`tel:${booking.contactPhone.replace(/[^\d+]/g, "")}`} className="text-lg font-semibold text-brand-700 hover:underline">
                  {booking.contactPhone}
                </a>
              </p>
              <p>
                <a href={`mailto:${booking.contactEmail}?subject=${encodeURIComponent(`Your trip ${booking.reference}`)}`} className="text-brand-700 hover:underline">
                  {booking.contactEmail}
                </a>
              </p>
            </div>
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Account</h2>
              <p className="mt-2 font-medium text-slate-900">{booking.customer.name}</p>
              <p className="text-sm text-slate-600">{booking.customer.email}</p>
              <Link href={`/admin/bookings?user=${booking.customer.id}`} className="text-sm font-semibold text-brand-700 hover:underline">
                All bookings by this customer →
              </Link>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Travelers</h2>
            <table className="mt-3 w-full text-sm">
              <tbody className="divide-y divide-slate-100">
                {booking.travelers.map((t, i) => (
                  <tr key={i}>
                    <td className="py-2 text-slate-500">{booking.quote.travelerSlots[i]?.label}</td>
                    <td className="py-2 font-medium text-slate-900">
                      {t.lastName.toUpperCase()}, {t.firstName}
                    </td>
                    <td className="py-2 text-right text-slate-600">{t.dob ? `DOB ${formatDob(t.dob)}` : ""}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Activity</h2>
            <div className="mt-3">
              <NoteForm reference={booking.reference} />
            </div>
            <ol className="mt-5 space-y-4">
              {events.map((e) => (
                <li key={e.id} className="flex gap-3">
                  <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${EVENT_DOT[e.type]}`} />
                  <div className="text-sm">
                    <p className="text-slate-900">{e.message}</p>
                    <p className="text-xs text-slate-500">
                      {e.actorName ?? "System"} · <LocalTime ms={e.createdAt} />
                    </p>
                  </div>
                </li>
              ))}
              {!events.length && <li className="text-sm text-slate-500">No activity yet.</li>}
            </ol>
          </section>
        </div>

        <aside className="space-y-6">
          {booking.status === "reserved" && (
            <section className="rounded-xl border-2 border-emerald-200 bg-white p-5">
              <h2 className="font-semibold text-slate-900">Record a payment</h2>
              <p className="mb-3 mt-1 text-sm text-slate-500">
                For payments taken outside the website. The customer gets a receipt email.
              </p>
              <MarkPaidForm reference={booking.reference} total={formatPrice(booking.total)} />
            </section>
          )}
          {booking.status !== "cancelled" && (
            <section className="rounded-xl border border-slate-200 bg-white p-5">
              <h2 className="mb-3 font-semibold text-slate-900">Cancel booking</h2>
              <CancelForm reference={booking.reference} wasPaid={booking.status === "paid"} />
            </section>
          )}
          {booking.quote.supplier && <SupplierPanel booking={booking} />}
          <TripSummary quote={booking.quote} />
        </aside>
      </div>
    </div>
  );
}

const PROVIDER_NAME = { duffel: "Duffel (flights)", liteapi: "LiteAPI (hotels)" } as const;

/** Staff-only: what we owe the supplier and what we keep. */
function SupplierPanel({ booking }: { booking: AdminBooking }) {
  const s = booking.quote.supplier!;
  const margin = booking.total - s.netUsd;
  const net =
    s.netCurrency === "USD"
      ? formatPrice(s.netAmount)
      : `${s.netAmount.toFixed(2)} ${s.netCurrency} (≈${formatPrice(s.netUsd)})`;
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 text-sm">
      <h2 className="font-semibold text-slate-900">Supplier & margin</h2>
      <dl className="mt-3 space-y-2">
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">Supplier</dt>
          <dd className="font-medium text-slate-900">{PROVIDER_NAME[s.provider]}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">Offer ID</dt>
          <dd className="truncate font-mono text-xs text-slate-700" title={s.offerId}>{s.offerId}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">Cost when booked</dt>
          <dd className="font-medium text-slate-900">{net}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">Customer pays</dt>
          <dd className="font-medium text-slate-900">{formatPrice(booking.total)}</dd>
        </div>
        <div className="flex justify-between gap-3 border-t border-slate-100 pt-2">
          <dt className="font-semibold text-slate-700">Gross margin</dt>
          <dd className={`font-bold ${margin >= 0 ? "text-emerald-700" : "text-red-600"}`}>
            {formatPrice(margin)} ({Math.round((margin / booking.total) * 100)}%)
          </dd>
        </div>
      </dl>
      <p className="mt-3 text-xs text-slate-500">
        Before supplier fees and card fees. Issue the ticket or room in the {s.provider === "duffel" ? "Duffel" : "LiteAPI"}{" "}
        dashboard after payment — supplier prices can change until then.
      </p>
    </section>
  );
}
