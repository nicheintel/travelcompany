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
| `/register`  | Create an account (name, email, password)                                  |
| `/signin`    | Email + password sign-in; returns visitors to where they came from (`?next=`) |
| `/account`   | Signed-in members only: profile and "My trips"                             |

## Project layout

- `src/app/` — routes
- `src/components/` — header, footer, cards, icons
- `src/components/search/` — search forms, airport autocomplete, date/traveler pickers
- `src/app/actions/auth.ts` — register / sign-in / sign-out server actions
- `src/components/auth/` — auth forms
- `src/lib/server/` — server-only code: database, password hashing, sessions, current-user lookup
- `src/lib/` — data and helpers:
  - `airports.ts` — supported airports
  - `flights.ts` — **mock** flight search (deterministic sample results)
  - `packages.ts` — promo package catalog
  - `site.ts` — site name / tagline (change branding here)

> Flight results and package prices are sample data. Swap `searchFlights` in
> `src/lib/flights.ts` for a real flight API (e.g. Amadeus, Duffel, Kiwi) when ready.

## Accounts & security

- **Database:** SQLite (`better-sqlite3`), created automatically at `data/travelcompany.db`
  (git-ignored). Set `DATABASE_PATH` to change the location.
- **Passwords:** hashed with scrypt (Node built-in); plain passwords are never stored.
- **Sessions:** a random token in an `httpOnly`, `SameSite=Lax` cookie (`Secure` in production),
  valid 30 days. The database only stores a SHA-256 hash of the token.
- **Brute-force protection:** 5 failed sign-ins for an email locks it for 15 minutes
  (in-memory; use Redis or a DB table when running more than one server).
- **Redirects:** `?next=` only accepts paths on this site, so it can't send people to other websites.

### Before going live

- SQLite needs a persistent disk. On serverless hosts (e.g. Vercel), move to a hosted database
  such as Postgres — only `src/lib/server/db.ts`, `users.ts` and `session.ts` touch the database.
- Not built yet: email verification, "forgot password", and changing your password or email.
