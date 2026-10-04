"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { PlaneIcon } from "@/components/icons";
import { findAirport } from "@/lib/airports";
import type { FlightLeg, FlightOffer } from "@/lib/flights";
import { formatDate, formatDuration, formatPrice, formatTime } from "@/lib/format";

type Sort = "best" | "cheapest" | "fastest";

const STOP_OPTIONS = [
  { value: 0, label: "Nonstop" },
  { value: 1, label: "1 stop" },
  { value: 2, label: "2+ stops" },
];

const TIME_WINDOWS = [
  { id: "morning", label: "Morning", hint: "5am – 12pm", from: 300, to: 720 },
  { id: "afternoon", label: "Afternoon", hint: "12pm – 6pm", from: 720, to: 1080 },
  { id: "evening", label: "Evening", hint: "6pm – 11pm", from: 1080, to: 1440 },
];

function totalDuration(o: FlightOffer) {
  return o.outbound.durationMinutes + (o.inbound?.durationMinutes ?? 0);
}

/** Blend of price and duration, lower is better. */
function bestScore(o: FlightOffer, minPrice: number, minDuration: number) {
  return o.totalPrice / minPrice + (totalDuration(o) / minDuration) * 0.7;
}

export function FlightResults({
  offers,
  travellers,
  bookHref,
}: {
  offers: FlightOffer[];
  travellers: number;
  bookHref: string;
}) {
  const priceCeiling = Math.max(...offers.map((o) => o.totalPrice));
  const priceFloor = Math.min(...offers.map((o) => o.totalPrice));
  const minDuration = Math.min(...offers.map(totalDuration));

  const [sort, setSort] = useState<Sort>("best");
  const [stops, setStops] = useState<number[]>([]);
  const [airlines, setAirlines] = useState<string[]>([]);
  const [times, setTimes] = useState<string[]>([]);
  const [maxPrice, setMaxPrice] = useState(priceCeiling);

  const airlineOptions = useMemo(() => {
    const map = new Map<string, { name: string; color: string; min: number }>();
    for (const o of offers) {
      const cur = map.get(o.airline.code);
      if (!cur || o.totalPrice < cur.min)
        map.set(o.airline.code, { name: o.airline.name, color: o.airline.color, min: o.totalPrice });
    }
    return [...map.entries()].sort((a, b) => a[1].min - b[1].min);
  }, [offers]);

  const filtered = useMemo(
    () =>
      offers.filter((o) => {
        if (stops.length && !stops.includes(Math.min(o.outbound.stops, 2))) return false;
        if (airlines.length && !airlines.includes(o.airline.code)) return false;
        if (o.totalPrice > maxPrice) return false;
        if (times.length) {
          const t = o.outbound.departMinutes;
          const ok = TIME_WINDOWS.some((w) => times.includes(w.id) && t >= w.from && t < w.to);
          if (!ok) return false;
        }
        return true;
      }),
    [offers, stops, airlines, times, maxPrice],
  );

  const sorted = useMemo(() => {
    const list = [...filtered];
    if (sort === "cheapest") list.sort((a, b) => a.totalPrice - b.totalPrice);
    else if (sort === "fastest") list.sort((a, b) => totalDuration(a) - totalDuration(b));
    else list.sort((a, b) => bestScore(a, priceFloor, minDuration) - bestScore(b, priceFloor, minDuration));
    return list;
  }, [filtered, sort, priceFloor, minDuration]);

  const sortSummary = useMemo(() => {
    if (!filtered.length) return null;
    const cheapest = filtered.reduce((a, b) => (b.totalPrice < a.totalPrice ? b : a));
    const fastest = filtered.reduce((a, b) => (totalDuration(b) < totalDuration(a) ? b : a));
    const best = filtered.reduce((a, b) =>
      bestScore(b, priceFloor, minDuration) < bestScore(a, priceFloor, minDuration) ? b : a,
    );
    return { best, cheapest, fastest };
  }, [filtered, priceFloor, minDuration]);

  function toggle<T>(list: T[], value: T, set: (v: T[]) => void) {
    set(list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
  }

  const hasFilters = stops.length || airlines.length || times.length || maxPrice < priceCeiling;

  return (
    <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
      {/* Filters */}
      <aside className="h-fit space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-slate-900">Filters</h2>
          {hasFilters ? (
            <button
              type="button"
              onClick={() => {
                setStops([]);
                setAirlines([]);
                setTimes([]);
                setMaxPrice(priceCeiling);
              }}
              className="text-sm font-medium text-brand-700 hover:underline"
            >
              Reset
            </button>
          ) : null}
        </div>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Stops</legend>
          {STOP_OPTIONS.map((s) => (
            <label key={s.value} className="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={stops.includes(s.value)}
                onChange={() => toggle(stops, s.value, setStops)}
                className="h-4 w-4 accent-brand-600"
              />
              {s.label}
            </label>
          ))}
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">
            Max price <span className="font-normal text-slate-500">· {formatPrice(maxPrice)}</span>
          </legend>
          <input
            type="range"
            min={priceFloor}
            max={priceCeiling}
            step={1}
            value={maxPrice}
            onChange={(e) => setMaxPrice(Number(e.target.value))}
            className="w-full accent-brand-600"
          />
          <div className="flex justify-between text-xs text-slate-500">
            <span>{formatPrice(priceFloor)}</span>
            <span>{formatPrice(priceCeiling)}</span>
          </div>
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Departure time</legend>
          {TIME_WINDOWS.map((w) => (
            <label key={w.id} className="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={times.includes(w.id)}
                onChange={() => toggle(times, w.id, setTimes)}
                className="h-4 w-4 accent-brand-600"
              />
              {w.label} <span className="text-xs text-slate-400">{w.hint}</span>
            </label>
          ))}
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Airlines</legend>
          {airlineOptions.map(([code, a]) => (
            <label key={code} className="flex cursor-pointer items-center justify-between gap-2 py-1 text-sm text-slate-700">
              <span className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={airlines.includes(code)}
                  onChange={() => toggle(airlines, code, setAirlines)}
                  className="h-4 w-4 accent-brand-600"
                />
                {a.name}
              </span>
              <span className="text-xs text-slate-500">{formatPrice(a.min)}</span>
            </label>
          ))}
        </fieldset>
      </aside>

      {/* Results */}
      <div className="space-y-4">
        {sortSummary && (
          <div className="grid grid-cols-3 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {(["best", "cheapest", "fastest"] as const).map((s) => {
              const offer = sortSummary[s];
              return (
                <button
                  key={s}
                  type="button"
                  onClick={() => setSort(s)}
                  className={`border-b-4 px-3 py-3 text-left transition sm:px-5 ${
                    sort === s ? "border-brand-600 bg-brand-50" : "border-transparent hover:bg-slate-50"
                  }`}
                >
                  <p className="text-sm font-semibold capitalize text-slate-900">{s}</p>
                  <p className="text-lg font-bold text-slate-900">{formatPrice(offer.totalPrice)}</p>
                  <p className="text-xs text-slate-500">{formatDuration(totalDuration(offer))} total</p>
                </button>
              );
            })}
          </div>
        )}

        <p className="text-sm text-slate-600">
          Showing <span className="font-semibold">{sorted.length}</span> of {offers.length} flights ·
          prices for {travellers} traveler{travellers === 1 ? "" : "s"}, taxes included
        </p>

        {sorted.length === 0 && (
          <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
            No flights match your filters. Try removing some.
          </p>
        )}

        {sorted.map((o) => (
          <article
            key={o.id}
            className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md md:grid-cols-[1fr_auto]"
          >
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <span
                  className="grid h-9 w-9 place-items-center rounded-lg text-xs font-bold text-white"
                  style={{ backgroundColor: o.airline.color }}
                >
                  {o.airline.code}
                </span>
                <span className="font-medium text-slate-900">{o.airline.name}</span>
                {o.refundable && (
                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                    Refundable
                  </span>
                )}
                {o.seatsLeft <= 3 && (
                  <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">
                    {o.seatsLeft} seat{o.seatsLeft === 1 ? "" : "s"} left
                  </span>
                )}
              </div>
              <LegRow leg={o.outbound} label="Depart" />
              {o.inbound && <LegRow leg={o.inbound} label="Return" />}
            </div>

            <div className="flex items-center justify-between gap-4 border-t border-slate-100 pt-4 md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-6 md:pt-0">
              <div className="md:text-right">
                <p className="text-2xl font-extrabold text-slate-900">{formatPrice(o.totalPrice)}</p>
                <p className="text-xs text-slate-500">{formatPrice(o.pricePerPerson)} per adult</p>
              </div>
              <Link
                href={bookHref}
                className="rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-bold text-white hover:bg-accent-600"
              >
                Select
              </Link>
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}

function LegRow({ leg, label }: { leg: FlightLeg; label: string }) {
  const arrive = leg.departMinutes + leg.durationMinutes;
  const dayOffset = Math.floor(arrive / 1440);
  const from = findAirport(leg.from);
  const to = findAirport(leg.to);

  return (
    <div className="grid grid-cols-[auto_1fr_auto] items-center gap-3 sm:gap-6">
      <div>
        <p className="text-xs font-medium uppercase text-slate-400">
          {label} · {formatDate(leg.date)}
        </p>
        <p className="text-lg font-bold text-slate-900">{formatTime(leg.departMinutes)}</p>
        <p className="text-sm text-slate-500" title={from?.name}>
          {leg.from}
        </p>
      </div>
      <div className="text-center">
        <p className="text-xs text-slate-500">{formatDuration(leg.durationMinutes)}</p>
        <div className="relative my-1 flex items-center">
          <span className="h-px flex-1 bg-slate-300" />
          {leg.stopCities.map((c) => (
            <span key={c} className="mx-1 h-2 w-2 rounded-full border-2 border-accent-500 bg-white" />
          ))}
          <PlaneIcon width={14} height={14} className="ml-1 rotate-45 text-slate-400" />
        </div>
        <p className={`text-xs font-medium ${leg.stops === 0 ? "text-emerald-600" : "text-accent-600"}`}>
          {leg.stops === 0
            ? "Nonstop"
            : `${leg.stops} stop${leg.stops > 1 ? "s" : ""} · ${leg.stopCities.join(", ")}`}
        </p>
      </div>
      <div className="text-right">
        <p className="text-xs font-medium uppercase text-slate-400">{leg.flightNumber}</p>
        <p className="text-lg font-bold text-slate-900">
          {formatTime(arrive)}
          {dayOffset > 0 && <sup className="ml-0.5 text-xs text-accent-600">+{dayOffset}</sup>}
        </p>
        <p className="text-sm text-slate-500" title={to?.name}>
          {leg.to}
        </p>
      </div>
    </div>
  );
}
