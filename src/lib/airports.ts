export type Airport = {
  code: string;
  city: string;
  country: string;
  name: string;
  lat: number;
  lon: number;
};

export const AIRPORTS: Airport[] = [
  { code: "JFK", city: "New York", country: "United States", name: "John F. Kennedy Intl", lat: 40.64, lon: -73.78 },
  { code: "LAX", city: "Los Angeles", country: "United States", name: "Los Angeles Intl", lat: 33.94, lon: -118.41 },
  { code: "ORD", city: "Chicago", country: "United States", name: "O'Hare Intl", lat: 41.98, lon: -87.9 },
  { code: "MIA", city: "Miami", country: "United States", name: "Miami Intl", lat: 25.79, lon: -80.29 },
  { code: "SFO", city: "San Francisco", country: "United States", name: "San Francisco Intl", lat: 37.62, lon: -122.38 },
  { code: "LAS", city: "Las Vegas", country: "United States", name: "Harry Reid Intl", lat: 36.08, lon: -115.15 },
  { code: "MCO", city: "Orlando", country: "United States", name: "Orlando Intl", lat: 28.43, lon: -81.31 },
  { code: "HNL", city: "Honolulu", country: "United States", name: "Daniel K. Inouye Intl", lat: 21.32, lon: -157.92 },
  { code: "YYZ", city: "Toronto", country: "Canada", name: "Toronto Pearson Intl", lat: 43.68, lon: -79.63 },
  { code: "CUN", city: "Cancún", country: "Mexico", name: "Cancún Intl", lat: 21.04, lon: -86.87 },
  { code: "LHR", city: "London", country: "United Kingdom", name: "Heathrow", lat: 51.47, lon: -0.45 },
  { code: "CDG", city: "Paris", country: "France", name: "Charles de Gaulle", lat: 49.01, lon: 2.55 },
  { code: "FCO", city: "Rome", country: "Italy", name: "Leonardo da Vinci–Fiumicino", lat: 41.8, lon: 12.25 },
  { code: "BCN", city: "Barcelona", country: "Spain", name: "Barcelona–El Prat", lat: 41.3, lon: 2.08 },
  { code: "AMS", city: "Amsterdam", country: "Netherlands", name: "Schiphol", lat: 52.31, lon: 4.76 },
  { code: "IST", city: "Istanbul", country: "Türkiye", name: "Istanbul Airport", lat: 41.26, lon: 28.74 },
  { code: "DXB", city: "Dubai", country: "United Arab Emirates", name: "Dubai Intl", lat: 25.25, lon: 55.36 },
  { code: "DOH", city: "Doha", country: "Qatar", name: "Hamad Intl", lat: 25.27, lon: 51.61 },
  { code: "SIN", city: "Singapore", country: "Singapore", name: "Changi", lat: 1.36, lon: 103.99 },
  { code: "BKK", city: "Bangkok", country: "Thailand", name: "Suvarnabhumi", lat: 13.69, lon: 100.75 },
  { code: "DPS", city: "Bali", country: "Indonesia", name: "Ngurah Rai Intl", lat: -8.75, lon: 115.17 },
  { code: "KUL", city: "Kuala Lumpur", country: "Malaysia", name: "Kuala Lumpur Intl", lat: 2.74, lon: 101.7 },
  { code: "MNL", city: "Manila", country: "Philippines", name: "Ninoy Aquino Intl", lat: 14.51, lon: 121.02 },
  { code: "CEB", city: "Cebu", country: "Philippines", name: "Mactan–Cebu Intl", lat: 10.31, lon: 123.98 },
  { code: "HKG", city: "Hong Kong", country: "Hong Kong", name: "Hong Kong Intl", lat: 22.31, lon: 113.91 },
  { code: "NRT", city: "Tokyo", country: "Japan", name: "Narita Intl", lat: 35.77, lon: 140.39 },
  { code: "ICN", city: "Seoul", country: "South Korea", name: "Incheon Intl", lat: 37.46, lon: 126.44 },
  { code: "DEL", city: "New Delhi", country: "India", name: "Indira Gandhi Intl", lat: 28.56, lon: 77.1 },
  { code: "SYD", city: "Sydney", country: "Australia", name: "Kingsford Smith", lat: -33.94, lon: 151.18 },
  { code: "GRU", city: "São Paulo", country: "Brazil", name: "Guarulhos Intl", lat: -23.43, lon: -46.47 },
];

export function findAirport(code: string | undefined | null) {
  if (!code) return undefined;
  const upper = code.trim().toUpperCase();
  return AIRPORTS.find((a) => a.code === upper);
}

export function searchAirports(query: string, limit = 8) {
  const q = query.trim().toLowerCase();
  if (!q) return AIRPORTS.slice(0, limit);
  return AIRPORTS.filter(
    (a) =>
      a.code.toLowerCase().startsWith(q) ||
      a.city.toLowerCase().includes(q) ||
      a.country.toLowerCase().includes(q) ||
      a.name.toLowerCase().includes(q),
  ).slice(0, limit);
}

/** Great-circle distance in km. */
export function distanceKm(a: Airport, b: Airport) {
  const R = 6371;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLon = toRad(b.lon - a.lon);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}
