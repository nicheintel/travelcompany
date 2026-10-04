import { type Airport, findAirport } from "./airports";
import { addDays } from "./format";
import { seeded } from "./random";
import { dateParam, earliestDate, int, type RawParams, str } from "./search-params";

export type Amenity = "wifi" | "pool" | "breakfast" | "gym" | "spa" | "parking" | "shuttle";

export const AMENITY_LABELS: Record<Amenity, string> = {
  wifi: "Free Wi-Fi",
  pool: "Pool",
  breakfast: "Breakfast included",
  gym: "Fitness center",
  spa: "Spa",
  parking: "Free parking",
  shuttle: "Airport shuttle",
};

export type Hotel = {
  id: string;
  name: string;
  /** 0 when unknown. */
  stars: number;
  /** Guest score out of 10, when known. */
  rating?: number;
  reviews?: number;
  neighborhood: string;
  distanceKm?: number;
  roomType: string;
  nightlyPrice: number;
  originalNightly?: number;
  amenities: Amenity[];
  freeCancellation: boolean;
  gradient: string;
  photoUrl?: string;
  /** Live rates: exact price for the whole stay (all rooms), taxes included. */
  stayTotal?: number;
};

/** Rough hotel price level per city (1 = average). */
const CITY_COST: Record<string, number> = {
  JFK: 1.6, SFO: 1.5, LHR: 1.5, HNL: 1.4, CDG: 1.4, NRT: 1.3, DXB: 1.3, SIN: 1.3, AMS: 1.3,
  MIA: 1.2, LAX: 1.2, SYD: 1.2, FCO: 1.1, BCN: 1.1, ICN: 1.0, HKG: 1.2, DOH: 1.1, ORD: 1.1,
  CUN: 0.9, IST: 0.7, GRU: 0.7, KUL: 0.6, BKK: 0.6, DPS: 0.6, MNL: 0.6, CEB: 0.6, DEL: 0.5,
};

const NAME_A = ["Grand", "Royal", "Harbor", "Garden", "Skyline", "Riverside", "Central", "Palm", "Urban", "Heritage", "Azure", "Lotus"];
const NAME_B = ["Hotel", "Suites", "Resort", "Inn", "Residences", "Boutique Hotel", "Plaza", "Lodge"];
const AREAS = ["City Center", "Old Town", "Waterfront", "Business District", "Arts Quarter", "Near the Airport", "Beachfront", "Shopping District"];
const ROOMS = ["Standard Double Room", "Deluxe King Room", "Superior Twin Room", "Junior Suite", "Family Room"];
const GRADIENTS = [
  "from-sky-400 to-blue-700",
  "from-emerald-400 to-teal-700",
  "from-amber-300 to-orange-600",
  "from-rose-400 to-purple-700",
  "from-indigo-400 to-slate-800",
  "from-cyan-300 to-sky-700",
];
const STAR_MULTIPLIER = [0, 0.45, 0.6, 1, 1.6, 2.8];

export type HotelSearch = {
  city: Airport;
  checkIn: string;
  checkOut: string;
  nights: number;
  adults: number;
  children: number;
  rooms: number;
};

/** Deterministic sample hotels — used until a LiteAPI key is configured. */
export function sampleHotels(search: HotelSearch): Hotel[] {
  const { city, checkIn } = search;
  // Seeded by city so a hotel keeps its identity; prices also vary with the date.
  const rand = seeded(`hotels-${city.code}`);
  const dateRand = seeded(`hotels-${city.code}-${checkIn}`);
  const cost = CITY_COST[city.code] ?? 1;
  const count = 14 + Math.floor(rand() * 5);
  const used = new Set<string>();

  return Array.from({ length: count }, (_, i) => {
    let name: string;
    do {
      const a = NAME_A[Math.floor(rand() * NAME_A.length)];
      const b = NAME_B[Math.floor(rand() * NAME_B.length)];
      name = rand() < 0.5 ? `${a} ${b} ${city.city}` : `The ${a} ${b}`;
    } while (used.has(name));
    used.add(name);

    const starRoll = rand();
    const stars = starRoll < 0.1 ? 2 : starRoll < 0.4 ? 3 : starRoll < 0.8 ? 4 : 5;
    const rating = Math.round(Math.min(9.8, 6.4 + stars * 0.45 + rand() * 1.6) * 10) / 10;
    const base = (55 + rand() * 70) * STAR_MULTIPLIER[stars] * cost;
    const nightlyPrice = Math.round(base * (0.85 + dateRand() * 0.3));
    const onSale = rand() < 0.35;

    const amenities = (Object.keys(AMENITY_LABELS) as Amenity[]).filter((a) => {
      if (a === "wifi") return rand() < 0.95;
      if (a === "pool" || a === "spa") return rand() < 0.15 + stars * 0.12;
      return rand() < 0.4;
    });

    return {
      id: `${city.code}-${i}`,
      name,
      stars,
      rating,
      reviews: 80 + Math.floor(rand() * 4200),
      neighborhood: AREAS[Math.floor(rand() * AREAS.length)],
      distanceKm: Math.round((0.2 + rand() * 9) * 10) / 10,
      roomType: ROOMS[Math.floor(rand() * ROOMS.length)],
      nightlyPrice,
      originalNightly: onSale ? Math.round(nightlyPrice * (1.15 + rand() * 0.3)) : undefined,
      amenities,
      freeCancellation: rand() < 0.6,
      gradient: GRADIENTS[i % GRADIENTS.length],
    };
  });
}

export function ratingLabel(rating: number) {
  if (rating >= 9) return "Exceptional";
  if (rating >= 8.5) return "Excellent";
  if (rating >= 8) return "Very good";
  if (rating >= 7) return "Good";
  return "Pleasant";
}

export type ParsedHotelSearch = {
  city?: Airport;
  checkIn: string;
  checkOut: string;
  adults: number;
  children: number;
  rooms: number;
  search: HotelSearch | null;
  query: string;
};

const MAX_NIGHTS = 30;

export function parseHotelParams(params: RawParams): ParsedHotelSearch {
  const city = findAirport(str(params.to));
  const min = earliestDate();
  const checkIn = dateParam(str(params.checkin), min) ?? addDays(min, 15);
  let checkOut = dateParam(str(params.checkout), addDays(checkIn, 1)) ?? addDays(checkIn, 3);
  if (checkOut > addDays(checkIn, MAX_NIGHTS)) checkOut = addDays(checkIn, MAX_NIGHTS);
  const nights = Math.round((Date.parse(checkOut) - Date.parse(checkIn)) / 86400000);
  const adults = int(str(params.adults), 2, 1, 9);
  const children = int(str(params.children), 0, 0, 8);
  const rooms = Math.min(int(str(params.rooms), 1, 1, 4), adults);

  const query = new URLSearchParams({
    to: city?.code ?? "",
    checkin: checkIn,
    checkout: checkOut,
    adults: String(adults),
    children: String(children),
    rooms: String(rooms),
  }).toString();

  return {
    city,
    checkIn,
    checkOut,
    adults,
    children,
    rooms,
    search: city ? { city, checkIn, checkOut, nights, adults, children, rooms } : null,
    query,
  };
}

/** Taxes & fees added on top of the room rate. */
export const HOTEL_TAX_RATE = 0.12;
