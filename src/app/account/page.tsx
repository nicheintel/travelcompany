import type { Metadata } from "next";
import Link from "next/link";
import { signOut } from "@/app/actions/auth";
import { BedIcon, PackageIcon, PlaneIcon, UserIcon } from "@/components/icons";
import { TripCard } from "@/components/TripCard";
import { serverToday } from "@/lib/search-params";
import { listBookings } from "@/lib/server/bookings";
import { requireUser } from "@/lib/server/dal";

export const metadata: Metadata = { title: "My account" };

export default async function AccountPage() {
  const user = await requireUser("/account");
  const firstName = user.name.split(" ")[0];
  const bookings = listBookings(user.id);
  const today = serverToday();
  const upcoming = bookings.filter((b) => b.status === "reserved" && (b.quote.endDate ?? b.startDate) >= today);
  const other = bookings.filter((b) => !upcoming.includes(b)).reverse();
  const memberSince = new Date(user.createdAt).toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
      <div className="flex flex-col gap-4 rounded-3xl bg-gradient-to-br from-brand-800 to-brand-600 p-8 text-white sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-brand-100">My account</p>
          <h1 className="mt-1 text-3xl font-bold">Hi, {firstName}! 👋</h1>
          <p className="mt-1 text-brand-100">Where are you heading next?</p>
        </div>
        <span className="rounded-full bg-white/15 px-4 py-1.5 text-sm font-semibold ring-1 ring-white/25">
          Member since {memberSince}
        </span>
      </div>

      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
        <section className="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-lg font-semibold text-slate-900">My trips</h2>
          {bookings.length === 0 ? (
          <div className="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                  <PlaneIcon width={28} height={28} />
                </span>
                <p className="mt-4 font-semibold text-slate-900">No trips yet</p>
                <p className="mt-1 max-w-sm text-sm text-slate-600">
                  When you book a flight, hotel or package it will show up here.
                </p>
                <div className="mt-6 flex flex-wrap justify-center gap-3">
                  <Link href="/flights" className="flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <PlaneIcon width={16} height={16} /> Find flights
                  </Link>
                  <Link href="/packages" className="flex items-center gap-2 rounded-xl bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-600">
                    <PackageIcon width={16} height={16} /> Browse packages
                  </Link>
                  <Link href="/hotels" className="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">
                    <BedIcon width={16} height={16} /> Hotels
                  </Link>
                </div>
              </div>
          ) : (
            <div className="mt-4 space-y-6">
              <div>
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                  Upcoming ({upcoming.length})
                </h3>
                {upcoming.length ? (
                  <div className="space-y-3">
                    {upcoming.map((b) => (
                      <TripCard key={b.reference} booking={b} />
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-slate-500">
                    No upcoming trips.{" "}
                    <Link href="/packages" className="font-semibold text-brand-700 hover:underline">
                      Find your next getaway →
                    </Link>
                  </p>
                )}
              </div>
              {other.length > 0 && (
                <div>
                  <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Past & cancelled
                  </h3>
                  <div className="space-y-3">
                    {other.map((b) => (
                      <TripCard key={b.reference} booking={b} />
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </section>

        <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-6">
          <div className="flex items-center gap-3">
            <span className="grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-brand-700">
              <UserIcon width={22} height={22} />
            </span>
            <div className="min-w-0">
              <p className="truncate font-semibold text-slate-900">{user.name}</p>
              <p className="truncate text-sm text-slate-500">{user.email}</p>
            </div>
          </div>
          <dl className="mt-6 space-y-3 border-t border-slate-100 pt-4 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">Membership</dt>
              <dd className="font-medium text-slate-900">Free member</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Member since</dt>
              <dd className="font-medium text-slate-900">{memberSince}</dd>
            </div>
          </dl>
          <form action={signOut} className="mt-6">
            <button
              type="submit"
              className="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50"
            >
              Sign out
            </button>
          </form>
        </aside>
      </div>
    </div>
  );
}
