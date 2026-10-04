"use client";

import Form from "next/form";
import { useState } from "react";
import { addDays } from "@/lib/format";
import { useToday } from "@/lib/useToday";
import { BedIcon, CarIcon, PlaneIcon, SearchIcon } from "../icons";
import { AirportInput } from "./AirportInput";
import { DateField, SubmitButton, TravellersField, type Travellers } from "./fields";

export type PackageFormDefaults = {
  from?: string;
  to?: string;
  depart?: string;
  return?: string;
  car?: boolean;
};

export function PackageSearchForm({ defaults = {} }: { defaults?: PackageFormDefaults }) {
  const today = useToday();
  const [from, setFrom] = useState(defaults.from ?? "JFK");
  const [to, setTo] = useState(defaults.to ?? "");
  const [departPick, setDepartPick] = useState(defaults.depart ?? "");
  const [returnPick, setReturnPick] = useState(defaults.return ?? "");
  const [car, setCar] = useState(defaults.car ?? true);
  const [travellers, setTravellers] = useState<Travellers>({ adults: 2, children: 0, rooms: 1 });
  const [error, setError] = useState<string | null>(null);

  const depart = departPick || (today ? addDays(today, 21) : "");
  const minReturn = depart || today;
  const returnDate =
    returnPick && returnPick > minReturn ? returnPick : depart ? addDays(depart, 5) : "";

  return (
    <Form
      action="/packages"
      scroll={false}
      onSubmit={(e) => {
        if (!to) {
          e.preventDefault();
          setError("Please choose a destination.");
        } else {
          setError(null);
        }
      }}
      className="space-y-4"
    >
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 font-medium text-brand-700">
          <PlaneIcon width={14} height={14} /> Flight
        </span>
        <span className="text-slate-400">+</span>
        <span className="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 font-medium text-brand-700">
          <BedIcon width={14} height={14} /> Hotel
        </span>
        <span className="text-slate-400">+</span>
        <label
          className={`flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 font-medium transition ${
            car
              ? "border-brand-600 bg-brand-600 text-white"
              : "border-dashed border-slate-300 bg-white text-slate-600 hover:border-slate-400"
          }`}
        >
          <input
            type="checkbox"
            name="car"
            value="1"
            checked={car}
            onChange={(e) => setCar(e.target.checked)}
            className="sr-only"
          />
          <CarIcon width={14} height={14} /> {car ? "Car added" : "Add a car"}
        </label>
        <span className="ml-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
          Bundle & save up to 40%
        </span>
      </div>

      <div className="grid gap-3 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_1fr_auto]">
        <AirportInput name="from" label="Leaving from" placeholder="City or airport" value={from} onChange={setFrom} />
        <AirportInput name="to" label="Going to" placeholder="Destination" value={to} onChange={setTo} />
        <DateField label="Check-in" name="depart" value={depart} min={today} onChange={setDepartPick} />
        <DateField label="Check-out" name="return" value={returnDate} min={minReturn} onChange={setReturnPick} />
        <TravellersField value={travellers} onChange={setTravellers} showRooms />
        <SubmitButton>
          <SearchIcon width={18} height={18} />
          <span>Find deals</span>
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
