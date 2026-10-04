import "server-only";
import { findAirport } from "./airports";
import {
  type Airline,
  CABIN_LABELS,
  childPrice,
  type FlightLeg,
  parseFlightParams,
  sampleFlights,
} from "./flights";
import { addDays, formatDate } from "./format";
import { HOTEL_TAX_RATE, type Hotel, parseHotelParams, sampleHotels } from "./hotels";
import { PROMO_PACKAGES } from "./packages";
import { dateParam, earliestDate, int, type RawParams, str } from "./search-params";
import { memberDiscountRate, type SupplierCost } from "./server/pricing";
import { duffelEnabled, getDuffelOffer } from "./server/providers/duffel";
import { getLiteApiHotel, liteApiEnabled } from "./server/providers/liteapi";

export type BookingKind = "flight" | "package" | "hotel";

export const BOOKING_KINDS: BookingKind[] = ["flight", "package", "hotel"];

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
  discountRate: number;
  /** Shown under the price, e.g. live-fare caveats. */
  note?: string;
  /** Live supplier offer and our cost. Staff-only: never render on customer pages. */
  supplier?: SupplierCost;
};

function finish(q: Omit<Quote, "subtotal" | "discount" | "total" | "discountRate">): Quote {
  const subtotal = q.lines.reduce((sum, l) => sum + l.amount, 0);
  const discountRate = memberDiscountRate();
  const discount = Math.round(subtotal * discountRate);
  return { ...q, subtotal, discount, discountRate, total: subtotal - discount };
}

function slots(adults: number, children: number): TravelerSlot[] {
  return [
    ...Array.from({ length: adults }, (_, i) => ({ label: `Adult ${i + 1}`, needsDob: true })),
    ...Array.from({ length: children }, (_, i) => ({ label: `Child ${i + 1}`, needsDob: true })),
  ];
}

const LIVE_FARE_NOTE =
  "Live airline fare. Fares can change until your ticket is issued — we'll confirm before charging any difference.";

async function flightQuote(params: RawParams): Promise<Quote | null> {
  const parsed = parseFlightParams(params);
  if (!parsed.search) return null;
  const offerId = str(params.offer) ?? "";
  const { cabin, returnDate } = parsed.search;

  if (duffelEnabled()) {
    // Trust the airline offer itself (route, passengers, price) rather than the URL.
    const live = await getDuffelOffer(offerId);
    if (!live) return null;
    const { offer, adults, children, cost, title } = live;
    const people = adults + children;
    return finish({
      kind: "flight",
      title: `${title.from} → ${title.to}`,
      subtitle: `${offer.airline.name} · ${offer.inbound ? "Round trip" : "One way"} · ${CABIN_LABELS[cabin]}`,
      startDate: offer.outbound.date,
      endDate: offer.inbound?.date,
      travelerSlots: slots(adults, children),
      lines: [{ label: `Flight for ${people} traveler${people === 1 ? "" : "s"}`, amount: offer.totalPrice }],
      facts: [
        { label: "Depart", value: formatDate(offer.outbound.date) },
        ...(offer.inbound ? [{ label: "Return", value: formatDate(offer.inbound.date) }] : []),
        { label: "Travelers", value: String(people) },
        { label: "Cabin", value: CABIN_LABELS[cabin] },
        { label: "Fare", value: offer.refundable ? "Refundable" : "Non-refundable" },
      ],
      flight: { airline: offer.airline, outbound: offer.outbound, inbound: offer.inbound },
      query: `${parsed.query}&offer=${encodeURIComponent(offer.id)}`,
      note: LIVE_FARE_NOTE,
      supplier: cost,
    });
  }

  const offer = sampleFlights(parsed.search).find((o) => o.id === offerId);
  if (!offer) return null;
  const { from, to, adults, children } = parsed.search;
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

async function hotelQuote(params: RawParams): Promise<Quote | null> {
  const parsed = parseHotelParams(params);
  if (!parsed.search) return null;
  const hotelId = str(params.hotel) ?? "";

  let hotel: Hotel | undefined;
  let supplier: SupplierCost | undefined;
  if (liteApiEnabled()) {
    const live = await getLiteApiHotel(parsed.search, hotelId);
    if (!live) return null;
    hotel = live.hotel;
    supplier = live.cost;
  } else {
    hotel = sampleHotels(parsed.search).find((h) => h.id === hotelId);
  }
  if (!hotel) return null;

  const { city, checkIn, checkOut, nights, rooms, adults, children } = parsed.search;
  const guests = adults + children;
  const stay = `${nights} night${nights === 1 ? "" : "s"} × ${rooms} room${rooms === 1 ? "" : "s"}`;
  const roomTotal = hotel.nightlyPrice * nights * rooms;
  const lines = hotel.stayTotal
    ? [{ label: `${stay} (taxes included)`, amount: hotel.stayTotal }]
    : [
        { label: stay, amount: roomTotal },
        { label: "Taxes & fees", amount: Math.round(roomTotal * HOTEL_TAX_RATE) },
      ];

  return finish({
    kind: "hotel",
    title: hotel.name,
    subtitle: [`${city.city}, ${city.country}`, hotel.neighborhood, hotel.stars ? `${hotel.stars}★` : ""]
      .filter(Boolean)
      .join(" · "),
    startDate: checkIn,
    endDate: checkOut,
    travelerSlots: [{ label: "Lead guest", needsDob: false }],
    lines,
    facts: [
      { label: "Check-in", value: formatDate(checkIn) },
      { label: "Check-out", value: formatDate(checkOut) },
      { label: "Room", value: `${rooms} × ${hotel.roomType}` },
      { label: "Guests", value: `${guests} (${adults} adult${adults === 1 ? "" : "s"}${children ? `, ${children} child${children === 1 ? "" : "ren"}` : ""})` },
      { label: "Cancellation", value: hotel.freeCancellation ? "Free cancellation" : "Non-refundable" },
    ],
    query: `${parsed.query}&hotel=${encodeURIComponent(hotel.id)}`,
    ...(supplier
      ? { supplier, note: "Live hotel rate. Some cities charge a local tourist tax, payable at the hotel." }
      : {}),
  });
}

/** Build a price quote from URL params. Always recomputed on the server — never trust a client price. */
export async function buildQuote(kind: string, params: RawParams): Promise<Quote | null> {
  if (kind === "flight") return flightQuote(params);
  if (kind === "package") return packageQuote(params);
  if (kind === "hotel") return hotelQuote(params);
  return null;
}
