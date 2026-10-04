"use client";

import { useMemo, useState } from "react";
import { PackageCard } from "@/components/PackageCard";
import { PACKAGE_CATEGORIES, type PackageCategory, type PromoPackage } from "@/lib/packages";

type Sort = "recommended" | "price" | "savings" | "rating";

export function PackageGrid({ packages }: { packages: PromoPackage[] }) {
  const [category, setCategory] = useState<PackageCategory | "All">("All");
  const [carOnly, setCarOnly] = useState(false);
  const [sort, setSort] = useState<Sort>("recommended");

  const visible = useMemo(() => {
    const list = packages.filter(
      (p) => (category === "All" || p.category === category) && (!carOnly || p.includesCar),
    );
    if (sort === "price") return [...list].sort((a, b) => a.price - b.price);
    if (sort === "savings")
      return [...list].sort((a, b) => b.originalPrice - b.price - (a.originalPrice - a.price));
    if (sort === "rating") return [...list].sort((a, b) => b.rating - a.rating);
    return list;
  }, [packages, category, carOnly, sort]);

  return (
    <div>
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex flex-wrap gap-2">
          {(["All", ...PACKAGE_CATEGORIES] as const).map((c) => (
            <button
              key={c}
              type="button"
              onClick={() => setCategory(c)}
              className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${
                category === c
                  ? "bg-slate-900 text-white"
                  : "bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-slate-300"
              }`}
            >
              {c}
            </button>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-4 text-sm">
          <label className="flex cursor-pointer items-center gap-2 text-slate-700">
            <input
              type="checkbox"
              checked={carOnly}
              onChange={(e) => setCarOnly(e.target.checked)}
              className="h-4 w-4 accent-brand-600"
            />
            Includes rental car
          </label>
          <label className="flex items-center gap-2 text-slate-700">
            Sort by
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value as Sort)}
              className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium"
            >
              <option value="recommended">Recommended</option>
              <option value="price">Lowest price</option>
              <option value="savings">Biggest savings</option>
              <option value="rating">Guest rating</option>
            </select>
          </label>
        </div>
      </div>

      {visible.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
          No packages match these filters yet. Try another category.
        </p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {visible.map((pkg) => (
            <div key={pkg.id} id={pkg.id} className="scroll-mt-24">
              <PackageCard pkg={pkg} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
