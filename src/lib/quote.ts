import { findAirport } from "./airports";
import {
  type Airline,
  CABIN_LABELS,
  childPrice,
  type FlightLeg,
  parseFlightParams,
  searchFlights,
} from "./flights";
import { addDays, formatDate } from "./format";
import { PROMO_PACKAGES } from "./packages";
import { dateParam, earliestDate, int, type RawParams, str } from "./search-params";
import { MEMBER_DISCOUNT_RATE } from "./site";

export type BookingKind = "flight" | "package";

export const BOOKING_KINDS: BookingKind[] = ["flight", "package"];

export type TravelerSlot = { label: string; needsDob: boolean };

export type Quote = {
  kind: BookingKind;
  title: string;
  subtitle: string;
  startDate: string;
  endDate?: string;
  travelerSlots: TravelerSlot[];
  lines: { label: string; amount: number }[];
  subtotal: number;
  discount: number;
  total: number;
  /** Key facts shown on the booking summary and trip page. */
  facts: { label: string; value: string }[];
  flight?: { airline: Airline; outbound: FlightLeg; inbound?: FlightLeg };
  /** Canonical query string that rebuilds this exact quote. */
  query: string;
};

function finish(q: Omit<Quote, "subtotal" | "discount" | "total">): Quote {
  const subtotal = q.lines.reduce((sum, l) => sum + l.amount, 0);
  const discount = Math.round(subtotal * MEMBER_DISCOUNT_RATE);
  return { ...q, subtotal, discount, total: subtotal - discount };
}

function slots(adults: number, children: number): TravelerSlot[] {
  return [
    ...Array.from({ length: adults }, (_, i) => ({ label: `Adult ${i + 1}`, needsDob: true })),
    ...Array.from({ length: children }, (_, i) => ({ label: `Child ${i + 1}`, needsDob: true })),
  ];
}

function flightQuote(params: RawParams): Quote | null {
  const parsed = parseFlightParams(params);
  if (!parsed.search) return null;
  const offerId = str(params.offer);
  const offer = searchFlights(parsed.search).find((o) => o.id === offerId);
  if (!offer) return null;

  const { from, to, adults, children, cabin, returnDate } = parsed.search;
  const lines = [{ label: `${adults} × adult fare`, amount: offer.pricePerPerson * adults }];
  if (children) {
    lines.push({ label: `${children} × child fare`, amount: childPrice(offer.pricePerPerson) * children });
  }

  return finish({
    kind: "flight",
    title: `${from.city} → ${to.city}`,
    subtitle: `${offer.airline.name} · ${returnDate ? "Round trip" : "One way"} · ${CABIN_LABELS[cabin]}`,
    startDate: offer.outbound.date,
    endDate: offer.inbound?.date,
    travelerSlots: slots(adults, children),
    lines,
    facts: [
      { label: "Depart", value: formatDate(offer.outbound.date) },
      ...(offer.inbound ? [{ label: "Return", value: formatDate(offer.inbound.date) }] : []),
      { label: "Travelers", value: String(adults + children) },
      { label: "Cabin", value: CABIN_LABELS[cabin] },
      { label: "Fare", value: offer.refundable ? "Refundable" : "Non-refundable" },
    ],
    flight: { airline: offer.airline, outbound: offer.outbound, inbound: offer.inbound },
    query: `${parsed.query}&offer=${encodeURIComponent(offer.id)}`,
  });
}

function packageQuote(params: RawParams): Quote | null {
  const pkg = PROMO_PACKAGES.find((p) => p.id === str(params.id));
  if (!pkg) return null;

  const fromParam = findAirport(str(params.from));
  const from = fromParam && fromParam.code !== pkg.destinationCode ? fromParam : findAirport("JFK")!;
  const min = earliestDate();
  const depart = dateParam(str(params.depart), min) ?? addDays(min, 22);
  const returnDate = addDays(depart, pkg.nights);
  const adults = int(str(params.adults), 2, 1, 6);

  const includes = ["Round-trip flights", "Hotel", ...(pkg.includesCar ? ["Rental car"] : [])];

  return finish({
    kind: "package",
    title: pkg.title,
    subtitle: `${from.city} → ${pkg.destination} · ${pkg.nights} nights · ${pkg.hotel}`,
    startDate: depart,
    endDate: returnDate,
    travelerSlots: slots(adults, 0),
    lines: [{ label: `${adults} × package price`, amount: pkg.price * adults }],
    facts: [
      { label: "Leaving from", value: `${from.city} (${from.code})` },
      { label: "Dates", value: `${formatDate(depart)} – ${formatDate(returnDate)}` },
      { label: "Hotel", value: `${pkg.hotel} (${pkg.hotelStars}★)` },
      { label: "Includes", value: includes.join(", ") },
      { label: "Travelers", value: String(adults) },
    ],
    query: new URLSearchParams({ id: pkg.id, from: from.code, depart, adults: String(adults) }).toString(),
  });
}

/** Build a price quote from URL params. Always recomputed on the server — never trust a client price. */
export function buildQuote(kind: string, params: RawParams): Quote | null {
  if (kind === "flight") return flightQuote(params);
  if (kind === "package") return packageQuote(params);
  return null;
}
