import type { Metadata } from "next";
import Link from "next/link";
import { BedIcon } from "@/components/icons";
import { HotelSearchForm } from "@/components/search/HotelSearchForm";
import { findAirport } from "@/lib/airports";
import { formatDate } from "@/lib/format";
import { ResultsNotice } from "@/components/ResultsNotice";
import { parseHotelParams } from "@/lib/hotels";
import { searchHotels } from "@/lib/server/travel-search";
import { HotelResults } from "./HotelResults";

export const metadata: Metadata = {
  title: "Hotels",
  description: "Compare hotels by price, rating and amenities and reserve with free cancellation on many stays.",
};

const POPULAR_CITIES = ["CDG", "NRT", "DPS", "BKK", "LHR", "CUN", "DXB", "CEB"];

export default async function HotelsPage({ searchParams }: PageProps<"/hotels">) {
  const { city, checkIn, checkOut, adults, children, rooms, search, query } = parseHotelParams(
    await searchParams,
  );
  const result = search ? await searchHotels(search) : null;
  const hotels = result?.items ?? [];

  return (
    <>
      <section className="bg-gradient-to-br from-teal-700 via-brand-800 to-brand-900 pb-8 pt-8">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          <div className="mb-5 text-white">
            {search ? (
              <>
                <h1 className="text-2xl font-bold sm:text-3xl">Hotels in {search.city.city}</h1>
                <p className="mt-1 text-brand-100">
                  {formatDate(checkIn)} – {formatDate(checkOut)} · {search.nights} night
                  {search.nights === 1 ? "" : "s"} · {adults + children} guest{adults + children === 1 ? "" : "s"} ·{" "}
                  {rooms} room{rooms === 1 ? "" : "s"}
                </p>
              </>
            ) : (
              <>
                <h1 className="text-3xl font-bold sm:text-4xl">Find your perfect stay</h1>
                <p className="mt-1 text-brand-100">
                  Hand-picked hotels, resorts and apartments — many with free cancellation.
                </p>
              </>
            )}
          </div>
          <div className="rounded-2xl bg-white p-4 shadow-xl sm:p-6">
            <HotelSearchForm
              key={query}
              defaults={{
                to: city?.code,
                checkin: search ? checkIn : undefined,
                checkout: search ? checkOut : undefined,
                adults,
                children,
                rooms,
              }}
            />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        {result && <ResultsNotice live={result.live} error={result.error} empty={!result.error && !hotels.length} what="hotels" />}
        {search ? (
          hotels.length > 0 && <HotelResults key={query} hotels={hotels} nights={search.nights} rooms={rooms} bookQuery={query} />
        ) : (
          <div>
            <h2 className="text-xl font-bold text-slate-900">Popular cities</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              {POPULAR_CITIES.map((code) => {
                const c = findAirport(code)!;
                return (
                  <Link
                    key={code}
                    href={`/hotels?to=${code}`}
                    className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300 hover:shadow-md"
                  >
                    <span className="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-600">
                      <BedIcon width={18} height={18} />
                    </span>
                    <span>
                      <span className="block font-medium text-slate-900">{c.city}</span>
                      <span className="block text-xs text-slate-500">{c.country}</span>
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
