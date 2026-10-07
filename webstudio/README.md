# Web Studio website (PHP + MySQL, runs on XAMPP)

The website for a web design business: a landing page that turns visitors into website
requests, a private tracking page for each client, and an admin dashboard to run everything.
Plain PHP 8.1+ and MySQL/MariaDB — no frameworks, no Composer, no build step.

The brand name ("IdeaCraft Studio") is a placeholder. Change it, and everything else the
website says, on **Admin → Site settings**.

## Run it on XAMPP

**Easiest on Windows:** double-click **`START-WINDOWS.bat`** in this folder. It finds XAMPP,
copies the site to `C:\xampp\htdocs\webstudio`, starts Apache and MySQL and opens your browser.

Or by hand:

1. Install [XAMPP](https://www.apachefriends.org) (PHP 8.1 or newer).
2. Copy this `webstudio` folder into XAMPP's `htdocs` folder
   (`C:\xampp\htdocs\webstudio` on Windows, `/Applications/XAMPP/htdocs/webstudio` on Mac).
3. In the XAMPP Control Panel, start **Apache** and **MySQL**.
4. Open **http://localhost/webstudio** — the database and tables are created automatically.
5. Open **http://localhost/webstudio/admin** and create your owner account (first visit only).

## What's inside

| Page | What it does |
| --- | --- |
| `index.php` | Landing page: hero with a live demo website, services, why us, portfolio, 4-step process, pricing, reviews, FAQ and the "Request your website" form |
| `request.php` | Receives the form (checks every field, blocks spam bots, max 5 requests per hour per connection) |
| `track.php` | The client's private page: progress timeline, your latest update, their quote. No account needed — the link is the key |
| `privacy.php` | Short privacy notice |
| `admin/` | The dashboard (see below) |

### The admin dashboard (`/admin`)

- **Dashboard** — new requests, conversations, projects in progress and launched; requests per
  week; your pipeline; who needs a reply; recent activity; a "finish setting up" checklist.
- **Requests** — every request, searchable, filtered by status, downloadable as CSV. Open one
  to email / WhatsApp / call the client in one tap, move it through
  *New → Contacted → Quote sent → In progress → Launched* (or *Closed*), write an update the
  client sees on their tracking page, record your quote and keep private notes.
- **Pricing packages, Portfolio, Testimonials, FAQs** — add, edit, reorder, hide or delete.
  Portfolio items take a screenshot upload (re-saved as a fresh JPEG, so only real pictures
  are stored) or show a colorful preview.
- **Site settings** — brand name, headline (put `*stars*` around words to color them orange),
  contact details, WhatsApp / Messenger chat button, social links, currency, time zone.
- **Team & password** — add team members, set a new password for one, change your own.

Why there's no customer login: asking visitors to create an account before they can ask for a
website loses customers. They just send the form, and their private tracking link shows their
progress instead.

The starter content (3 packages, 7 FAQs, 6 portfolio items marked *Design concept*) is there
so the site looks complete on day one. Change the prices, timelines and FAQ answers to match how
you work, and replace the concepts with real projects as you finish them. The reviews section
stays hidden until you add a real review.

## Forgot your admin password?

Another admin can set a new one on **Team & password**. Or, on the computer running XAMPP:

```
C:\xampp\php\php.exe C:\xampp\htdocs\webstudio\tools\reset-password.php you@example.com NewPassword123
```

## Put it online on Hostinger

1. **PHP version:** hPanel → **Advanced → PHP Configuration** → PHP 8.2 or newer.
2. **Database:** hPanel → **Databases → MySQL Databases** → create one. Write down the
   database name, user and password (names look like `u123456789_webstudio`).
3. **Upload:** hPanel → **Files → File Manager** → open `public_html` (for a subfolder such as
   `yourdomain.com/studio`, create and open that folder) → upload the zip → right-click →
   **Extract** → delete the zip. `index.php` must sit directly in that folder.
   (No zip? Upload the contents of this folder, plus `config.local.example.php` renamed to
   `config.local.php`.)
4. **Settings file:** in File Manager, right-click `config.local.php` → **Edit**. Fill in
   `db_name`, `db_user` and `db_pass` from step 2 (`db_host` stays `localhost`) and a long
   `setup_key` phrase. Once you have a domain, set `app_url` to `https://www.yourdomain.com`.
5. **SSL:** hPanel → **Security → SSL** → install the free certificate.
6. **Owner account:** open `https://yourdomain.com/admin/setup.php?key=YOUR-SETUP-KEY`
   and create it. The tables are created automatically on the first visit. After that the
   setup page switches itself off.

If something is wrong, the site says what to fix (missing database details, wrong password,
old PHP version) instead of a blank error.

## Security built in

Passwords hashed with `password_hash`; sign-in locks an email for 15 minutes after 5 wrong
passwords (and limits attempts per connection); changing a password signs out other devices;
CSRF tokens on every form; prepared SQL statements everywhere; every value escaped on output;
Content-Security-Policy that only allows this site's own scripts and forms; pages can't be
framed; `includes/`, `storage/`, `tools/`, `.sql` and `config.local.php` are blocked from the
web; uploads folder only serves the re-saved JPEGs; the first admin account can only be created
from your own computer or with the secret setup key; tracking links are random 128-bit tokens
and aren't indexed by search engines; CSV export neutralises spreadsheet formulas.

## Changing the design

Plain CSS, no build step: `assets/css/site.css` (website) and `assets/css/admin.css`
(dashboard). The colors are at the top of each file (`--royal-*` and `--orange-*`).
Font: Plus Jakarta Sans (SIL Open Font License, `assets/fonts/OFL-LICENSE.txt`).
