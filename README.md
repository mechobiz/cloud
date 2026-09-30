# GMB CRM

A small CRM for managing Google Business Profile locations: clients, reviews inbox with replies,
post scheduling, and performance insights. Plain PHP 8+ and MySQL, so it runs on Namecheap shared
hosting (cPanel) with no build step.

## Features
- Login (single admin), CSRF protection, hashed passwords
- Locations synced from Google, with client name, contact, status and notes (the CRM part)
- Reviews inbox: sync, filter unanswered, reply from the app
- Posts: publish now, schedule (via cron), drafts, multi-location
- Insights: impressions, calls, website clicks and direction requests for the last 30 days

## Manual mode (no Google API yet)
With no Google credentials in `app/config.php`, the app runs in manual mode: add locations, log
reviews and reply, and create posts locally. Google sync buttons appear once credentials are added,
and manually added locations are never sent to Google.

## 1. Google Cloud setup (optional, do later)
1. Create a project at https://console.cloud.google.com.
2. Enable these APIs: **My Business Account Management API**, **My Business Business Information API**,
   **Google My Business API** (v4, for reviews and posts), **Business Profile Performance API**.
3. Business Profile APIs need approval: request access at
   https://developers.google.com/my-business/content/prereqs. Until approved, quota is 0 and calls fail.
4. OAuth consent screen: add the scope `https://www.googleapis.com/auth/business.manage`.
5. Credentials -> Create OAuth client ID -> Web application. Add the authorized redirect URI
   `https://yourdomain.com/google_callback.php` (same value as `redirect_uri` in config).

## 2. Namecheap (cPanel) deployment
1. **MySQL Databases**: create a database and a user, give the user all privileges.
2. **phpMyAdmin**: select the database, Import `database/schema.sql`.
3. **File Manager** (or FTP): upload the repository contents into `public_html` (or a subfolder).
4. Copy `app/config.sample.php` to `app/config.php` and fill in URL, DB and Google credentials.
5. Visit `https://yourdomain.com/setup.php`, create the admin user, then **delete `setup.php`**.
6. Log in, open **Locations**, click **Connect Google**, then **Sync locations**.
7. **Cron Jobs** in cPanel, every 15 minutes:
   `php /home/CPANELUSER/public_html/cron.php`
   This publishes scheduled posts and refreshes reviews and insights hourly.

`app/` and `database/` contain `.htaccess` files that deny web access. Check that
`https://yourdomain.com/app/config.php` returns 403.

## Notes
- Google enforces low per-minute quotas on these APIs; the app reports rate-limit errors instead of retrying.
- Insights data lags about 2 days on Google's side.
- Requires PHP 8.0+ with `curl`, `pdo_mysql` and `mbstring` (all standard on Namecheap; pick PHP 8.x in cPanel "Select PHP Version").

## Local development
```
cp app/config.sample.php app/config.php   # fill in
php -S localhost:8000
```
