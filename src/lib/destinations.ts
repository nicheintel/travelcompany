export type Destination = {
  city: string;
  country: string;
  code: string;
  fromPrice: number;
  gradient: string;
};

export const POPULAR_DESTINATIONS: Destination[] = [
  { city: "Paris", country: "France", code: "CDG", fromPrice: 389, gradient: "from-rose-400 to-purple-700" },
  { city: "Tokyo", country: "Japan", code: "NRT", fromPrice: 612, gradient: "from-fuchsia-500 to-red-600" },
  { city: "Bali", country: "Indonesia", code: "DPS", fromPrice: 548, gradient: "from-emerald-400 to-cyan-700" },
  { city: "Cancún", country: "Mexico", code: "CUN", fromPrice: 219, gradient: "from-cyan-400 to-blue-700" },
  { city: "Dubai", country: "UAE", code: "DXB", fromPrice: 497, gradient: "from-amber-400 to-orange-700" },
  { city: "London", country: "United Kingdom", code: "LHR", fromPrice: 342, gradient: "from-slate-400 to-indigo-800" },
];
