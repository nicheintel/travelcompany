import { type Airport, distanceKm } from "./airports";

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
};

export type FlightOffer = {
  id: string;
  airline: Airline;
  outbound: FlightLeg;
  inbound?: FlightLeg;
  pricePerPerson: number;
  totalPrice: number;
  seatsLeft: number;
  refundable: boolean;
};

export type Airline = { code: string; name: string; color: string };

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

/** Small deterministic PRNG so the same search always shows the same results. */
function seeded(seedText: string) {
  let h = 1779033703 ^ seedText.length;
  for (let i = 0; i < seedText.length; i++) {
    h = Math.imul(h ^ seedText.charCodeAt(i), 3432918353);
    h = (h << 13) | (h >>> 19);
  }
  return () => {
    h = Math.imul(h ^ (h >>> 16), 2246822507);
    h = Math.imul(h ^ (h >>> 13), 3266489909);
    h ^= h >>> 16;
    return (h >>> 0) / 4294967296;
  };
}

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

export function searchFlights(search: FlightSearch): FlightOffer[] {
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
    const childPrice = Math.round(pricePerPerson * 0.75);

    offers.push({
      id: `${airline.code}-${i}`,
      airline,
      outbound,
      inbound,
      pricePerPerson,
      totalPrice: pricePerPerson * adults + childPrice * children,
      seatsLeft: 1 + Math.floor(rand() * 9),
      refundable: rand() > 0.6,
    });
  }
  return offers.sort((a, b) => a.totalPrice - b.totalPrice);
}
