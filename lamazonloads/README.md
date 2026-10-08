# LamazonLoads website

**Why Wait? Let's Freight.** The website for LamazonLoads: freight dispatching, daily routes and
driver support for cargo vans, Sprinter vans and box trucks. Plain PHP 8.1+ and MySQL with no build
step, hosted on **Hostinger** at **lamazonloads.com**.

## Put it live / update it

Step by step: [`HOSTINGER.md`](HOSTINGER.md). In short: create a MySQL database in hPanel, upload the
contents of `site/` to `public_html`, make `config.local.php` from `config.local.example.php` (database,
`admin_emails`, contact details), turn on SSL, then sign up with your admin email.

The tables and four starter job posts are created automatically on the first visit
(or import `site/database.sql` in phpMyAdmin). Only emails listed in `admin_emails` become admins.

## Pages

| Page | What it is |
| --- | --- |
| `index.php` | Home: hero, equipment, services, "built from the driver's seat", how it works, open opportunities, FAQ |
| `services.php`, `drivers.php`, `about.php` | Services, Drive with us (onboarding checklist), company story & motto |
| `careers.php`, `job.php` | Job posts with category filter; job details and one-click apply. `job.php?id=0` is the always-open "Join the driver network" application |
| `contact.php` | Contact form (messages land in Admin → Messages) |
| `privacy.php` | Privacy policy |
| Chat button | Live chat on every page (not for staff): `chat.php` + `assets/chat.js`, engine in `includes/chat.php`. Staff answer in `admin/chats.php` (Support chats). Emails via `includes/mail.php` |
| `register.php`, `login.php` | Sign up (name, email, phone, owner-operator / driver / dispatcher) and sign in |
| `account.php` | Member dashboard: onboarding progress and application status |
| `profile.php` | Driver profile: equipment, vehicle, ZIP code, availability, MC / DOT, insurance |
| `documents.php` | Private uploads: W-9, insurance (COI), driver's license, registration, authority (PDF / JPG / PNG) |
| `settings.php` | Change name, phone, password |
| `admin/` | Staff: overview, applications (status + private notes), job posts, drivers & members (search by ZIP / equipment, documents, make staff, reset password), messages |
| Job posts | Admin → Job posts: category (incl. Light Truck & Delivery Drivers), number to hire in 30 days, job types (multi-choice), settings (apply on the site or another website, resume required/optional/no, application-update emails, candidate contact email, fair chance, background check, hiring timeline) and automations (welcome email, auto "In review", onboarding reminder, "Not selected" for unresponsive applicants, auto-close when enough are approved). Code: `includes/jobs.php`. Time-based automations run at most every 30 minutes when someone opens a page |

## Settings

`config.local.php` (made from `site/config.local.example.php`) sets:

| Setting | What it does |
| --- | --- |
| `contact_email`, `contact_phone` | Shown in the header (phone), footer and on the Contact page. Currently info@lamazonloads.com and (678) 528-1181 |
| `admin_emails` | Emails that become admins when they sign up |
| `db_host`, `db_name`, `db_user`, `db_pass` | Database login from hPanel → Databases |
| `max_upload_mb` | Biggest document upload (default 8 MB) |
| `smtp_user`, `smtp_pass` | Hostinger mailbox for chat emails (`smtp_host` smtp.hostinger.com, port 465 by default). `support_email` = where chat alerts go (default `contact_email`) |

## Security

- **Passwords:** stored only as bcrypt hashes; easy-to-guess passwords are refused; sign-in is paused for 15 minutes after too many
  wrong passwords (8 for one email or 25 from one connection) and takes the same time whether or not the email exists.
- **Every form** has a CSRF token; staff pages check for an admin account; all database queries are parameterised;
  everything people type is shown as text (never as HTML), including chat messages and emails.
- **Spam limits:** 5 new accounts per hour per connection, contact form and chat limits, hidden "honeypot" fields.
- **Uploads** are checked by their content (not their name), saved under random names in `uploads/` (blocked from the
  web, nothing there can run as code) and only served to their owner and staff through `doc.php`.
- **Browser protections:** Content Security Policy (only our own scripts run), no framing (clickjacking), HSTS
  (https only), no camera/microphone/location access, secure, HttpOnly, SameSite session cookies.
- **Private files** are blocked by `.htaccess`: `includes/`, `storage/`, `uploads/`, `config.local.php`, `database.sql`,
  and leftovers such as `.zip`, `.bak` or `.sql` files. Error details go to the log, never to visitors.
- Account-level security (2FA, domain lock, backups): see the checklist at the end of `HOSTINGER.md`.

## Brand

The site uses the LamazonLoads logo (`site/assets/brand/logo.png`, trimmed from
`brand/lamazonloads-logo-original.png`) with a blue and white theme: navy `#0A2463`, blue `#1E63E9`,
light blue `#8EC2FF`, white. Fonts: Montserrat (headings) and Inter (text).
To swap the logo later, replace `site/assets/brand/logo.png` (and `site/assets/favicon.png`).

The other files in `brand/` are optional alternative logo designs, not used by the site.
