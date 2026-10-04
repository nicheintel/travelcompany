import "server-only";
import type { CabinClass, FlightLeg, FlightOffer, FlightSearch } from "../../flights";
import { flightMarkup, sellPrice, type SupplierCost, toUsd } from "../pricing";

/** Duffel Flights API (https://duffel.com/docs). DUFFEL_API_BASE exists only for tests. */
const BASE = process.env.DUFFEL_API_BASE ?? "https://api.duffel.com";

export function duffelEnabled() {
  return !!process.env.DUFFEL_ACCESS_TOKEN;
}

// --- The subset of Duffel's response we use (field names as in @duffel/api types) ---
type DPlace = { iata_code: string | null; city_name?: string; name: string };
type DAirline = { name: string; iata_code: string | null; logo_symbol_url: string | null };
type DSegment = {
  departing_at: string;
  arriving_at: string;
  origin: DPlace;
  destination: DPlace;
  marketing_carrier: DAirline;
  marketing_carrier_flight_number: string;
  stops?: unknown[];
};
type DSlice = { origin: DPlace; destination: DPlace; duration: string | null; segments: DSegment[] };
type DOffer = {
  id: string;
  total_amount: string;
  total_currency: string;
  expires_at: string;
  owner: DAirline;
  conditions: { refund_before_departure: { allowed: boolean } | null } | null;
  passengers: { type: string | null; age: number | null }[];
  slices: DSlice[];
};

async function duffel<T>(method: "GET" | "POST", path: string, body?: unknown): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${process.env.DUFFEL_ACCESS_TOKEN}`,
      "Duffel-Version": "v2",
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: body ? JSON.stringify({ data: body }) : undefined,
    cache: "no-store",
    signal: AbortSignal.timeout(45_000),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`Duffel ${res.status}: ${json?.errors?.[0]?.message ?? "request failed"}`);
  return json.data as T;
}

const CABIN: Record<CabinClass, string> = {
  economy: "economy",
  premium: "premium_economy",
  business: "business",
  first: "first",
};

/** "PT7H35M" / "P1DT2H5M" → minutes */
function isoMinutes(d: string | null) {
  const m = d?.match(/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/);
  if (!m) return null;
  return Number(m[1] ?? 0) * 1440 + Number(m[2] ?? 0) * 60 + Number(m[3] ?? 0);
}

/** Minutes after local midnight from "2026-11-07T10:05:00". */
const clock = (t: string) => Number(t.slice(11, 13)) * 60 + Number(t.slice(14, 16));
const dayDiff = (a: string, b: string) => Math.round((Date.parse(b.slice(0, 10)) - Date.parse(a.slice(0, 10))) / 86_400_000);

const PALETTE = ["#1c54f0", "#0f766e", "#7c3aed", "#f06c06", "#be123c", "#0369a1", "#15803d", "#a16207"];
const colorFor = (code: string) => PALETTE[[...code].reduce((n, c) => n + c.charCodeAt(0), 0) % PALETTE.length];

function toLeg(slice: DSlice): FlightLeg {
  const first = slice.segments[0];
  const last = slice.segments[slice.segments.length - 1];
  const departMinutes = clock(first.departing_at);
  const arriveMinutes = clock(last.arriving_at);
  const arriveDayOffset = dayDiff(first.departing_at, last.arriving_at);
  const stopCities = slice.segments.slice(0, -1).map((s) => s.destination.iata_code ?? s.destination.name);
  const technicalStops = slice.segments.reduce((n, s) => n + (s.stops?.length ?? 0), 0);
  return {
    from: slice.origin.iata_code ?? first.origin.iata_code ?? "",
    to: slice.destination.iata_code ?? last.destination.iata_code ?? "",
    date: first.departing_at.slice(0, 10),
    departMinutes,
    arriveMinutes,
    arriveDayOffset,
    durationMinutes: isoMinutes(slice.duration) ?? arriveDayOffset * 1440 + arriveMinutes - departMinutes,
    stops: slice.segments.length - 1 + technicalStops,
    stopCities,
    flightNumber: `${first.marketing_carrier.iata_code ?? ""}${first.marketing_carrier_flight_number}`,
  };
}

export type LiveFlightOffer = {
  offer: FlightOffer;
  cost: SupplierCost;
  adults: number;
  children: number;
  title: { from: string; to: string };
};

function mapOffer(o: DOffer): LiveFlightOffer | null {
  const net = Number(o.total_amount);
  const netUsd = toUsd(net, o.total_currency);
  if (netUsd === null || !o.slices.length) return null;
  const markupRate = flightMarkup();
  const total = sellPrice(netUsd, markupRate);
  const adults = o.passengers.filter((p) => p.type === "adult" || (p.age ?? 99) >= 12).length;
  const children = o.passengers.length - adults;
  const code = o.owner.iata_code ?? "";

  return {
    offer: {
      id: o.id,
      airline: { code, name: o.owner.name, color: colorFor(code || o.owner.name), logoUrl: o.owner.logo_symbol_url },
      outbound: toLeg(o.slices[0]),
      inbound: o.slices[1] ? toLeg(o.slices[1]) : undefined,
      pricePerPerson: Math.ceil(total / Math.max(1, o.passengers.length)),
      totalPrice: total,
      refundable: o.conditions?.refund_before_departure?.allowed ?? false,
    },
    cost: { provider: "duffel", offerId: o.id, netAmount: net, netCurrency: o.total_currency, netUsd, markupRate },
    adults,
    children,
    title: {
      from: o.slices[0].origin.city_name ?? o.slices[0].origin.name,
      to: o.slices[0].destination.city_name ?? o.slices[0].destination.name,
    },
  };
}

export async function searchDuffel(search: FlightSearch): Promise<FlightOffer[]> {
  const slices = [{ origin: search.from.code, destination: search.to.code, departure_date: search.depart }];
  if (search.returnDate) {
    slices.push({ origin: search.to.code, destination: search.from.code, departure_date: search.returnDate });
  }
  const passengers = [
    ...Array.from({ length: search.adults }, () => ({ type: "adult" })),
    // The search form doesn't ask children's ages; 8 is a typical child fare age.
    ...Array.from({ length: search.children }, () => ({ age: 8 })),
  ];
  const request = await duffel<{ offers: DOffer[] }>(
    "POST",
    "/air/offer_requests?return_offers=true&supplier_timeout=20000",
    { slices, passengers, cabin_class: CABIN[search.cabin] },
  );
  return request.offers
    .map(mapOffer)
    .filter((o): o is LiveFlightOffer => o !== null)
    .map((o) => o.offer)
    .sort((a, b) => a.totalPrice - b.totalPrice)
    .slice(0, 60);
}

/** Latest price for one offer, or null if it expired or no longer exists. */
export async function getDuffelOffer(offerId: string): Promise<LiveFlightOffer | null> {
  if (!/^off_[A-Za-z0-9]+$/.test(offerId)) return null;
  try {
    const o = await duffel<DOffer>("GET", `/air/offers/${offerId}`);
    if (Date.parse(o.expires_at) < Date.now()) return null;
    return mapOffer(o);
  } catch (err) {
    console.error(`[duffel] Could not fetch offer ${offerId}:`, err);
    return null;
  }
}
