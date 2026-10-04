import type { Metadata } from "next";
import Link from "next/link";
import { PlaneIcon } from "@/components/icons";
import { FlightSearchForm } from "@/components/search/FlightSearchForm";
import { findAirport } from "@/lib/airports";
import { CABIN_LABELS, parseFlightParams, searchFlights } from "@/lib/flights";
import { formatDate } from "@/lib/format";
import { FlightResults } from "./FlightResults";

export const metadata: Metadata = {
  title: "Cheap flights",
  description: "Compare airlines and find affordable flights to anywhere.",
};

const POPULAR_ROUTES = [
  ["JFK", "LHR"],
  ["LAX", "NRT"],
  ["JFK", "CUN"],
  ["SFO", "MNL"],
  ["MIA", "BCN"],
  ["ORD", "DXB"],
];

export default async function FlightsPage({ searchParams }: PageProps<"/flights">) {
  const { from, to, depart, returnDate, trip, adults, children, cabin, search, query } =
    parseFlightParams(await searchParams);
  const canSearch = !!search;
  const offers = search ? searchFlights(search) : [];

  return (
    <>
      <section className="bg-gradient-to-br from-brand-900 to-brand-700 pb-8 pt-8">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          {search ? (
            <div className="mb-5 text-white">
              <h1 className="flex flex-wrap items-center gap-3 text-2xl font-bold sm:text-3xl">
                {search.from.city} <PlaneIcon className="rotate-45 text-accent-400" /> {search.to.city}
              </h1>
              <p className="mt-1 text-brand-100">
                {formatDate(depart)}
                {returnDate ? ` – ${formatDate(returnDate)}` : " · One way"} · {adults + children}{" "}
                traveler{adults + children === 1 ? "" : "s"} · {CABIN_LABELS[cabin]}
              </p>
            </div>
          ) : (
            <div className="mb-5 text-white">
              <h1 className="text-3xl font-bold sm:text-4xl">Find cheap flights</h1>
              <p className="mt-1 text-brand-100">
                Choose your departure, destination and dates — we&apos;ll compare airlines for you.
              </p>
            </div>
          )}
          <div className="rounded-2xl bg-white p-4 shadow-xl sm:p-6">
            <FlightSearchForm
              key={query}
              defaults={{
                from: from?.code,
                to: to?.code,
                depart: canSearch ? depart : undefined,
                return: canSearch ? returnDate : undefined,
                trip,
                adults,
                children,
                cabin,
              }}
            />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        {canSearch ? (
          <FlightResults
            key={query}
            offers={offers}
            travellers={adults + children}
            bookQuery={query}
          />
        ) : (
          <div>
            {from && to && from.code === to.code && (
              <p className="mb-6 rounded-xl bg-red-50 p-4 text-sm font-medium text-red-700">
                Origin and destination must be different.
              </p>
            )}
            <h2 className="text-xl font-bold text-slate-900">Popular routes</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {POPULAR_ROUTES.map(([a, b]) => {
                const fa = findAirport(a)!;
                const fb = findAirport(b)!;
                return (
                  <Link
                    key={`${a}-${b}`}
                    href={`/flights?from=${a}&to=${b}`}
                    className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300 hover:shadow-md"
                  >
                    <span className="font-medium text-slate-900">
                      {fa.city} → {fb.city}
                    </span>
                    <span className="font-mono text-xs text-slate-500">
                      {a}–{b}
                    </span>
                  </Link>
                );
              })}
            </div>
          </div>
        )}
      </section>
    </>
  );
}
