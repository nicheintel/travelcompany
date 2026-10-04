# TravelCompany

A travel assistant website for finding affordable flights, hotels and Flight + Hotel + Car promo packages.

Built with [Next.js](https://nextjs.org) (App Router), TypeScript and Tailwind CSS.

## Getting started

```bash
npm install
npm run dev
```

Open http://localhost:3000.

## Pages

| Route        | What it is                                                                 |
| ------------ | -------------------------------------------------------------------------- |
| `/`          | Landing page: search widget (Flights / Flight+Hotel+Car / Hotels), promos, destinations, FAQ |
| `/packages`  | Promo packages landing page with package search, category filters and sorting |
| `/flights`   | Flight search: from/to, dates, travelers & cabin; results with sort and filters |
| `/hotels`    | Placeholder (hotel-only search coming soon)                               |
| `/signin`, `/register` | Placeholders — accounts are the next step                       |

## Project layout

- `src/app/` — routes
- `src/components/` — header, footer, cards, icons
- `src/components/search/` — search forms, airport autocomplete, date/traveler pickers
- `src/lib/` — data and helpers:
  - `airports.ts` — supported airports
  - `flights.ts` — **mock** flight search (deterministic sample results)
  - `packages.ts` — promo package catalog
  - `site.ts` — site name / tagline (change branding here)

> Flight results and package prices are sample data. Swap `searchFlights` in
> `src/lib/flights.ts` for a real flight API (e.g. Amadeus, Duffel, Kiwi) when ready.
