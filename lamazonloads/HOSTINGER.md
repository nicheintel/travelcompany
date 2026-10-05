# Put LamazonLoads live on Hostinger

You need: a Hostinger plan with **lamazonloads.com** connected to it. About 15 minutes.
Use **`lamazonloads-hostinger.zip`**. It holds the website files plus a `config.local.php` ready to fill in.
(To build it yourself: zip the *contents* of the `site` folder and add a copy of `config.local.example.php` named `config.local.php`.)

## 1. Set PHP to 8.2
hPanel → **Websites** → lamazonloads.com → **Advanced → PHP Configuration** → choose **PHP 8.2** (8.1 or newer) → Save.

## 2. Create the database
If Hostinger already made one for you (a **"Connect database manually"** screen showing a database name,
username and password), use those values and skip to step 3. Otherwise:

hPanel → **Databases → Management** (MySQL Databases):
- Database name: e.g. `lamazon` → Hostinger shows the full name, like **`u123456789_lamazon`**
- Username: e.g. `lamazon` → full name like **`u123456789_lamazon`**
- Password: make a strong one and **write it down**
- Click **Create**. Keep these three values for step 4.

## 3. Upload the website
hPanel → **Files → File Manager** → open **`public_html`**:
1. Delete the default files that are there (e.g. `default.php`, or an old site).
2. Click **Upload** and choose `lamazonloads-hostinger.zip`.
3. Right-click the zip → **Extract** → extract into `public_html` (leave the folder box empty / `.`).
4. Check that **`index.php` is directly inside `public_html`**, not inside another folder.
5. Delete the zip.

## 4. Fill in your settings
In File Manager, right-click **`public_html/config.local.php`** → **Edit**, and replace the `PUT_…` values:

```php
'db_host' => 'localhost',
'db_name' => 'u123456789_lamazon',      // from step 2
'db_user' => 'u123456789_lamazon',      // from step 2
'db_pass' => 'your-database-password',  // from step 2
'admin_emails' => 'you@lamazonloads.com',
'contact_email' => 'dispatch@lamazonloads.com',
'contact_phone' => '(555) 123-4567',
```
Save.

**`admin_emails` matters:** only the emails listed there become admins when they sign up.
Several people: `'you@lamazonloads.com, partner@lamazonloads.com'`.

## 5. Turn on SSL (https)
hPanel → **Security → SSL** → make sure lamazonloads.com has an active (free) certificate.
The site sends every visitor to `https://` automatically.

## 6. Open the site and create your admin account
Go to **https://lamazonloads.com**. The tables and the four starter job posts are created on the first visit.
Click **Get loaded** and sign up **with the email you put in `admin_emails`**. "Admin" appears in the top menu.
Then edit or close the starter job posts in **Admin → Job posts**.

## 7. Quick security check
These two addresses must show **403 Forbidden** (not a download):
- https://lamazonloads.com/database.sql
- https://lamazonloads.com/uploads/

## Good to know
- **Updating later:** upload only the changed files, or upload a new zip and extract it over the old files.
  Never overwrite `config.local.php` or delete the `uploads` folder (drivers' documents are there).
- **Backups:** hPanel → Files → Backups (database + files). Download one now and then.
- **Contact form messages** appear in **Admin → Messages** (no email alert yet).
- **Google:** in Google Search Console, add lamazonloads.com and submit `https://lamazonloads.com/sitemap.php`.
- **"Can't connect to the database"** after step 6 means a typo in `config.local.php`: check the
  full `u123456789_…` names and the password.
