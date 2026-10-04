"use client";

import Form from "next/form";
import { useState } from "react";
import { AirportInput } from "@/components/search/AirportInput";
import { DateField } from "@/components/search/fields";
import { useToday } from "@/lib/useToday";

/** Changes the package's origin, date or traveler count by reloading the page with new params. */
export function PackageOptions({
  id,
  from: initialFrom,
  depart: initialDepart,
  adults,
}: {
  id: string;
  from: string;
  depart: string;
  adults: number;
}) {
  const today = useToday();
  const [from, setFrom] = useState(initialFrom);
  const [depart, setDepart] = useState(initialDepart);

  return (
    <Form action="/book/package" scroll={false} replace className="rounded-2xl border border-slate-200 bg-white p-6">
      <h2 className="text-lg font-semibold text-slate-900">Trip options</h2>
      <input type="hidden" name="id" value={id} />
      <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_0.8fr_auto]">
        <AirportInput name="from" label="Leaving from" placeholder="City or airport" value={from} onChange={setFrom} />
        <DateField label="Departure" name="depart" value={depart} min={today} onChange={setDepart} />
        <label className="flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5">
          <span className="text-xs font-medium uppercase tracking-wide text-slate-500">Travelers</span>
          <select
            name="adults"
            defaultValue={adults}
            className="bg-transparent text-base font-semibold text-slate-900 outline-none"
          >
            {[1, 2, 3, 4, 5, 6].map((n) => (
              <option key={n} value={n}>
                {n} adult{n === 1 ? "" : "s"}
              </option>
            ))}
          </select>
        </label>
        <button
          type="submit"
          className="rounded-xl px-5 py-3 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50"
        >
          Update price
        </button>
      </div>
    </Form>
  );
}
