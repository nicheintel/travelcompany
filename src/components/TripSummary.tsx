import { LegRow } from "@/app/flights/FlightResults";
import { formatPrice } from "@/lib/format";
import type { Quote } from "@/lib/quote";
import { BedIcon, PackageIcon, PlaneIcon } from "./icons";

const KIND_ICON = { flight: PlaneIcon, package: PackageIcon, hotel: BedIcon } as const;
const KIND_LABEL = { flight: "Flight", package: "Flight + Hotel package", hotel: "Hotel" } as const;

/** Itinerary + price breakdown. Used on the booking page and the trip details page. */
export function TripSummary({ quote }: { quote: Quote }) {
  const Icon = KIND_ICON[quote.kind];
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <div className="bg-gradient-to-br from-brand-800 to-brand-600 p-5 text-white">
        <p className="flex items-center gap-2 text-sm font-medium text-brand-100">
          <Icon width={16} height={16} /> {KIND_LABEL[quote.kind]}
        </p>
        <h2 className="mt-1 text-xl font-bold">{quote.title}</h2>
        <p className="mt-1 text-sm text-brand-100">{quote.subtitle}</p>
      </div>

      {quote.flight && (
        <div className="space-y-4 border-b border-slate-100 p-5">
          <LegRow leg={quote.flight.outbound} label="Depart" />
          {quote.flight.inbound && <LegRow leg={quote.flight.inbound} label="Return" />}
        </div>
      )}

      <dl className="space-y-2 border-b border-slate-100 p-5 text-sm">
        {quote.facts.map((f) => (
          <div key={f.label} className="flex justify-between gap-4">
            <dt className="text-slate-500">{f.label}</dt>
            <dd className="text-right font-medium text-slate-900">{f.value}</dd>
          </div>
        ))}
      </dl>

      <dl className="space-y-2 p-5 text-sm">
        {quote.lines.map((l) => (
          <div key={l.label} className="flex justify-between">
            <dt className="text-slate-600">{l.label}</dt>
            <dd className="text-slate-900">{formatPrice(l.amount)}</dd>
          </div>
        ))}
        {quote.discount > 0 && (
          <div className="flex justify-between text-emerald-700">
            <dt>Member discount ({Math.round((quote.discountRate ?? 0.1) * 100)}%)</dt>
            <dd>−{formatPrice(quote.discount)}</dd>
          </div>
        )}
        <div className="flex items-end justify-between border-t border-slate-100 pt-3">
          <dt className="font-semibold text-slate-900">Total</dt>
          <dd className="text-2xl font-extrabold text-slate-900">{formatPrice(quote.total)}</dd>
        </div>
        <p className="text-right text-xs text-slate-500">Taxes and fees included</p>
        {quote.note && <p className="pt-2 text-xs leading-relaxed text-slate-500">{quote.note}</p>}
      </dl>
    </div>
  );
}
