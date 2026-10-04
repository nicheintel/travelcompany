import Link from "next/link";
import { formatPrice } from "@/lib/format";
import type { PromoPackage } from "@/lib/packages";
import { BedIcon, CarIcon, CheckIcon, PlaneIcon, StarIcon } from "./icons";

export function PackageCard({ pkg }: { pkg: PromoPackage }) {
  const savings = pkg.originalPrice - pkg.price;
  const savingsPct = Math.round((savings / pkg.originalPrice) * 100);

  return (
    <article className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
      <div className={`relative h-44 bg-gradient-to-br ${pkg.gradient} p-4 text-white`}>
        <svg
          className="absolute inset-0 h-full w-full opacity-20"
          viewBox="0 0 400 180"
          preserveAspectRatio="none"
          aria-hidden
        >
          <path d="M0 140 Q100 100 200 130 T400 120 V180 H0Z" fill="white" />
          <path d="M0 160 Q120 130 240 155 T400 150 V180 H0Z" fill="white" />
        </svg>
        <div className="relative flex items-start justify-between">
          {pkg.badge ? (
            <span className="rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-slate-900 shadow">
              {pkg.badge}
            </span>
          ) : (
            <span />
          )}
          <span className="rounded-full bg-accent-500 px-3 py-1 text-xs font-bold shadow">
            -{savingsPct}%
          </span>
        </div>
        <div className="absolute bottom-4 left-4 right-4">
          <p className="text-sm font-medium text-white/90">
            {pkg.country} · {pkg.nights} nights
          </p>
          <h3 className="text-xl font-bold drop-shadow">{pkg.destination}</h3>
        </div>
      </div>

      <div className="flex flex-1 flex-col p-5">
        <h4 className="font-semibold text-slate-900">{pkg.title}</h4>
        <div className="mt-1 flex items-center gap-2 text-sm text-slate-600">
          <span className="flex text-amber-400">
            {Array.from({ length: pkg.hotelStars }).map((_, i) => (
              <StarIcon key={i} width={13} height={13} />
            ))}
          </span>
          <span className="truncate">{pkg.hotel}</span>
        </div>

        <div className="mt-3 flex flex-wrap gap-1.5 text-xs font-medium">
          <span className="flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-brand-700">
            <PlaneIcon width={12} height={12} /> Flight
          </span>
          <span className="flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-brand-700">
            <BedIcon width={12} height={12} /> Hotel
          </span>
          {pkg.includesCar && (
            <span className="flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-brand-700">
              <CarIcon width={12} height={12} /> Car
            </span>
          )}
        </div>

        <ul className="mt-3 space-y-1 text-sm text-slate-600">
          {pkg.perks.map((perk) => (
            <li key={perk} className="flex items-center gap-2">
              <CheckIcon width={14} height={14} className="text-emerald-500" />
              {perk}
            </li>
          ))}
        </ul>

        <div className="mt-auto flex items-end justify-between gap-3 pt-5">
          <div>
            <p className="text-xs text-slate-500">
              <span className="font-semibold text-slate-700">{pkg.rating}</span>/5 ·{" "}
              {pkg.reviews.toLocaleString("en-US")} reviews
            </p>
            <p className="text-sm text-slate-400 line-through">{formatPrice(pkg.originalPrice)}</p>
            <p className="text-2xl font-extrabold text-slate-900">
              {formatPrice(pkg.price)}
              <span className="text-xs font-medium text-slate-500"> /person</span>
            </p>
            <p className="text-xs font-semibold text-emerald-600">
              You save {formatPrice(savings)}
            </p>
          </div>
          <Link
            href={`/signin?next=${encodeURIComponent(`/packages#${pkg.id}`)}`}
            className="shrink-0 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700"
          >
            View deal
          </Link>
        </div>
      </div>
    </article>
  );
}
