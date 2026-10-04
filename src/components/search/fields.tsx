"use client";

import { useEffect, useId, useRef, useState } from "react";
import { CABIN_LABELS, type CabinClass } from "@/lib/flights";
import { CalendarIcon, UserIcon } from "../icons";

export function DateField({
  label,
  name,
  value,
  min,
  onChange,
  disabled,
}: {
  label: string;
  name: string;
  value: string;
  min?: string;
  onChange: (v: string) => void;
  disabled?: boolean;
}) {
  const id = useId();
  return (
    <label
      htmlFor={id}
      className={`flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 ${
        disabled ? "opacity-50" : ""
      }`}
    >
      <span className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</span>
      <span className="flex items-center gap-2">
        <CalendarIcon width={16} height={16} className="shrink-0 text-brand-500" />
        <input
          id={id}
          type="date"
          name={disabled ? undefined : name}
          value={disabled ? "" : value}
          min={min || undefined}
          disabled={disabled}
          required={!disabled}
          onChange={(e) => onChange(e.target.value)}
          className="w-full bg-transparent text-base font-semibold text-slate-900 outline-none"
        />
      </span>
    </label>
  );
}

function Counter({
  label,
  hint,
  value,
  min,
  max,
  onChange,
}: {
  label: string;
  hint: string;
  value: number;
  min: number;
  max: number;
  onChange: (v: number) => void;
}) {
  return (
    <div className="flex items-center justify-between py-2">
      <div>
        <p className="font-medium text-slate-900">{label}</p>
        <p className="text-xs text-slate-500">{hint}</p>
      </div>
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => onChange(Math.max(min, value - 1))}
          disabled={value <= min}
          className="grid h-8 w-8 place-items-center rounded-full border border-slate-300 text-lg text-slate-700 hover:border-brand-500 hover:text-brand-600 disabled:opacity-30"
          aria-label={`Fewer ${label.toLowerCase()}`}
        >
          −
        </button>
        <span className="w-4 text-center font-semibold">{value}</span>
        <button
          type="button"
          onClick={() => onChange(Math.min(max, value + 1))}
          disabled={value >= max}
          className="grid h-8 w-8 place-items-center rounded-full border border-slate-300 text-lg text-slate-700 hover:border-brand-500 hover:text-brand-600 disabled:opacity-30"
          aria-label={`More ${label.toLowerCase()}`}
        >
          +
        </button>
      </div>
    </div>
  );
}

export type Travellers = {
  adults: number;
  children: number;
  rooms?: number;
  cabin?: CabinClass;
};

/** Popover for passengers, rooms and cabin class. Emits hidden inputs for the form. */
export function TravellersField({
  value,
  onChange,
  showCabin = false,
  showRooms = false,
}: {
  value: Travellers;
  onChange: (v: Travellers) => void;
  showCabin?: boolean;
  showRooms?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [open]);

  const people = value.adults + value.children;
  const summary = [
    `${people} traveler${people === 1 ? "" : "s"}`,
    showRooms && value.rooms ? `${value.rooms} room${value.rooms === 1 ? "" : "s"}` : null,
    showCabin && value.cabin ? CABIN_LABELS[value.cabin] : null,
  ]
    .filter(Boolean)
    .join(", ");

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="flex w-full flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-left transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
      >
        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">
          {showRooms ? "Travelers & rooms" : "Travelers & class"}
        </span>
        <span className="flex items-center gap-2">
          <UserIcon width={16} height={16} className="shrink-0 text-brand-500" />
          <span className="truncate text-base font-semibold text-slate-900">{summary}</span>
        </span>
      </button>

      <input type="hidden" name="adults" value={value.adults} />
      <input type="hidden" name="children" value={value.children} />
      {showRooms && <input type="hidden" name="rooms" value={value.rooms ?? 1} />}
      {showCabin && <input type="hidden" name="cabin" value={value.cabin ?? "economy"} />}

      {open && (
        <div className="absolute right-0 top-full z-30 mt-2 w-full min-w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
          <Counter
            label="Adults"
            hint="Age 12+"
            value={value.adults}
            min={1}
            max={9}
            onChange={(adults) => onChange({ ...value, adults })}
          />
          <Counter
            label="Children"
            hint="Age 2–11"
            value={value.children}
            min={0}
            max={8}
            onChange={(children) => onChange({ ...value, children })}
          />
          {showRooms && (
            <Counter
              label="Rooms"
              hint="Max 4 per booking"
              value={value.rooms ?? 1}
              min={1}
              max={4}
              onChange={(rooms) => onChange({ ...value, rooms })}
            />
          )}
          {showCabin && (
            <div className="mt-2 border-t border-slate-100 pt-3">
              <p className="mb-2 text-sm font-medium text-slate-900">Cabin class</p>
              <div className="grid grid-cols-2 gap-2">
                {(Object.keys(CABIN_LABELS) as CabinClass[]).map((c) => (
                  <button
                    key={c}
                    type="button"
                    onClick={() => onChange({ ...value, cabin: c })}
                    className={`rounded-lg border px-3 py-2 text-sm ${
                      value.cabin === c
                        ? "border-brand-500 bg-brand-50 font-semibold text-brand-700"
                        : "border-slate-200 text-slate-700 hover:border-slate-300"
                    }`}
                  >
                    {CABIN_LABELS[c]}
                  </button>
                ))}
              </div>
            </div>
          )}
          <button
            type="button"
            onClick={() => setOpen(false)}
            className="mt-4 w-full rounded-lg bg-brand-600 py-2 text-sm font-semibold text-white hover:bg-brand-700"
          >
            Done
          </button>
        </div>
      )}
    </div>
  );
}

export function SubmitButton({ children }: { children: React.ReactNode }) {
  return (
    <button
      type="submit"
      className="flex h-full min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-accent-500 px-6 text-base font-bold text-white shadow-lg shadow-accent-500/30 transition hover:bg-accent-600"
    >
      {children}
    </button>
  );
}
