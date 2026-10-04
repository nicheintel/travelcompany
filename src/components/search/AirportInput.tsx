"use client";

import { useId, useState } from "react";
import { findAirport, searchAirports } from "@/lib/airports";
import { MapPinIcon } from "../icons";

type Props = {
  name: string;
  label: string;
  placeholder: string;
  value: string;
  onChange: (code: string) => void;
};

export function AirportInput({ name, label, placeholder, value, onChange }: Props) {
  const id = useId();
  // null = not editing; show the selected airport instead of the typed query.
  const [query, setQuery] = useState<string | null>(null);
  const [highlight, setHighlight] = useState(0);

  const selected = findAirport(value);
  const display = query ?? (selected ? `${selected.city} (${selected.code})` : "");
  const results = query !== null ? searchAirports(query) : [];
  const open = query !== null && results.length > 0;

  function choose(code: string) {
    onChange(code);
    setQuery(null);
  }

  return (
    <div className="relative">
      <label
        htmlFor={id}
        className="flex h-full flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100"
      >
        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</span>
        <span className="flex items-center gap-2">
          <MapPinIcon width={16} height={16} className="shrink-0 text-brand-500" />
          <input
            id={id}
            type="text"
            autoComplete="off"
            role="combobox"
            aria-expanded={open}
            aria-controls={`${id}-list`}
            placeholder={placeholder}
            value={display}
            onFocus={(e) => {
              setQuery("");
              setHighlight(0);
              e.currentTarget.select();
            }}
            onChange={(e) => {
              setQuery(e.target.value);
              setHighlight(0);
            }}
            onBlur={() => {
              // Accept a typed IATA code, otherwise revert to the last selection.
              const typed = query && findAirport(query);
              if (typed) onChange(typed.code);
              setQuery(null);
            }}
            onKeyDown={(e) => {
              if (!open) return;
              if (e.key === "ArrowDown") {
                e.preventDefault();
                setHighlight((h) => Math.min(h + 1, results.length - 1));
              } else if (e.key === "ArrowUp") {
                e.preventDefault();
                setHighlight((h) => Math.max(h - 1, 0));
              } else if (e.key === "Enter") {
                e.preventDefault();
                choose(results[highlight].code);
                e.currentTarget.blur();
              } else if (e.key === "Escape") {
                setQuery(null);
              }
            }}
            className="w-full truncate bg-transparent text-base font-semibold text-slate-900 outline-none placeholder:font-normal placeholder:text-slate-400"
          />
        </span>
      </label>
      <input type="hidden" name={name} value={value} />

      {open && (
        <ul
          id={`${id}-list`}
          role="listbox"
          className="absolute left-0 right-0 top-full z-30 mt-2 max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white py-2 shadow-xl sm:min-w-80"
        >
          {results.map((a, i) => (
            <li
              key={a.code}
              role="option"
              aria-selected={i === highlight}
              onMouseDown={(e) => {
                e.preventDefault();
                choose(a.code);
                (document.activeElement as HTMLElement | null)?.blur();
              }}
              onMouseEnter={() => setHighlight(i)}
              className={`flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 ${
                i === highlight ? "bg-brand-50" : ""
              }`}
            >
              <span>
                <span className="block font-medium text-slate-900">
                  {a.city}, {a.country}
                </span>
                <span className="block text-xs text-slate-500">{a.name}</span>
              </span>
              <span className="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700">
                {a.code}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
