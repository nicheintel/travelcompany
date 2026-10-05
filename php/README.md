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

**Easiest:** on your own computer, the first account you use becomes the admin automatically
(only on `localhost`, and only while the site has no admin). Then open
**Admin dashboard → Site settings** to paste your Duffel and LiteAPI keys and set your markup,
and **Diagnostics** to check the connections. No files to edit.

You can also put settings in a file — values there override Site settings. Copy
`config.local.example.php` to **`config.local.php`** and fill in what you use:

| Setting | What it does |
| --- | --- |
| `admin_emails` | Your email(s). Sign up with that email and **Admin dashboard** appears in the user menu. |
| `duffel_access_token` | Real flights from Duffel. Without it, flight search says "not available" — the site never invents flights. Test tokens (`duffel_test_…`) only return Duffel's pretend airline "Duffel Airways"; real airlines appear after **Go live** in Duffel. |
| `liteapi_key` | Real hotels from LiteAPI (sandbox key while testing; production key to sell). |
| `demo_mode` | `true` shows clearly-marked **made-up** sample flights and hotels when no key is set — for demos only, never for customers. Default `false`. |
| `flight_markup_rate` / `hotel_markup_rate` | Your margin on supplier prices (default `0.20` = +20%). |
| `member_discount_rate` | Discount for signed-in members (default `0.10`; `0` turns it off). |
| `smtp_user`, `smtp_pass` (+ `smtp_host`, `smtp_port`) | Send real emails from your own mailbox, e.g. Hostinger email (smtp.hostinger.com, port 465). Or `resend_api_key` + `email_from` for [Resend](https://resend.com). With neither, emails are written to `storage/emails.log.php` (handy for password-reset links on XAMPP). |
| `paypal_client_id`, `paypal_secret`, `paypal_mode` | Online payments with **PayPal** (PayPal account or any card). Keys from developer.paypal.com → Apps & Credentials; `sandbox` while testing, `live` for real money. |
| `paypal_webhook_id` | Optional, recommended live: webhook to `https://YOUR-SITE/paypal-webhook.php` for `CHECKOUT.ORDER.APPROVED` and `PAYMENT.CAPTURE.COMPLETED`. |
| `stripe_secret_key`, `stripe_webhook_secret` | Alternative to PayPal (Stripe isn't available to Philippine-registered businesses). Webhook URL: `https://YOUR-SITE/stripe-webhook.php`. |
| `db_*` | Database login, if not XAMPP's defaults. |
| `app_url` | **Set this on a live server**, e.g. `https://www.yourdomain.com`. |

`config.local.php` is ignored by git, so your keys never end up on GitHub. On hosting that
supports environment variables you can use the same names in upper case instead
(e.g. `DUFFEL_ACCESS_TOKEN`).

## Upload to Hostinger (any plan, including the cheapest)

1. hPanel → **Databases → MySQL Databases**: create a database and user; note the names and password.
2. Upload the **contents** of this folder to `public_html` (File Manager or FTP).
3. Create `config.local.php` there with `db_name`, `db_user`, `db_pass`, `db_host` (usually
   `localhost`) and your keys.
4. hPanel → **Security → SSL**: turn on the free SSL certificate.
5. Visit `https://yourdomain.com` — the tables are created automatically. Sign in as admin and
   open the dashboard once, so the site saves its address for links in emails.

## Pages

| File | Page |
| --- | --- |
| `index.php` | Landing page with Flights / Flight + Hotel / Hotels search |
| `flights.php`, `hotels.php`, `packages.php` | Search results with filters and sorting; promo packages (created on Admin → Packages) |
| `book.php` | Booking: traveler details, contact info, price summary (signed-in members) |
| `account.php`, `trip.php`, `settings.php` | My trips, trip details (pay / cancel), account settings |
| `register.php`, `signin.php`, `forgot-password.php`, `reset-password.php` | Accounts |
| `admin/` | Staff dashboard: paid trips to ticket, needs-a-call list, bookings, payments, ticketing, notes, users, packages and photos |
| `paypal-webhook.php`, `stripe-webhook.php` | Payment notifications from PayPal / Stripe |

Code lives in `includes/` (blocked from the web by `.htaccess`): `search.php` (Duffel, LiteAPI,
pricing), `quote.php` (booking prices, always recomputed on the server), `bookings.php`,
`auth.php`, `email.php`, `payments.php`, `packages.php` (promo packages, photo uploads), `ui.php` (shared HTML pieces).

Uploaded photos are saved in `uploads/` (the folder must be writable). Every photo is re-saved as
a new JPEG, so only real images end up there, and `uploads/.htaccess` serves nothing else.

## Security built in

Passwords hashed with `password_hash`; CSRF tokens on every form; prepared SQL statements;
output escaping; session cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` on https); sign-in
lockout after 5 wrong passwords (15 min); single-use, hashed password-reset links (1 hour);
changing the password signs out other devices; booking prices re-checked with the supplier;
PayPal and Stripe payments confirmed server-to-server and checked against the booking amount;
payment webhooks signature-verified.

Also:
- **Content-Security-Policy with a per-request nonce.** Only this site's own scripts can run,
  and forms can only post to this site (then on to PayPal/Stripe). Pages can't be framed
  (clickjacking), and `nosniff`, `Referrer-Policy` and `Permissions-Policy` headers are sent.
- **HTTPS.** When the site address starts with `https://`, plain http is redirected to https,
  HSTS is sent and the session cookie becomes `__Secure-` and `Secure`.
- **Links in emails** use only the configured site address, never the request's Host header.
  On a live server the address is saved the first time an admin opens the dashboard, or can be
  set on Admin → Site settings.
- **Limits per IP address.** Sign-in (30 per 15 min), new accounts (10 per hour), password-reset
  emails (10 per hour), live searches (40 per 10 min, repeats cached for 3 minutes) and
  reservations (20 per account and 30 per IP per day). `X-Forwarded-For` is ignored, so it
  can't be faked to get around them.
- **Private files.** `includes/` files refuse to run directly; the email log is
  `storage/emails.log.php` and starts with `exit`, so it can't be read from the web even on
  servers that ignore `.htaccess`. Error details are never shown to visitors.
- **Owner setup** (first local account becomes admin) only works from `localhost` on your own
  computer, with no proxy headers.
- Admin → Diagnostics warns when HTTPS, the site address or a database password is missing.

Only the PHP version in this folder has these protections. The Next.js version in the repository
root is a prototype and shouldn't be put online as is.

## Changing the design

Styles are pre-built in `assets/app.css` (Tailwind CSS). If you change CSS classes in the PHP
files, rebuild it from the repository root with `npm install` then `npm run php:css`.
