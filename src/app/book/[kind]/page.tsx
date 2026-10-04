import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { TripSummary } from "@/components/TripSummary";
import { BOOKING_KINDS, type BookingKind, buildQuote } from "@/lib/quote";
import { requireUser } from "@/lib/server/dal";
import { paymentsEnabled } from "@/lib/server/payments";
import { BookingForm } from "./BookingForm";
import { PackageOptions } from "./PackageOptions";

export const metadata: Metadata = { title: "Complete your booking" };

const BACK = {
  flight: { label: "flight results", href: (q: string) => `/flights?${q}` },
  hotel: { label: "hotel results", href: (q: string) => `/hotels?${q}` },
  package: { label: "packages", href: () => "/packages" },
} as const;

function toQuery(params: Record<string, string | string[] | undefined>) {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (typeof v === "string") q.set(k, v);
    else if (Array.isArray(v) && v[0]) q.set(k, v[0]);
  }
  return q.toString();
}

export default async function BookPage({ params, searchParams }: PageProps<"/book/[kind]">) {
  const { kind } = await params;
  if (!BOOKING_KINDS.includes(kind as BookingKind)) notFound();
  const sp = await searchParams;

  const user = await requireUser(`/book/${kind}?${toQuery(sp)}`);
  const quote = buildQuote(kind, sp);

  if (!quote) {
    return (
      <div className="mx-auto max-w-xl px-4 py-24 text-center">
        <h1 className="text-2xl font-bold text-slate-900">This deal is no longer available</h1>
        <p className="mt-2 text-slate-600">Prices and availability change quickly. Please search again.</p>
        <Link href={`/${kind === "flight" ? "flights" : kind === "hotel" ? "hotels" : "packages"}`} className="mt-6 inline-block rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white">
          Search again
        </Link>
      </div>
    );
  }

  const [firstName, ...rest] = user.name.split(" ");
  const pkgParams = new URLSearchParams(quote.query);

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
      <nav className="mb-4 text-sm text-slate-500">
        <Link href={BACK[quote.kind].href(quote.query)} className="hover:text-brand-700">
          ← Back to {BACK[quote.kind].label}
        </Link>
      </nav>
      <h1 className="text-3xl font-bold text-slate-900">Complete your booking</h1>
      <p className="mt-1 text-slate-600">Almost there — just a few details and your trip is reserved.</p>

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          {quote.kind === "package" && (
            <PackageOptions
              key={quote.query}
              id={pkgParams.get("id")!}
              from={pkgParams.get("from")!}
              depart={pkgParams.get("depart")!}
              adults={Number(pkgParams.get("adults"))}
            />
          )}
          <BookingForm
            key={quote.query}
            kind={quote.kind}
            query={quote.query}
            total={quote.total}
            slots={quote.travelerSlots}
            defaults={{ firstName, lastName: rest.join(" "), email: user.email }}
            payOnline={paymentsEnabled()}
          />
        </div>
        <aside className="h-fit lg:sticky lg:top-20">
          <TripSummary quote={quote} />
        </aside>
      </div>
    </div>
  );
}
