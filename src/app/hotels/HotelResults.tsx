"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { BedIcon, CheckIcon, MapPinIcon, StarIcon } from "@/components/icons";
import { formatPrice } from "@/lib/format";
import { type Amenity, AMENITY_LABELS, type Hotel, ratingLabel } from "@/lib/hotels";

type Sort = "recommended" | "price" | "rating" | "stars";

const FILTER_AMENITIES: Amenity[] = ["breakfast", "pool", "parking", "shuttle", "spa", "gym"];

export function HotelResults({
  hotels,
  nights,
  rooms,
  bookQuery,
}: {
  hotels: Hotel[];
  nights: number;
  rooms: number;
  bookQuery: string;
}) {
  const priceCeiling = Math.max(...hotels.map((h) => h.nightlyPrice));
  const priceFloor = Math.min(...hotels.map((h) => h.nightlyPrice));

  const [sort, setSort] = useState<Sort>("recommended");
  const [stars, setStars] = useState<number[]>([]);
  const [minRating, setMinRating] = useState(0);
  const [amenities, setAmenities] = useState<Amenity[]>([]);
  const [freeCancel, setFreeCancel] = useState(false);
  const [maxPrice, setMaxPrice] = useState(priceCeiling);

  const visible = useMemo(() => {
    const list = hotels.filter(
      (h) =>
        (!stars.length || stars.includes(h.stars)) &&
        h.rating >= minRating &&
        amenities.every((a) => h.amenities.includes(a)) &&
        (!freeCancel || h.freeCancellation) &&
        h.nightlyPrice <= maxPrice,
    );
    const score = (h: Hotel) => h.rating * 10 - h.nightlyPrice / 20 + (h.freeCancellation ? 5 : 0);
    if (sort === "price") return list.sort((a, b) => a.nightlyPrice - b.nightlyPrice);
    if (sort === "rating") return list.sort((a, b) => b.rating - a.rating);
    if (sort === "stars") return list.sort((a, b) => b.stars - a.stars || a.nightlyPrice - b.nightlyPrice);
    return list.sort((a, b) => score(b) - score(a));
  }, [hotels, stars, minRating, amenities, freeCancel, maxPrice, sort]);

  const toggle = <T,>(list: T[], v: T, set: (l: T[]) => void) =>
    set(list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);

  const hasFilters = stars.length || minRating || amenities.length || freeCancel || maxPrice < priceCeiling;

  return (
    <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
      <aside className="h-fit space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-slate-900">Filters</h2>
          {hasFilters ? (
            <button
              type="button"
              onClick={() => {
                setStars([]);
                setMinRating(0);
                setAmenities([]);
                setFreeCancel(false);
                setMaxPrice(priceCeiling);
              }}
              className="text-sm font-medium text-brand-700 hover:underline"
            >
              Reset
            </button>
          ) : null}
        </div>

        <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" checked={freeCancel} onChange={(e) => setFreeCancel(e.target.checked)} className="h-4 w-4 accent-brand-600" />
          Free cancellation
        </label>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">
            Price per night <span className="font-normal text-slate-500">· up to {formatPrice(maxPrice)}</span>
          </legend>
          <input
            type="range"
            min={priceFloor}
            max={priceCeiling}
            value={maxPrice}
            onChange={(e) => setMaxPrice(Number(e.target.value))}
            className="w-full accent-brand-600"
          />
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Star rating</legend>
          <div className="flex flex-wrap gap-2">
            {[5, 4, 3, 2].map((s) => (
              <button
                key={s}
                type="button"
                aria-pressed={stars.includes(s)}
                onClick={() => toggle(stars, s, setStars)}
                className={`flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm ring-1 ${
                  stars.includes(s) ? "bg-brand-600 text-white ring-brand-600" : "text-slate-700 ring-slate-200 hover:ring-slate-300"
                }`}
              >
                {s} <StarIcon width={12} height={12} />
              </button>
            ))}
          </div>
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Guest rating</legend>
          {[
            { v: 0, l: "Any" },
            { v: 9, l: "Exceptional 9+" },
            { v: 8, l: "Very good 8+" },
            { v: 7, l: "Good 7+" },
          ].map(({ v, l }) => (
            <label key={v} className="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700">
              <input type="radio" name="minRating" checked={minRating === v} onChange={() => setMinRating(v)} className="h-4 w-4 accent-brand-600" />
              {l}
            </label>
          ))}
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-slate-700">Amenities</legend>
          {FILTER_AMENITIES.map((a) => (
            <label key={a} className="flex cursor-pointer items-center gap-2 py-1 text-sm text-slate-700">
              <input type="checkbox" checked={amenities.includes(a)} onChange={() => toggle(amenities, a, setAmenities)} className="h-4 w-4 accent-brand-600" />
              {AMENITY_LABELS[a]}
            </label>
          ))}
        </fieldset>
      </aside>

      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-slate-600">
            <span className="font-semibold">{visible.length}</span> of {hotels.length} properties · prices for{" "}
            {nights} night{nights === 1 ? "" : "s"}, {rooms} room{rooms === 1 ? "" : "s"}
          </p>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            Sort by
            <select value={sort} onChange={(e) => setSort(e.target.value as Sort)} className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium">
              <option value="recommended">Recommended</option>
              <option value="price">Lowest price</option>
              <option value="rating">Guest rating</option>
              <option value="stars">Star rating</option>
            </select>
          </label>
        </div>

        {visible.length === 0 && (
          <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
            No hotels match your filters. Try removing some.
          </p>
        )}

        {visible.map((h) => {
          const total = h.nightlyPrice * nights * rooms;
          return (
            <article
              key={h.id}
              className="grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md sm:grid-cols-[220px_1fr]"
            >
              <div className={`relative flex min-h-40 items-end bg-gradient-to-br ${h.gradient} p-4 text-white`}>
                <BedIcon className="absolute right-4 top-4 text-white/30" width={48} height={48} />
                {h.originalNightly && (
                  <span className="absolute left-3 top-3 rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold">
                    Deal −{Math.round((1 - h.nightlyPrice / h.originalNightly) * 100)}%
                  </span>
                )}
                <span className="text-xs font-medium">{h.roomType}</span>
              </div>
              <div className="flex flex-col gap-4 p-5 md:flex-row md:justify-between">
                <div className="min-w-0 space-y-2">
                  <div className="flex text-amber-400" aria-label={`${h.stars} stars`}>
                    {Array.from({ length: h.stars }).map((_, i) => (
                      <StarIcon key={i} width={14} height={14} />
                    ))}
                  </div>
                  <h3 className="text-lg font-semibold text-slate-900">{h.name}</h3>
                  <p className="flex items-center gap-1 text-sm text-slate-500">
                    <MapPinIcon width={14} height={14} /> {h.neighborhood} · {h.distanceKm} km from center
                  </p>
                  <div className="flex items-center gap-2">
                    <span className="rounded-lg bg-brand-700 px-2 py-1 text-sm font-bold text-white">{h.rating.toFixed(1)}</span>
                    <span className="text-sm font-semibold text-slate-800">{ratingLabel(h.rating)}</span>
                    <span className="text-xs text-slate-500">{h.reviews.toLocaleString("en-US")} reviews</span>
                  </div>
                  <ul className="flex flex-wrap gap-1.5 text-xs">
                    {h.amenities.slice(0, 4).map((a) => (
                      <li key={a} className="rounded-md bg-slate-100 px-2 py-1 text-slate-700">
                        {AMENITY_LABELS[a]}
                      </li>
                    ))}
                  </ul>
                  {h.freeCancellation && (
                    <p className="flex items-center gap-1 text-sm font-medium text-emerald-700">
                      <CheckIcon width={14} height={14} /> Free cancellation
                    </p>
                  )}
                </div>
                <div className="flex shrink-0 flex-row items-end justify-between gap-3 md:flex-col md:text-right">
                  <div>
                    {h.originalNightly && (
                      <p className="text-sm text-slate-400 line-through">{formatPrice(h.originalNightly)}</p>
                    )}
                    <p className="text-2xl font-extrabold text-slate-900">{formatPrice(h.nightlyPrice)}</p>
                    <p className="text-xs text-slate-500">per night</p>
                    <p className="mt-1 text-xs text-slate-600">
                      {formatPrice(total)} total + taxes
                    </p>
                  </div>
                  <Link
                    href={`/book/hotel?${bookQuery}&hotel=${encodeURIComponent(h.id)}`}
                    className="rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-bold text-white hover:bg-accent-600"
                  >
                    Reserve
                  </Link>
                </div>
              </div>
            </article>
          );
        })}
      </div>
    </div>
  );
}
