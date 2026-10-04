import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cancelBookingAction } from "@/app/actions/bookings";
import { CheckIcon } from "@/components/icons";
import { TripSummary } from "@/components/TripSummary";
import { formatDate } from "@/lib/format";
import { getBooking } from "@/lib/server/bookings";
import { requireUser } from "@/lib/server/dal";
import { CancelButton } from "./CancelButton";

export async function generateMetadata({ params }: PageProps<"/account/trips/[ref]">): Promise<Metadata> {
  return { title: `Trip ${(await params).ref}` };
}

export default async function TripPage({ params, searchParams }: PageProps<"/account/trips/[ref]">) {
  const { ref } = await params;
  const user = await requireUser(`/account/trips/${ref}`);
  const booking = getBooking(user.id, ref);
  if (!booking) notFound();
  const isNew = (await searchParams).new === "1" && booking.status === "reserved";
  const cancelled = booking.status === "cancelled";

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
      <nav className="mb-4 text-sm text-slate-500">
        <Link href="/account" className="hover:text-brand-700">
          ← My trips
        </Link>
      </nav>

      {isNew && (
        <div className="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">
            <CheckIcon width={20} height={20} />
          </span>
          <div>
            <p className="text-lg font-semibold text-emerald-900">Your trip is reserved!</p>
            <p className="mt-1 text-sm text-emerald-800">
              A travel assistant will contact you at {booking.contactEmail} within 24 hours to
              confirm availability and arrange payment. Keep your reference handy: <strong>{booking.reference}</strong>
            </p>
          </div>
        </div>
      )}

      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-3xl font-bold text-slate-900">Trip {booking.reference}</h1>
        <span
          className={`rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${
            cancelled ? "bg-slate-200 text-slate-600" : "bg-amber-100 text-amber-800"
          }`}
        >
          {cancelled ? "Cancelled" : "Reserved · awaiting payment"}
        </span>
      </div>
      <p className="mt-1 text-sm text-slate-500">
        Booked {new Date(booking.createdAt).toLocaleDateString("en-US", { dateStyle: "medium" })}
        {booking.cancelledAt &&
          ` · Cancelled ${new Date(booking.cancelledAt).toLocaleDateString("en-US", { dateStyle: "medium" })}`}
      </p>

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          <section className="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 className="text-lg font-semibold text-slate-900">Travelers</h2>
            <ul className="mt-4 divide-y divide-slate-100">
              {booking.travelers.map((t, i) => (
                <li key={i} className="flex items-center justify-between py-3 text-sm">
                  <span className="font-medium text-slate-900">
                    {t.firstName} {t.lastName}
                  </span>
                  <span className="text-slate-500">
                    {booking.quote.travelerSlots[i]?.label}
                    {t.dob ? ` · born ${formatDate(t.dob)}` : ""}
                  </span>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 className="text-lg font-semibold text-slate-900">Contact</h2>
            <dl className="mt-4 space-y-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-slate-500">Email</dt>
                <dd className="font-medium text-slate-900">{booking.contactEmail}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-slate-500">Phone</dt>
                <dd className="font-medium text-slate-900">{booking.contactPhone}</dd>
              </div>
            </dl>
          </section>

          {!cancelled && (
            <section className="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 className="text-lg font-semibold text-slate-900">Need to change plans?</h2>
              <p className="mt-1 text-sm text-slate-600">
                Reservations can be cancelled free of charge until payment is made.
              </p>
              <form action={cancelBookingAction} className="mt-4 sm:w-64">
                <input type="hidden" name="reference" value={booking.reference} />
                <CancelButton />
              </form>
            </section>
          )}
        </div>
        <aside className="h-fit lg:sticky lg:top-20">
          <TripSummary quote={booking.quote} />
        </aside>
      </div>
    </div>
  );
}
