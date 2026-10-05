# TravelCompany

A travel assistant website for finding affordable flights, hotels and Flight + Hotel + Car promo packages.

> **Two versions live in this repository:**
> - **`php/` — PHP + MySQL, runs on XAMPP and any Hostinger plan.** See [`php/README.md`](php/README.md).
> - The root folder — the original Next.js version (needs Node.js), documented below.
>
> **`lamazonloads/`** is a separate website (LamazonLoads freight dispatch & driver network, PHP + MySQL on XAMPP). See [`lamazonloads/README.md`](lamazonloads/README.md).
>
> Both have the same features and design.

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
| `/admin`     | **Staff only.** Overview: unpaid trips that need a call, key numbers, latest bookings |
| `/admin/bookings` | Search and filter all bookings; open one to record a payment, cancel or add notes |
| `/admin/users` | Customers and staff; grant or remove admin access                        |

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

> Flights and hotels use **live prices** once supplier keys are set (see below); without keys the
> site shows clearly-labelled sample data. Promo packages are a fixed catalog in `src/lib/packages.ts`.

## Live prices: Duffel (flights) & LiteAPI (hotels)

1. **Duffel** — sign up at [duffel.com](https://duffel.com), copy a **test** access token
   (`duffel_test_…`) into `DUFFEL_ACCESS_TOKEN`. Test mode returns real-looking fares from
   "Duffel Airways"; switch to a live token when you're ready to sell.
2. **LiteAPI** — sign up at [liteapi.travel](https://www.liteapi.travel), copy your sandbox API key
   into `LITEAPI_KEY`.

How pricing works:

- Customer price = supplier price × (1 + markup), rounded **up** to the dollar.
  `FLIGHT_MARKUP_RATE` and `HOTEL_MARKUP_RATE` (default `0.2` = 20%).
- Then the member discount (`MEMBER_DISCOUNT_RATE`, default `0.1`) comes off the total.
- Hotels are never sold below the hotel's suggested selling price (rate-parity rules).
- Prices are in USD. Flights quoted in other currencies are converted with the rates in
  `src/lib/server/pricing.ts` (override with `FX_RATES_TO_USD`); unknown currencies are skipped.
- The booking page **re-checks the price with the supplier**; if it changed, the customer sees
  the new price before reserving. Expired fares show "no longer available".
- Staff see the supplier, offer ID, cost and gross margin on each booking in the admin dashboard.
- Tickets and rooms are **issued by staff** in the Duffel / LiteAPI dashboards after payment
  (automatic issuing is the next step). Supplier prices can change until then.

Code: `src/lib/server/providers/duffel.ts`, `src/lib/server/providers/liteapi.ts`,
`src/lib/server/travel-search.ts` (live vs sample switch), `src/lib/quote.ts` (booking prices).

## Bookings & payments

A booking is saved with a `TC-XXXXXX` reference and status `reserved`. Then:

- **With Stripe configured**, the trip page shows **Pay now**, which opens Stripe Checkout. The
  booking becomes `paid` when Stripe confirms it — via the webhook, or by checking with Stripe
  when the customer returns. The amount and booking reference must match, and the receipt is
  emailed once.
- **Without Stripe**, it's "reserve now, pay later": a travel assistant contacts the customer.

Reserved (unpaid) trips can be cancelled by the customer; paid trips are changed through support.
Members get a discount set by the `MEMBER_DISCOUNT_RATE` environment variable (10% by default;
set to `0` to turn it off — the landing page offer updates automatically).

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

## Admin dashboard (for travel assistants)

1. Put the owner's email in `ADMIN_EMAILS` (comma-separated for several), then sign up / sign in
   with that email. **Admin dashboard** appears in the user menu.
2. Other staff create a normal account; an admin gives them access from **Admin → Users**.

What staff can do:

- **Overview** — unpaid trips departing within 14 days or reserved over 24 hours ago (the "call
  these people" list), money awaiting payment, 30-day revenue, new members.
- **Bookings** — search by reference, name, email, phone or traveler; filter by status and type.
- **Booking page** — tap-to-call / email the customer, full traveler names and dates of birth,
  **record a payment** taken by phone, bank transfer or cash (customer gets a receipt),
  **cancel** with a reason (customer is emailed), and **internal notes**. Every change is kept in
  the booking's activity log with who did it and when.
- **Users** — see each customer's bookings; make someone an admin or remove access. Admins from
  `ADMIN_EMAILS` can't be removed from the dashboard, and nobody can change their own access.

Non-staff get a "not found" page for anything under `/admin`, and every admin action re-checks
access on the server.

## Environment variables

| Variable          | Needed for                                                                    |
| ----------------- | ----------------------------------------------------------------------------- |
| `APP_URL`         | **Required in production** — your site address, used in reset-password emails (e.g. `https://www.example.com`) |
| `RESEND_API_KEY`  | Sending email via [Resend](https://resend.com). Without it, development prints emails to the server log |
| `EMAIL_FROM`      | Sender, e.g. `TravelCompany <hello@yourdomain.com>` (domain must be verified in Resend) |
| `STRIPE_SECRET_KEY` | Turns on card payments via Stripe Checkout                                   |
| `STRIPE_WEBHOOK_SECRET` | Verifies Stripe webhook calls (required for the webhook)                  |
| `DUFFEL_ACCESS_TOKEN` | Live flight search and prices (Duffel)                                    |
| `LITEAPI_KEY`     | Live hotel search and prices (LiteAPI)                                        |
| `FLIGHT_MARKUP_RATE` / `HOTEL_MARKUP_RATE` | Your margin on supplier prices (default `0.2` = 20%) |
| `MEMBER_DISCOUNT_RATE` | Member discount on bookings (default `0.1` = 10%; `0` = off)            |
| `FX_RATES_TO_USD` | Optional JSON of exchange rates, e.g. `{"EUR":1.09,"GBP":1.28}`               |
| `ADMIN_EMAILS`    | Emails that are always admins, e.g. `owner@yourdomain.com,manager@yourdomain.com` |
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
- Not built yet: email verification at sign-up, and issuing Stripe refunds from the dashboard
  (refund in the Stripe dashboard for now).
