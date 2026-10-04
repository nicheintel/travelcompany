import "server-only";
import { type FlightOffer, type FlightSearch, sampleFlights } from "../flights";
import { type Hotel, type HotelSearch, sampleHotels } from "../hotels";
import { duffelEnabled, searchDuffel } from "./providers/duffel";
import { liteApiEnabled, searchLiteApi } from "./providers/liteapi";

export type SearchResult<T> = { items: T[]; live: boolean; error?: string };

/** Live Duffel fares when DUFFEL_ACCESS_TOKEN is set, otherwise sample data. */
export async function searchFlights(search: FlightSearch): Promise<SearchResult<FlightOffer>> {
  if (!duffelEnabled()) return { items: sampleFlights(search), live: false };
  try {
    return { items: await searchDuffel(search), live: true };
  } catch (err) {
    console.error("[duffel] search failed:", err);
    return { items: [], live: true, error: "We couldn't load live fares just now. Please try again in a moment." };
  }
}

/** Live LiteAPI hotels when LITEAPI_KEY is set, otherwise sample data. */
export async function searchHotels(search: HotelSearch): Promise<SearchResult<Hotel>> {
  if (!liteApiEnabled()) return { items: sampleHotels(search), live: false };
  try {
    return { items: await searchLiteApi(search), live: true };
  } catch (err) {
    console.error("[liteapi] search failed:", err);
    return { items: [], live: true, error: "We couldn't load live hotel prices just now. Please try again in a moment." };
  }
}
