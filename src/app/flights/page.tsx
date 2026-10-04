import type { Metadata } from "next";
import Link from "next/link";
import { PlaneIcon } from "@/components/icons";
import { FlightSearchForm } from "@/components/search/FlightSearchForm";
import { findAirport } from "@/lib/airports";
import { CABIN_LABELS, type CabinClass, searchFlights } from "@/lib/flights";
import { addDays, formatDate } from "@/lib/format";
import { FlightResults } from "./FlightResults";

export const metadata: Metadata = {
  title: "Cheap flights",
  description: "Compare airlines and find affordable flights to anywhere.",
};

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

function str(v: string | string[] | undefined) {
  return Array.isArray(v) ? v[0] : v;
}

function int(v: string | undefined, fallback: number, min: number, max: number) {
  const n = Number.parseInt(v ?? "", 10);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : fallback;
}

const POPULAR_ROUTES = [
  ["JFK", "LHR"],
  ["LAX", "NRT"],
  ["JFK", "CUN"],
  ["SFO", "MNL"],
  ["MIA", "BCN"],
  ["ORD", "DXB"],
];

export default async function FlightsPage({ searchParams }: PageProps<"/flights">) {
  const params = await searchParams;
  const from = findAirport(str(params.from));
  const to = findAirport(str(params.to));

  const today = new Date().toISOString().slice(0, 10);
  const departParam = str(params.depart);
  const depart = departParam && DATE_RE.test(departParam) && departParam >= today ? departParam : addDays(today, 14);
  const trip = str(params.trip) === "oneway" ? "oneway" : "roundtrip";
  const returnParam = str(params.return);
  const returnDate =
    trip === "oneway"
      ? undefined
      : returnParam && DATE_RE.test(returnParam) && returnParam >= depart
        ? returnParam
        : addDays(depart, 7);
  const adults = int(str(params.adults), 1, 1, 9);
  const children = int(str(params.children), 0, 0, 8);
  const cabinParam = str(params.cabin) as CabinClass | undefined;
  const cabin: CabinClass = cabinParam && cabinParam in CABIN_LABELS ? cabinParam : "economy";

  const canSearch = from && to && from.code !== to.code;
  const offers = canSearch
    ? searchFlights({ from, to, depart, returnDate, adults, children, cabin })
    : [];

  const query = new URLSearchParams({
    from: from?.code ?? "",
    to: to?.code ?? "",
    depart,
    ...(returnDate ? { return: returnDate } : {}),
    trip,
    adults: String(adults),
    children: String(children),
    cabin,
  }).toString();

  return (
    <>
      <section className="bg-gradient-to-br from-brand-900 to-brand-700 pb-8 pt-8">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          {canSearch ? (
            <div className="mb-5 text-white">
              <h1 className="flex flex-wrap items-center gap-3 text-2xl font-bold sm:text-3xl">
                {from.city} <PlaneIcon className="rotate-45 text-accent-400" /> {to.city}
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
            bookHref={`/signin?next=${encodeURIComponent(`/flights?${query}`)}`}
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
