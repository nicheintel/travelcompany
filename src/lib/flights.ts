import { type Airport, distanceKm, findAirport } from "./airports";
import { seeded } from "./random";
import { addDays } from "./format";
import { dateParam, earliestDate, int, type RawParams, str } from "./search-params";

export type CabinClass = "economy" | "premium" | "business" | "first";

export const CABIN_LABELS: Record<CabinClass, string> = {
  economy: "Economy",
  premium: "Premium Economy",
  business: "Business",
  first: "First",
};

export type FlightLeg = {
  from: string;
  to: string;
  date: string;
  departMinutes: number;
  durationMinutes: number;
  stops: number;
  stopCities: string[];
  flightNumber: string;
  /** Local arrival time; live fares provide it (sample data derives it from the duration). */
  arriveMinutes?: number;
  arriveDayOffset?: number;
};

export type FlightOffer = {
  id: string;
  airline: Airline;
  outbound: FlightLeg;
  inbound?: FlightLeg;
  pricePerPerson: number;
  totalPrice: number;
  seatsLeft?: number;
  refundable: boolean;
};

export type Airline = { code: string; name: string; color: string; logoUrl?: string | null };

export const AIRLINES: Airline[] = [
  { code: "SK", name: "SkyBridge Air", color: "#1c54f0" },
  { code: "PA", name: "Pacific Atlas", color: "#0f766e" },
  { code: "NV", name: "Nova Airways", color: "#7c3aed" },
  { code: "SU", name: "SunJet", color: "#f06c06" },
  { code: "MR", name: "Meridian Airlines", color: "#be123c" },
  { code: "BL", name: "BlueLine Express", color: "#0369a1" },
];

const HUBS = ["DXB", "DOH", "IST", "AMS", "SIN", "HKG", "ORD", "LHR", "ICN"];

const CABIN_MULTIPLIER: Record<CabinClass, number> = {
  economy: 1,
  premium: 1.6,
  business: 3.2,
  first: 5.5,
};

function buildLeg(
  rand: () => number,
  from: Airport,
  to: Airport,
  date: string,
  airline: Airline,
): FlightLeg {
  const km = distanceKm(from, to);
  const longHaul = km > 4000;
  const stopRoll = rand();
  const stops = longHaul
    ? stopRoll < 0.35 ? 0 : stopRoll < 0.85 ? 1 : 2
    : stopRoll < 0.65 ? 0 : 1;
  const stopCities = Array.from({ length: stops }, () => {
    const candidates = HUBS.filter((c) => c !== from.code && c !== to.code);
    return candidates[Math.floor(rand() * candidates.length)];
  });
  const airMinutes = Math.round((km / 820) * 60 + 35);
  const layover = stops * Math.round(60 + rand() * 180);
  const departMinutes = Math.round((5 * 60 + rand() * 17 * 60) / 5) * 5;
  return {
    from: from.code,
    to: to.code,
    date,
    departMinutes,
    durationMinutes: airMinutes + layover,
    stops,
    stopCities,
    flightNumber: `${airline.code}${100 + Math.floor(rand() * 899)}`,
  };
}

export type FlightSearch = {
  from: Airport;
  to: Airport;
  depart: string;
  returnDate?: string;
  adults: number;
  children: number;
  cabin: CabinClass;
};

/** Deterministic sample fares — used until a Duffel key is configured. */
export function sampleFlights(search: FlightSearch): FlightOffer[] {
  const { from, to, depart, returnDate, adults, children, cabin } = search;
  const rand = seeded(`${from.code}-${to.code}-${depart}-${returnDate ?? ""}-${cabin}`);
  const km = distanceKm(from, to);
  const basePrice = 49 + km * 0.085;

  const offers: FlightOffer[] = [];
  const count = 10 + Math.floor(rand() * 6);
  for (let i = 0; i < count; i++) {
    const airline = AIRLINES[Math.floor(rand() * AIRLINES.length)];
    const outbound = buildLeg(rand, from, to, depart, airline);
    const inbound = returnDate ? buildLeg(rand, to, from, returnDate, airline) : undefined;

    // Non-stop flights cost a bit more; each stop gets cheaper.
    const stopFactor = 1.15 - outbound.stops * 0.12;
    const variance = 0.75 + rand() * 0.6;
    let pricePerPerson = basePrice * stopFactor * variance * CABIN_MULTIPLIER[cabin];
    if (inbound) pricePerPerson *= 1.85;
    pricePerPerson = Math.round(pricePerPerson);

    offers.push({
      id: `${airline.code}-${i}`,
      airline,
      outbound,
      inbound,
      pricePerPerson,
      totalPrice: pricePerPerson * adults + childPrice(pricePerPerson) * children,
      seatsLeft: 1 + Math.floor(rand() * 9),
      refundable: rand() > 0.6,
    });
  }
  return offers.sort((a, b) => a.totalPrice - b.totalPrice);
}

export type ParsedFlightSearch = {
  from?: Airport;
  to?: Airport;
  depart: string;
  returnDate?: string;
  trip: "roundtrip" | "oneway";
  adults: number;
  children: number;
  cabin: CabinClass;
  /** Ready-to-search criteria, or null when origin/destination are missing or equal. */
  search: FlightSearch | null;
  /** Normalised query string that reproduces this search. */
  query: string;
};

/** Parse and sanitise flight search URL params. Shared by the results and booking pages. */
export function parseFlightParams(params: RawParams): ParsedFlightSearch {
  const from = findAirport(str(params.from));
  const to = findAirport(str(params.to));
  const min = earliestDate();
  const depart = dateParam(str(params.depart), min) ?? addDays(min, 15);
  const trip = str(params.trip) === "oneway" ? "oneway" : "roundtrip";
  const returnDate =
    trip === "oneway" ? undefined : (dateParam(str(params.return), depart) ?? addDays(depart, 7));
  const adults = int(str(params.adults), 1, 1, 9);
  const children = int(str(params.children), 0, 0, 8);
  const cabinParam = str(params.cabin);
  const cabin: CabinClass =
    cabinParam && cabinParam in CABIN_LABELS ? (cabinParam as CabinClass) : "economy";

  const search =
    from && to && from.code !== to.code
      ? { from, to, depart, returnDate, adults, children, cabin }
      : null;

  const query = new URLSearchParams({
    from: from?.code ?? "",
    to: to?.code ?? "",
    depart,
    ...(returnDate ? { return: returnDate } : {}),
    trip,
    adults: String(adults),
    children: String(children),
    cabin,
  }).toString();

  return { from, to, depart, returnDate, trip, adults, children, cabin, search, query };
}

export function childPrice(pricePerPerson: number) {
  return Math.round(pricePerPerson * 0.75);
}
