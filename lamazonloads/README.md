# LamazonLoads website

**Why Wait? Let's Freight.** The website for LamazonLoads: freight dispatching, daily routes and
driver support for cargo vans, Sprinter vans and box trucks. Plain PHP 8.1+ and MySQL, so it runs on
**XAMPP** with no build step, and later on any normal web host (e.g. Hostinger) at lamazonloads.com.

## Test it on Windows (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org) (PHP 8.1 or newer).
2. Double-click **`START-LamazonLoads.bat`** in this folder. It finds XAMPP, copies `site/` to
   `C:\xampp\htdocs\lamazonloads`, starts Apache + MySQL and opens **http://localhost/lamazonloads/**.
3. Create your account. **The first account created on your own computer becomes the admin.**
4. `STOP-LamazonLoads.bat` stops Apache and MySQL.

The database `lamazonloads` and its tables are created automatically on the first visit (XAMPP's
default `root` user, no password), with four starter job posts you can edit or close in
**Admin → Job posts**. You can also import `site/database.sql` in phpMyAdmin.

Manual install instead of the .bat: copy the `site` folder to `C:\xampp\htdocs\` and rename it to
`lamazonloads`, start Apache and MySQL in the XAMPP Control Panel, then open the address above.

## Put it live on Hostinger

Step by step: [`HOSTINGER.md`](HOSTINGER.md). In short: create a MySQL database in hPanel, upload the contents of
`site/` to `public_html`, fill in `config.local.php` (database + `admin_emails`), turn on SSL, then sign up
with your admin email. On a live site only `admin_emails` become admins, and visitors are sent to https.

## Pages

| Page | What it is |
| --- | --- |
| `index.php` | Home: hero, equipment, services, "built from the driver's seat", how it works, open opportunities, FAQ |
| `services.php`, `drivers.php`, `about.php` | Services, Drive with us (onboarding checklist), company story & motto |
| `careers.php`, `job.php` | Job posts with category filter; job details and one-click apply. `job.php?id=0` is the always-open "Join the driver network" application |
| `contact.php` | Contact form (messages land in Admin → Messages) |
| `register.php`, `login.php` | Sign up (name, email, phone, owner-operator / driver / dispatcher) and sign in |
| `account.php` | Member dashboard: onboarding progress and application status |
| `profile.php` | Driver profile: equipment, vehicle, ZIP code, availability, MC / DOT, insurance |
| `documents.php` | Private uploads: W-9, insurance (COI), driver's license, registration, authority (PDF / JPG / PNG) |
| `settings.php` | Change name, phone, password |
| `admin/` | Staff: overview, applications (status + private notes), job posts, drivers & members (search by ZIP / equipment, documents, make staff, reset password), messages |

## Settings

Copy `site/config.local.example.php` to `config.local.php` (the .bat does this for you) to set:

| Setting | What it does |
| --- | --- |
| `contact_email`, `contact_phone` | Shown in the footer and on the Contact page |
| `admin_emails` | Emails that become admins when they sign up (needed on a live server) |
| `db_host`, `db_name`, `db_user`, `db_pass` | Database login (live server) |
| `max_upload_mb` | Biggest document upload (default 8 MB) |

## Security

Passwords are hashed with `password_hash`; every form has a CSRF token; sign-in is rate-limited;
uploaded documents are checked by content (not file name), stored under random names in `uploads/`
(blocked from the web) and only served to their owner and staff through `doc.php`. `includes/`,
`storage/`, `database.sql` and `config.local.php` are blocked by `.htaccess`.

## Brand

The site uses the LamazonLoads logo (`site/assets/brand/logo.png`, trimmed from
`brand/lamazonloads-logo-original.png`) with a blue and white theme: navy `#0A2463`, blue `#1E63E9`,
light blue `#8EC2FF`, white. Fonts: Montserrat (headings) and Inter (text).
To swap the logo later, replace `site/assets/brand/logo.png` (and `site/assets/favicon.png`).

The other files in `brand/` are optional alternative logo designs, not used by the site.
