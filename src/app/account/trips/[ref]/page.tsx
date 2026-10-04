import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cancelBookingAction, payBookingAction } from "@/app/actions/bookings";
import { CheckIcon } from "@/components/icons";
import { TripSummary } from "@/components/TripSummary";
import { formatDate } from "@/lib/format";
import { getBooking } from "@/lib/server/bookings";
import { applyPaidSession, paymentsEnabled, retrieveCheckoutSession } from "@/lib/server/payments";
import { formatPrice } from "@/lib/format";
import { requireUser } from "@/lib/server/dal";
import { CancelButton } from "./CancelButton";

export async function generateMetadata({ params }: PageProps<"/account/trips/[ref]">): Promise<Metadata> {
  return { title: `Trip ${(await params).ref}` };
}

export default async function TripPage({ params, searchParams }: PageProps<"/account/trips/[ref]">) {
  const { ref } = await params;
  const user = await requireUser(`/account/trips/${ref}`);
  let booking = getBooking(user.id, ref);
  if (!booking) notFound();
  const sp = await searchParams;

  // Back from Stripe Checkout: confirm the payment with Stripe directly (don't trust the URL),
  // in case the webhook hasn't arrived yet.
  const sessionId = typeof sp.session_id === "string" ? sp.session_id : undefined;
  if (sessionId && booking.status === "reserved" && paymentsEnabled()) {
    try {
      const session = await retrieveCheckoutSession(sessionId);
      if ((session.metadata?.reference ?? session.client_reference_id) === booking.reference) {
        await applyPaidSession(session);
        booking = getBooking(user.id, ref)!;
      }
    } catch (err) {
      console.error(`[payments] Could not verify session for ${ref}:`, err);
    }
  }

  const isNew = sp.new === "1" && booking.status === "reserved";
  const justPaid = !!sessionId && booking.status === "paid";
  const paymentError = sp.payment === "error";
  const cancelled = booking.status === "cancelled";
  const paid = booking.status === "paid";
  const canPay = booking.status === "reserved" && paymentsEnabled();

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
              {canPay
                ? "Pay securely below to confirm it now, or a travel assistant will contact you within 24 hours."
                : `A travel assistant will contact you at ${booking.contactEmail} within 24 hours to confirm availability and arrange payment.`}{" "}
              We&apos;ve emailed your confirmation to {booking.contactEmail}. Reference: <strong>{booking.reference}</strong>
            </p>
          </div>
        </div>
      )}

      {justPaid && (
        <div className="mb-6 flex items-start gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">
            <CheckIcon width={20} height={20} />
          </span>
          <div>
            <p className="text-lg font-semibold text-emerald-900">Payment received — you&apos;re all set!</p>
            <p className="mt-1 text-sm text-emerald-800">
              We&apos;ve emailed your receipt to {booking.contactEmail}.
            </p>
          </div>
        </div>
      )}
      {paymentError && booking.status === "reserved" && (
        <p className="mb-6 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 ring-1 ring-red-200">
          We couldn&apos;t start the payment just now. Please try again in a moment.
        </p>
      )}

      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-3xl font-bold text-slate-900">Trip {booking.reference}</h1>
        <span
          className={`rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${
            cancelled ? "bg-slate-200 text-slate-600" : paid ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"
          }`}
        >
          {cancelled ? "Cancelled" : paid ? "Paid · confirmed" : "Reserved · awaiting payment"}
        </span>
      </div>
      <p className="mt-1 text-sm text-slate-500">
        Booked {new Date(booking.createdAt).toLocaleDateString("en-US", { dateStyle: "medium" })}
        {booking.paidAt &&
          ` · Paid ${new Date(booking.paidAt).toLocaleDateString("en-US", { dateStyle: "medium" })}`}
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

          {canPay && (
            <section className="rounded-2xl border-2 border-brand-200 bg-white p-6">
              <h2 className="text-lg font-semibold text-slate-900">Pay now to confirm</h2>
              <p className="mt-1 text-sm text-slate-600">
                Pay {formatPrice(booking.total)} securely by card. You&apos;ll be taken to our payment
                partner Stripe and brought back here afterwards.
              </p>
              <form action={payBookingAction} className="mt-4 sm:w-64">
                <input type="hidden" name="reference" value={booking.reference} />
                <button
                  type="submit"
                  className="w-full rounded-xl bg-accent-500 py-3 font-bold text-white shadow-sm hover:bg-accent-600"
                >
                  Pay {formatPrice(booking.total)}
                </button>
              </form>
            </section>
          )}

          {paid && (
            <section className="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 className="text-lg font-semibold text-slate-900">Need to change plans?</h2>
              <p className="mt-1 text-sm text-slate-600">
                Your trip is paid. To change or cancel it, contact our travel assistants with your
                reference {booking.reference} — refunds depend on the fare and hotel rules.
              </p>
            </section>
          )}

          {booking.status === "reserved" && (
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
