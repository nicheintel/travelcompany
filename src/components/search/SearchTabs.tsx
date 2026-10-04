"use client";

import { useState } from "react";
import { BedIcon, PackageIcon, PlaneIcon } from "../icons";
import { FlightSearchForm } from "./FlightSearchForm";
import { HotelSearchForm } from "./HotelSearchForm";
import { PackageSearchForm } from "./PackageSearchForm";

const TABS = [
  { id: "flights", label: "Flights", icon: PlaneIcon },
  { id: "packages", label: "Flight + Hotel + Car", icon: PackageIcon },
  { id: "hotels", label: "Hotels", icon: BedIcon },
] as const;

type TabId = (typeof TABS)[number]["id"];

export function SearchTabs() {
  const [tab, setTab] = useState<TabId>("flights");

  return (
    <div className="rounded-2xl bg-white shadow-2xl shadow-brand-950/20">
      <div role="tablist" className="flex overflow-x-auto border-b border-slate-100 px-2 sm:px-4">
        {TABS.map(({ id, label, icon: Icon }) => (
          <button
            key={id}
            role="tab"
            type="button"
            aria-selected={tab === id}
            onClick={() => setTab(id)}
            className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-4 text-sm font-semibold transition ${
              tab === id
                ? "border-brand-600 text-brand-700"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
          >
            <Icon width={18} height={18} />
            {label}
            {id === "packages" && (
              <span className="rounded-full bg-accent-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">
                Save
              </span>
            )}
          </button>
        ))}
      </div>
      <div role="tabpanel" className="p-4 sm:p-6">
        {tab === "flights" && <FlightSearchForm />}
        {tab === "packages" && <PackageSearchForm />}
        {tab === "hotels" && <HotelSearchForm />}
      </div>
    </div>
  );
}
