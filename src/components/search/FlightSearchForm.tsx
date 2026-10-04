"use client";

import Form from "next/form";
import { useState } from "react";
import { addDays } from "@/lib/format";
import type { CabinClass } from "@/lib/flights";
import { useToday } from "@/lib/useToday";
import { SearchIcon, SwapIcon } from "../icons";
import { AirportInput } from "./AirportInput";
import { DateField, SubmitButton, TravellersField, type Travellers } from "./fields";

export type FlightFormDefaults = {
  from?: string;
  to?: string;
  depart?: string;
  return?: string;
  trip?: "roundtrip" | "oneway";
  adults?: number;
  children?: number;
  cabin?: CabinClass;
};

export function FlightSearchForm({ defaults = {} }: { defaults?: FlightFormDefaults }) {
  const today = useToday();
  const [trip, setTrip] = useState<"roundtrip" | "oneway">(defaults.trip ?? "roundtrip");
  const [from, setFrom] = useState(defaults.from ?? "JFK");
  const [to, setTo] = useState(defaults.to ?? "");
  // Empty string = user hasn't picked yet; fall back to a sensible default below.
  const [departPick, setDepartPick] = useState(defaults.depart ?? "");
  const [returnPick, setReturnPick] = useState(defaults.return ?? "");
  const [travellers, setTravellers] = useState<Travellers>({
    adults: defaults.adults ?? 1,
    children: defaults.children ?? 0,
    cabin: defaults.cabin ?? "economy",
  });
  const [error, setError] = useState<string | null>(null);

  const depart = departPick || (today ? addDays(today, 14) : "");
  const minReturn = depart || today;
  const returnDate =
    returnPick && returnPick >= minReturn ? returnPick : depart ? addDays(depart, 7) : "";

  return (
    <Form
      action="/flights"
      onSubmit={(e) => {
        if (!from || !to) {
          e.preventDefault();
          setError("Please choose where you're flying from and to.");
        } else if (from === to) {
          e.preventDefault();
          setError("Origin and destination must be different.");
        } else {
          setError(null);
        }
      }}
      className="space-y-4"
    >
      <div className="flex flex-wrap items-center gap-2">
        {(["roundtrip", "oneway"] as const).map((t) => (
          <label
            key={t}
            className={`cursor-pointer rounded-full border px-4 py-1.5 text-sm font-medium transition ${
              trip === t
                ? "border-brand-600 bg-brand-600 text-white"
                : "border-slate-200 bg-white text-slate-700 hover:border-slate-300"
            }`}
          >
            <input
              type="radio"
              name="trip"
              value={t}
              checked={trip === t}
              onChange={() => setTrip(t)}
              className="sr-only"
            />
            {t === "roundtrip" ? "Round trip" : "One way"}
          </label>
        ))}
      </div>

      <div className="grid gap-3 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_1fr_auto]">
        <div className="relative grid gap-3 sm:grid-cols-2 lg:col-span-2">
          <AirportInput
            name="from"
            label="From"
            placeholder="City or airport"
            value={from}
            onChange={setFrom}
          />
          <button
            type="button"
            onClick={() => {
              setFrom(to);
              setTo(from);
            }}
            className="absolute left-1/2 top-1/2 z-10 hidden h-9 w-9 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-brand-600 shadow-sm hover:bg-brand-50 sm:grid"
            aria-label="Swap origin and destination"
          >
            <SwapIcon width={16} height={16} />
          </button>
          <AirportInput
            name="to"
            label="To"
            placeholder="Where to?"
            value={to}
            onChange={setTo}
          />
        </div>
        <DateField
          label="Depart"
          name="depart"
          value={depart}
          min={today}
          onChange={setDepartPick}
        />
        <DateField
          label="Return"
          name="return"
          value={returnDate}
          min={minReturn}
          onChange={setReturnPick}
          disabled={trip === "oneway"}
        />
        <TravellersField value={travellers} onChange={setTravellers} showCabin />
        <SubmitButton>
          <SearchIcon width={18} height={18} />
          <span>Search</span>
        </SubmitButton>
      </div>

      {error && (
        <p role="alert" className="text-sm font-medium text-red-600">
          {error}
        </p>
      )}
    </Form>
  );
}
