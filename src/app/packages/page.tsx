import type { Metadata } from "next";
import { BedIcon, CarIcon, CheckIcon, PlaneIcon } from "@/components/icons";
import { PackageCard } from "@/components/PackageCard";
import { PackageSearchForm } from "@/components/search/PackageSearchForm";
import { findAirport } from "@/lib/airports";
import { formatDate, formatPrice } from "@/lib/format";
import { HOW_BUNDLES_SAVE, PROMO_PACKAGES } from "@/lib/packages";
import { PackageGrid } from "./PackageGrid";

export const metadata: Metadata = {
  title: "Promo packages — Flight + Hotel + Car",
  description: "Bundle your flight, hotel and rental car and save up to 40% on your next trip.",
};

function str(v: string | string[] | undefined) {
  return Array.isArray(v) ? v[0] : v;
}

export default async function PackagesPage({ searchParams }: PageProps<"/packages">) {
  const params = await searchParams;
  const to = findAirport(str(params.to));
  const from = findAirport(str(params.from));
  const depart = str(params.depart);
  const ret = str(params.return);
  const withCar = str(params.car) === "1";

  // Show every package for the destination; when a car was requested, list those with one first.
  const matches = to
    ? PROMO_PACKAGES.filter((p) => p.destinationCode === to.code).sort(
        (a, b) => (withCar ? Number(b.includesCar) - Number(a.includesCar) : 0),
      )
    : [];

  const separateTotal = HOW_BUNDLES_SAVE.reduce((sum, i) => sum + i.price, 0);
  const bundlePrice = Math.round(separateTotal * 0.68);

  return (
    <>
      <section className="relative overflow-hidden bg-gradient-to-br from-accent-600 via-rose-500 to-brand-700">
        <svg
          className="pointer-events-none absolute inset-0 h-full w-full opacity-15"
          viewBox="0 0 1200 500"
          preserveAspectRatio="xMidYMid slice"
          aria-hidden
        >
          <circle cx="1050" cy="90" r="120" fill="white" />
          <path d="M0 420 Q300 340 600 400 T1200 380 V500 H0Z" fill="white" />
        </svg>
        <div className="relative mx-auto max-w-7xl px-4 pb-14 pt-12 sm:px-6 sm:pt-16">
          <div className="max-w-2xl text-white">
            <p className="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-semibold ring-1 ring-white/25">
              Limited-time promo
            </p>
            <h1 className="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
              Flight + Hotel + Car.
              <br />
              <span className="text-yellow-200">One booking. Bigger savings.</span>
            </h1>
            <p className="mt-4 text-lg text-white/90">
              Our travel assistants bundle airfare, hand-picked hotels and rental cars into packages
              that cost less than booking each one separately.
            </p>
          </div>
          <div className="mt-8 rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
            <PackageSearchForm
              defaults={{
                from: from?.code,
                to: to?.code,
                depart,
                return: ret,
                car: params.to ? withCar : true,
              }}
            />
          </div>
        </div>
      </section>

      {to && (
        <section className="mx-auto max-w-7xl px-4 pt-12 sm:px-6">
          <div className="rounded-2xl border border-brand-200 bg-brand-50 p-6">
            <h2 className="text-xl font-bold text-slate-900">
              Packages to {to.city}
              {from ? ` from ${from.city}` : ""}
            </h2>
            {depart && ret && (
              <p className="mt-1 text-sm text-slate-600">
                {formatDate(depart)} – {formatDate(ret)}
                {withCar ? " · with rental car" : ""}
              </p>
            )}
            {matches.length > 0 ? (
              <div className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {matches.map((pkg) => (
                  <PackageCard key={pkg.id} pkg={pkg} />
                ))}
              </div>
            ) : (
              <p className="mt-4 text-slate-700">
                We don&apos;t have a ready-made promo to {to.city} right now — but our travel
                assistants can build a custom bundle for you. Browse the current promos below, or
                create an account and we&apos;ll alert you when a {to.city} deal drops.
              </p>
            )}
          </div>
        </section>
      )}

      <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <div className="mb-6">
          <h2 className="text-2xl font-bold text-slate-900 sm:text-3xl">All promo packages</h2>
          <p className="mt-1 text-slate-600">
            Prices are per person, based on two travelers sharing a room, including taxes.
          </p>
        </div>
        <PackageGrid packages={PROMO_PACKAGES} />
      </section>

      <section className="bg-white py-14">
        <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wider text-accent-600">Why bundle?</p>
            <h2 className="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
              Package rates you can&apos;t get separately
            </h2>
            <p className="mt-3 text-slate-600">
              Airlines, hotels and car companies give us special rates when they&apos;re sold
              together. That discount goes straight to you.
            </p>
            <ul className="mt-6 space-y-3 text-slate-700">
              {[
                "One itinerary, one payment, one confirmation",
                "Free airport-to-hotel planning help",
                "Change support from a real travel assistant",
              ].map((t) => (
                <li key={t} className="flex items-center gap-3">
                  <span className="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                    <CheckIcon width={14} height={14} />
                  </span>
                  {t}
                </li>
              ))}
            </ul>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <p className="text-sm font-semibold text-slate-500">Example: 5 nights in Cancún</p>
            <ul className="mt-4 space-y-3">
              {HOW_BUNDLES_SAVE.map(({ item, price }, i) => {
                const Icon = [PlaneIcon, BedIcon, CarIcon][i] ?? PlaneIcon;
                return (
                  <li key={item} className="flex items-center justify-between">
                    <span className="flex items-center gap-3 text-slate-700">
                      <Icon width={18} height={18} className="text-brand-600" />
                      {item}
                    </span>
                    <span className="text-slate-500">{formatPrice(price)}</span>
                  </li>
                );
              })}
            </ul>
            <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
              <span className="text-slate-600">Booked separately</span>
              <span className="font-semibold text-slate-500 line-through">{formatPrice(separateTotal)}</span>
            </div>
            <div className="mt-2 flex items-center justify-between rounded-xl bg-emerald-50 p-4">
              <span className="font-semibold text-emerald-800">Booked as a package</span>
              <span className="text-2xl font-extrabold text-emerald-700">{formatPrice(bundlePrice)}</span>
            </div>
            <p className="mt-2 text-right text-sm font-semibold text-emerald-700">
              You save {formatPrice(separateTotal - bundlePrice)}
            </p>
          </div>
        </div>
      </section>
    </>
  );
}
