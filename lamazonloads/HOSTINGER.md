# Put LamazonLoads live on Hostinger

You need: a Hostinger plan with **lamazonloads.com** connected to it. About 15 minutes.
Use **`lamazonloads-hostinger.zip`**. It holds the website files plus a `config.local.php` ready to fill in.
(To build it yourself, use only the committed files, never a folder you've tested in: from the repository root run
`git archive --format=zip -o lamazonloads-site.zip HEAD:lamazonloads/site`, then add a copy of `config.local.example.php`
named `config.local.php`. A tested folder holds test emails, sessions and uploads that must not go online.)

**Also in hPanel:** Advanced → PHP Configuration → set **display_errors** to Off (the site turns it off too).

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
'contact_email' => 'info@lamazonloads.com',
'contact_phone' => '(678) 528-1181',
'smtp_user' => 'info@lamazonloads.com',
'smtp_pass' => 'your-email-password',    // the password of the info@ mailbox (step 4b)
```
Save.

**`admin_emails` matters:** the email listed there becomes the site's first admin, but only after you click the confirmation link we email to it (so nobody else can claim it). Everyone after that gets staff access from you in **Admin → Drivers & members** (Moderator or Admin). Accounts listed here can't be demoted or deleted from the website.
Several people: `'you@lamazonloads.com, partner@lamazonloads.com'`.

### 4b. Email for the live chat
The Chat button emails you when someone writes, and emails people your reply if they've left the site.
1. hPanel → **Emails** → make sure the mailbox **info@lamazonloads.com** exists (create it if not) and note its password.
2. Put that password in `config.local.php` as `smtp_pass` (and `smtp_user` = `info@lamazonloads.com`).
Chat alerts go to `contact_email`. Without the password the site still tries to send with PHP's built-in mail,
but those emails often land in spam.

## 5. Turn on SSL (https)
hPanel → **Security → SSL** → make sure lamazonloads.com has an active (free) certificate.
The site sends every visitor to `https://` automatically.

## 6. Open the site and create your admin account
Go to **https://lamazonloads.com**. The tables and the four starter job posts are created on the first visit.
Click **Get loaded** and sign up **with the email you put in `admin_emails`**, then open the confirmation email and click the link. You're now the admin, and "Admin" appears in the top menu.
Then edit or close the starter job posts in **Admin → Job posts**.

## 7. Quick security check
These two addresses must show **403 Forbidden** (not a download):
- https://lamazonloads.com/database.sql
- https://lamazonloads.com/uploads/

## Good to know
- **Updating later:** upload only the changed files, or upload a new zip and extract it over the old files.
  Never overwrite `config.local.php` or delete the `uploads` folder (drivers' documents are there).
- **Backups:** hPanel → Files → Backups (database + files). Download one now and then.
- **Live chat:** answer in **Admin → Support chats**. While that page is open, visitors see "Online now".
- **Contact form messages** appear in **Admin → Messages** (no email alert).
- **Google:** in Google Search Console, add lamazonloads.com and submit `https://lamazonloads.com/sitemap.php`.
- **"Can't connect to the database"** after step 6 means a typo in `config.local.php`: check the
  full `u123456789_…` names and the password.

## Keep it secure (accounts and hosting)
The website protects itself (see README → Security). These settings are yours to keep safe:
- **Two-step login (2FA)** on your **Hostinger** account (hPanel → profile → Security) and your **GoDaddy** account
  (Account settings → Login & PIN → 2-step verification). Whoever controls these controls your website and domain.
- **GoDaddy domain protection:** keep the domain **locked** (Domain → Registration Settings → Domain lock: On).
- **Strong, unique passwords** for Hostinger, GoDaddy, the database, the info@ mailbox and your admin account
  on the website. Never reuse them. A password manager helps.
- **Keep `admin_emails` to the owner's own address.** Give staff access in Admin → Drivers & members instead, and remove it when people leave.
- **Delete update zips** from `public_html` after extracting them (the site blocks them anyway).
- **Backups:** hPanel → Files → Backups. Hostinger makes daily backups; download one now and then.
- **PHP version:** keep it on a supported version (8.2 or newer) in hPanel → Advanced → PHP Configuration.
- **Malware scanner:** leave it on (Security → Malware scanner).
