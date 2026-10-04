import "server-only";
import { countryCode } from "../../airports";
import type { Amenity, Hotel, HotelSearch } from "../../hotels";
import { hotelMarkup, sellPrice, type SupplierCost, toUsd } from "../pricing";

/** LiteAPI hotels (https://docs.liteapi.travel). LITEAPI_API_BASE exists only for tests. */
const BASE = process.env.LITEAPI_API_BASE ?? "https://api.liteapi.travel/v3.0";

export function liteApiEnabled() {
  return !!process.env.LITEAPI_KEY;
}

// --- The subset of LiteAPI's responses we use (field names as in liteapi-node-sdk docs) ---
type LHotel = {
  id: string;
  name: string;
  address?: string;
  main_photo?: string;
  stars?: number;
  rating?: number;
  reviewCount?: number;
};
type LAmount = { amount: number; currency: string };
type LRate = {
  name: string;
  boardName?: string;
  retailRate: { total: LAmount[]; suggestedSellingPrice?: LAmount[] };
  cancellationPolicies?: { refundableTag?: string };
};
type LRoomType = { offerId: string; rates: LRate[] };
type LHotelRates = { hotelId: string; roomTypes: LRoomType[] };

async function lite<T>(method: "GET" | "POST", path: string, body?: unknown): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: { "X-API-Key": process.env.LITEAPI_KEY!, accept: "application/json", "content-type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
    cache: "no-store",
    signal: AbortSignal.timeout(30_000),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`LiteAPI ${res.status}: ${json?.error?.message ?? json?.error ?? "request failed"}`);
  return json as T;
}

// Hotel lists change rarely — cache them per city for 12 hours.
const cityCache = new Map<string, { at: number; hotels: LHotel[] }>();
const CACHE_MS = 12 * 60 * 60 * 1000;

const plain = (s: string) => s.normalize("NFD").replace(/[̀-ͯ]/g, "");

async function hotelsInCity(search: HotelSearch): Promise<LHotel[]> {
  const key = search.city.code;
  const hit = cityCache.get(key);
  if (hit && Date.now() - hit.at < CACHE_MS) return hit.hotels;

  const params = new URLSearchParams({ countryCode: countryCode(search.city), cityName: plain(search.city.city), limit: "60" });
  const { data } = await lite<{ data: LHotel[] }>("GET", `/data/hotels?${params}`);
  cityCache.set(key, { at: Date.now(), hotels: data ?? [] });
  return data ?? [];
}

/** Split guests over rooms the way the search form describes them. */
function occupancies(search: HotelSearch) {
  return Array.from({ length: search.rooms }, (_, i) => ({
    adults: Math.floor(search.adults / search.rooms) + (i < search.adults % search.rooms ? 1 : 0),
    // The form doesn't ask children's ages; assume 8.
    children: Array.from(
      { length: Math.floor(search.children / search.rooms) + (i < search.children % search.rooms ? 1 : 0) },
      () => 8,
    ),
  }));
}

async function rates(hotelIds: string[], search: HotelSearch) {
  const { data } = await lite<{ data: LHotelRates[] }>("POST", "/hotels/rates", {
    hotelIds,
    checkin: search.checkIn,
    checkout: search.checkOut,
    currency: "USD",
    guestNationality: "US",
    occupancies: occupancies(search),
    timeout: 12,
  });
  return data ?? [];
}

const sum = (xs: number[]) => xs.reduce((a, b) => a + b, 0);
const first = (xs?: LAmount[]) => xs?.[0];

/** Cheapest room offer for a hotel: price for the whole stay (all rooms, taxes included). */
function cheapest(h: LHotelRates) {
  let best: { room: LRoomType; net: number; currency: string; floor: number } | null = null;
  for (const room of h.roomTypes ?? []) {
    const totals = room.rates.map((r) => first(r.retailRate.total));
    if (!totals.length || totals.some((t) => !t)) continue;
    const net = sum(totals.map((t) => t!.amount));
    const floor = sum(room.rates.map((r) => first(r.retailRate.suggestedSellingPrice)?.amount ?? 0));
    if (!best || net < best.net) best = { room, net, currency: totals[0]!.currency, floor };
  }
  return best;
}

const GRADIENTS = ["from-sky-400 to-blue-700", "from-emerald-400 to-teal-700", "from-amber-300 to-orange-600", "from-rose-400 to-purple-700"];

export type LiveHotel = { hotel: Hotel; cost: SupplierCost };

function mapHotel(info: LHotel, r: LHotelRates, search: HotelSearch, i: number): LiveHotel | null {
  const pick = cheapest(r);
  if (!pick) return null;
  const netUsd = toUsd(pick.net, pick.currency);
  if (netUsd === null) return null;
  const markupRate = hotelMarkup();
  // Never sell below the hotel's suggested selling price (rate-parity rules).
  const stayTotal = Math.max(sellPrice(netUsd, markupRate), Math.ceil(toUsd(pick.floor, pick.currency) ?? 0));
  const rate = pick.room.rates[0];
  const amenities: Amenity[] = /breakfast/i.test(rate.boardName ?? "") ? ["breakfast"] : [];

  return {
    hotel: {
      id: r.hotelId,
      name: info.name,
      stars: Math.round(info.stars ?? 0),
      rating: info.rating || undefined,
      reviews: info.reviewCount || undefined,
      neighborhood: info.address ?? search.city.city,
      roomType: rate.name,
      nightlyPrice: Math.ceil(stayTotal / search.nights / search.rooms),
      stayTotal,
      amenities,
      freeCancellation: rate.cancellationPolicies?.refundableTag === "RFN",
      gradient: GRADIENTS[i % GRADIENTS.length],
      photoUrl: info.main_photo || undefined,
    },
    cost: {
      provider: "liteapi",
      offerId: pick.room.offerId,
      netAmount: pick.net,
      netCurrency: pick.currency,
      netUsd,
      markupRate,
    },
  };
}

export async function searchLiteApi(search: HotelSearch): Promise<Hotel[]> {
  const list = await hotelsInCity(search);
  if (!list.length) return [];
  const byId = new Map(list.map((h) => [h.id, h]));
  const available = await rates(list.map((h) => h.id), search);
  return available
    .map((r, i) => (byId.has(r.hotelId) ? mapHotel(byId.get(r.hotelId)!, r, search, i) : null))
    .filter((h): h is LiveHotel => h !== null)
    .map((h) => h.hotel);
}

/** Fresh price for one hotel, or null if it's no longer available. */
export async function getLiteApiHotel(search: HotelSearch, hotelId: string): Promise<LiveHotel | null> {
  if (!/^[A-Za-z0-9_-]{1,40}$/.test(hotelId)) return null;
  try {
    const info = (await hotelsInCity(search)).find((h) => h.id === hotelId);
    if (!info) return null;
    const [r] = await rates([hotelId], search);
    return r ? mapHotel(info, r, search, 0) : null;
  } catch (err) {
    console.error(`[liteapi] Could not price hotel ${hotelId}:`, err);
    return null;
  }
}
