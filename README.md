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
| `/hotels`    | Hotel search: destination, dates, guests & rooms; results with filters and sorting |
| `/register`  | Create an account (name, email, password)                                  |
| `/signin`    | Email + password sign-in; returns visitors to where they came from (`?next=`) |
| `/forgot-password`, `/reset-password` | Email a single-use reset link (1 hour) and choose a new password |
| `/book/flight`, `/book/hotel`, `/book/package` | Booking: traveler details, contact info, price summary (members only) |
| `/account`   | Signed-in members only: profile and "My trips" (upcoming, past & cancelled) |
| `/account/trips/[ref]` | Trip details, online payment (Stripe) and cancellation          |
| `/account/settings` | Change name, email (needs current password) and password             |
| `/api/stripe/webhook` | Stripe payment notifications                                     |

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
  - `hotels.ts` — **mock** hotel search
  - `quote.ts` — prices a booking from its search params (always recomputed on the server)
  - `packages.ts` — promo package catalog
  - `site.ts` — site name / tagline (change branding here)

> Flight, hotel and package prices are sample data. Swap `searchFlights` / `searchHotels`
> for real APIs (e.g. Amadeus, Duffel, Expedia Rapid) when ready.

## Bookings & payments

A booking is saved with a `TC-XXXXXX` reference and status `reserved`. Then:

- **With Stripe configured**, the trip page shows **Pay now**, which opens Stripe Checkout. The
  booking becomes `paid` when Stripe confirms it — via the webhook, or by checking with Stripe
  when the customer returns. The amount and booking reference must match, and the receipt is
  emailed once.
- **Without Stripe**, it's "reserve now, pay later": a travel assistant contacts the customer.

Reserved (unpaid) trips can be cancelled by the customer; paid trips are changed through support.
Members get a discount set by `MEMBER_DISCOUNT_RATE` in `src/lib/site.ts` (10% by default; set to
`0` to turn it off).

### Emails sent

Trip reserved · payment received · reservation cancelled · password reset · password changed ·
email address changed (sent to the *old* address).

### Setting up Stripe

1. Create an account at [stripe.com](https://stripe.com) and copy the **secret key** (`sk_test_…`
   while testing) into `STRIPE_SECRET_KEY`.
2. In Stripe → Developers → Webhooks, add an endpoint `https://YOUR-SITE/api/stripe/webhook`
   for `checkout.session.completed` and `checkout.session.async_payment_succeeded`, and copy its
   signing secret (`whsec_…`) into `STRIPE_WEBHOOK_SECRET`.
3. Locally, run `stripe listen --forward-to localhost:3000/api/stripe/webhook` with the
   [Stripe CLI](https://docs.stripe.com/stripe-cli) and use the secret it prints.

## Environment variables

| Variable          | Needed for                                                                    |
| ----------------- | ----------------------------------------------------------------------------- |
| `APP_URL`         | **Required in production** — your site address, used in reset-password emails (e.g. `https://www.example.com`) |
| `RESEND_API_KEY`  | Sending email via [Resend](https://resend.com). Without it, development prints emails to the server log |
| `EMAIL_FROM`      | Sender, e.g. `TravelCompany <hello@yourdomain.com>` (domain must be verified in Resend) |
| `STRIPE_SECRET_KEY` | Turns on card payments via Stripe Checkout                                   |
| `STRIPE_WEBHOOK_SECRET` | Verifies Stripe webhook calls (required for the webhook)                  |
| `DATABASE_PATH`   | Optional SQLite file location (default `data/travelcompany.db`)               |

## Accounts & security

- **Database:** SQLite (`better-sqlite3`), created automatically at `data/travelcompany.db`
  (git-ignored). Set `DATABASE_PATH` to change the location.
- **Passwords:** hashed with scrypt (Node built-in); plain passwords are never stored.
- **Sessions:** a random token in an `httpOnly`, `SameSite=Lax` cookie (`Secure` in production),
  valid 30 days. The database only stores a SHA-256 hash of the token.
- **Brute-force protection:** 5 failed sign-ins for an email locks it for 15 minutes; at most
  3 reset emails per address per hour (in-memory; use Redis or a DB table when running more
  than one server).
- **Password reset:** single-use links valid for 1 hour (only a hash is stored); resetting signs
  the user out on all other devices.
- **Redirects:** `?next=` only accepts paths on this site, so it can't send people to other websites.

### Before going live

- SQLite needs a persistent disk. On serverless hosts (e.g. Vercel), move to a hosted database
  such as Postgres — only `src/lib/server/db.ts`, `users.ts` and `session.ts` touch the database.
- Switch Stripe from test keys to live keys, and register the live webhook endpoint.
- Not built yet: email verification at sign-up, refunds from the site, and an admin dashboard
  for travel assistants to see and manage bookings.
