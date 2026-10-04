# TravelCompany — PHP + MySQL version (runs on XAMPP)

The full website in plain PHP 8.1+ and MySQL/MariaDB. No frameworks, no Composer and no build
step: copy the folder, start XAMPP, open the browser.

## Run it on XAMPP (Windows / Mac)

1. Install [XAMPP](https://www.apachefriends.org) (PHP 8.1 or newer).
2. Copy this `php` folder into XAMPP's `htdocs` folder and rename it to **`travelcompany`**
   (from the zip: make sure you don't end up with `htdocs\travelcompany-xampp\travelcompany` —
   move the inner `travelcompany` folder up so `index.php` is directly inside it):
   - Windows: `C:\xampp\htdocs\travelcompany`
   - Mac: `/Applications/XAMPP/htdocs/travelcompany`
3. In the **XAMPP Control Panel**, start **Apache** and **MySQL**, then open
   **http://localhost/travelcompany**.

**Easiest on Windows:** use the ready-made zip (`travelcompany-windows.zip`, built from this
folder plus `windows/`). Extract it anywhere and double-click `START-TravelCompany.bat` —
it finds XAMPP, copies the site into `htdocs`, starts Apache and MySQL and opens the browser.

The database `travelcompany` and its tables are created automatically on the first visit
(using XAMPP's default `root` user with no password). You can also import `database.sql`
in phpMyAdmin if you prefer.

## Settings (API keys, admin, email, payments)

Copy `config.local.example.php` to **`config.local.php`** and fill in what you use:

| Setting | What it does |
| --- | --- |
| `admin_emails` | Your email(s). Sign up with that email and **Admin dashboard** appears in the user menu. |
| `duffel_access_token` | Real flights from Duffel. Without it, flight search says "not available" — the site never invents flights. Test tokens (`duffel_test_…`) only return Duffel's pretend airline "Duffel Airways"; real airlines appear after **Go live** in Duffel. |
| `liteapi_key` | Real hotels from LiteAPI (sandbox key while testing; production key to sell). |
| `demo_mode` | `true` shows clearly-marked **made-up** sample flights and hotels when no key is set — for demos only, never for customers. Default `false`. |
| `flight_markup_rate` / `hotel_markup_rate` | Your margin on supplier prices (default `0.20` = +20%). |
| `member_discount_rate` | Discount for signed-in members (default `0.10`; `0` turns it off). |
| `resend_api_key`, `email_from` | Send real emails via [Resend](https://resend.com). Without it, emails are written to `storage/emails.log` (handy for password-reset links on XAMPP). |
| `stripe_secret_key`, `stripe_webhook_secret` | Card payments with Stripe Checkout. Webhook URL: `https://YOUR-SITE/stripe-webhook.php`. |
| `db_*` | Database login, if not XAMPP's defaults. |
| `app_url` | **Set this on a live server**, e.g. `https://www.yourdomain.com`. |

`config.local.php` is ignored by git, so your keys never end up on GitHub. On hosting that
supports environment variables you can use the same names in upper case instead
(e.g. `DUFFEL_ACCESS_TOKEN`).

## Upload to Hostinger (any plan, including the cheapest)

1. hPanel → **Databases → MySQL Databases**: create a database and user; note the names and password.
2. Upload the **contents** of this folder to `public_html` (File Manager or FTP).
3. Create `config.local.php` there with `db_name`, `db_user`, `db_pass`, `db_host` (usually
   `localhost`), `app_url` (your domain with `https://`) and your keys.
4. Visit your domain — the tables are created automatically.

## Pages

| File | Page |
| --- | --- |
| `index.php` | Landing page with Flights / Flight+Hotel+Car / Hotels search |
| `flights.php`, `hotels.php`, `packages.php` | Search results with filters and sorting; promo packages |
| `book.php` | Booking: traveler details, contact info, price summary (signed-in members) |
| `account.php`, `trip.php`, `settings.php` | My trips, trip details (pay / cancel), account settings |
| `register.php`, `signin.php`, `forgot-password.php`, `reset-password.php` | Accounts |
| `admin/` | Staff dashboard: needs-a-call list, bookings, record payments, cancel, notes, users |
| `stripe-webhook.php` | Stripe payment notifications |

Code lives in `includes/` (blocked from the web by `.htaccess`): `search.php` (Duffel, LiteAPI,
pricing), `quote.php` (booking prices, always recomputed on the server), `bookings.php`,
`auth.php`, `email.php`, `payments.php`, `ui.php` (shared HTML pieces).

## Security built in

Passwords hashed with `password_hash`; CSRF tokens on every form; prepared SQL statements;
output escaping; session cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` on https); sign-in
lockout after 5 wrong passwords (15 min); single-use, hashed password-reset links (1 hour);
changing the password signs out other devices; booking prices re-checked with the supplier;
Stripe webhooks signature-verified and payments checked against the booking amount.

## Changing the design

Styles are pre-built in `assets/app.css` (Tailwind CSS). If you change CSS classes in the PHP
files, rebuild it from the repository root with `npm install` then `npm run php:css`.
