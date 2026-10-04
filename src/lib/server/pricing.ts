import "server-only";

function rate(name: string, fallback: number) {
  const v = Number(process.env[name]);
  return process.env[name] !== undefined && Number.isFinite(v) && v >= 0 && v <= 3 ? v : fallback;
}

/** Your margin on top of the supplier's price (0.2 = +20%). Change via env without touching code. */
export const flightMarkup = () => rate("FLIGHT_MARKUP_RATE", 0.2);
export const hotelMarkup = () => rate("HOTEL_MARKUP_RATE", 0.2);

/** Discount for signed-in members on every booking (0.1 = 10%). Set to 0 to turn it off. */
export const memberDiscountRate = () => rate("MEMBER_DISCOUNT_RATE", 0.1);

/** Customer price in whole dollars, always rounded up so the margin never drops below the rate. */
export function sellPrice(netUsd: number, markup: number) {
  return Math.ceil(netUsd * (1 + markup));
}

/**
 * Approximate rates for suppliers that quote in another currency. The site sells in USD.
 * Override with FX_RATES_TO_USD='{"EUR":1.09,"GBP":1.28}' — keep these current, or ask your
 * supplier to quote in USD, because a stale rate eats into your margin.
 */
const DEFAULT_FX: Record<string, number> = { USD: 1, EUR: 1.08, GBP: 1.27, CAD: 0.73, AUD: 0.66, SGD: 0.75, PHP: 0.0175, JPY: 0.0067 };

function fxTable(): Record<string, number> {
  try {
    return { ...DEFAULT_FX, ...JSON.parse(process.env.FX_RATES_TO_USD ?? "{}") };
  } catch {
    return DEFAULT_FX;
  }
}

/** Converts to USD, or null when the currency is unknown (the offer is then skipped). */
export function toUsd(amount: number, currency: string) {
  const r = fxTable()[currency.toUpperCase()];
  return r ? amount * r : null;
}

/** What we pay the supplier — kept on the booking for the admin's cost/profit view. */
export type SupplierCost = {
  provider: "duffel" | "liteapi";
  offerId: string;
  netAmount: number;
  netCurrency: string;
  netUsd: number;
  markupRate: number;
};
