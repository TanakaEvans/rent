# Hosting ZimRent on cPanel (test site)

Written for someone who has never used cPanel. Follow it top to bottom;
it takes about 30 minutes.

Throughout, replace:

- `USER` — your cPanel username (shown on the right of the cPanel home page)
- `rentals.inspirelabs.co.zw` — your domain

**Log in at:** `https://rentals.inspirelabs.co.zw/cpanel`

---

## What you are uploading

`C:\Users\Gwese\Documents\zimrent-deploy.zip` (about 11 MB) contains the
whole site, already prepared:

- the app code,
- the compiled front-end (`public/build`),
- all PHP libraries (`vendor`),
- `env-production.txt` — your settings file, with a security key already generated,
- `READ-ME-FIRST.txt` — the short version of this guide.

So the server needs **no Node.js and no Composer**. Rebuild the zip after
code changes with `npm run build` and the packaging script (see *Updating the
site later*).

---

## Step 1 — Check the PHP version (2 minutes)

The app needs **PHP 8.3 or newer**. Nothing else works until this is right.

1. On the cPanel home page, use the search box at the top: type `PHP`.
2. Open **Select PHP Version** (some hosts call it **MultiPHP Manager**).
3. If the version shown is lower than 8.3, choose **8.3** (or newer) and
   press **Apply** / **Set as current**.
4. If 8.3 is not offered at all, stop and email your host:
   *"Please enable PHP 8.3 for rentals.inspirelabs.co.zw."*

While you are there, check these boxes are ticked under **Extensions**:
`bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`,
`pdo_mysql`, `tokenizer`, `xml`, `zip`. Most hosts enable them already.

---

## Step 2 — Create the database (5 minutes)

1. cPanel search → **MySQL® Database Wizard**.
2. **Step 1 — New Database:** type `zimrent` → **Next Step**.
   The real name becomes something like `inspire_zimrent`. Write it down.
3. **Step 2 — Database User:** username `zimrent`, then use **Password
   Generator** and **copy the password somewhere safe** → **Create User**.
   The real username becomes something like `inspire_zimrent`.
4. **Step 3 — Privileges:** tick **ALL PRIVILEGES** → **Next Step**.

You now have three values you will need in Step 5:

```
database name:  inspire_zimrent
database user:  inspire_zimrent
password:       (the one you generated)
```

---

## Step 3 — Upload and extract the site (5 minutes)

1. cPanel search → **File Manager**.
2. Click **Home** in the left sidebar (the folder `/home/USER`).
   **Do not** go into `public_html`.
3. Click **Upload** (top toolbar) → **Select File** → choose
   `zimrent-deploy.zip` → wait for the green progress bar → click
   **Go Back to ...** at the bottom.
4. Press **Reload** in File Manager, right-click `zimrent-deploy.zip` →
   **Extract** → **Extract File(s)**.
5. You should now have a folder `/home/USER/zimrent`. Open it: you should see
   `app`, `public`, `vendor`, `artisan`, `env-production.txt`.
6. Delete `zimrent-deploy.zip` to save space (right-click → **Delete**).

---

## Step 4 — Point the domain at the `public` folder (3 minutes)

The site must serve only the `public` folder. Everything else (including your
password file) must stay private.

1. cPanel search → **Domains**.
2. Find `rentals.inspirelabs.co.zw` → **Manage**.
3. Set **Document Root** to:

   ```
   /home/USER/zimrent/public
   ```

4. Save.

If your plan will not let you change the document root, see *If you cannot
change the document root* at the end of this guide.

---

## Step 5 — Enter your database details (3 minutes)

1. **File Manager** → open `/home/USER/zimrent`.
2. Right-click `env-production.txt` → **Rename** → change it to `.env`
   (a leading dot, no `.txt`).
   - If the file disappears: click **Settings** (top right) → tick
     **Show Hidden Files (dotfiles)** → **Save**.
3. Right-click `.env` → **Edit** → **Edit** again if a dialog appears.
4. Fill in the three values from Step 2:

   ```ini
   DB_DATABASE=inspire_zimrent
   DB_USERNAME=inspire_zimrent
   DB_PASSWORD=the-password-you-generated
   ```

5. Check `APP_URL` matches your domain, then **Save Changes**.

Leave `APP_DEBUG=false`. If it is `true`, error pages show your database
password to anyone who visits.

---

## Step 6 — Set up the database tables (5 minutes)

Two ways. Try **A**; if your host has no Terminal, use **B**.

### A. With Terminal (preferred)

1. cPanel search → **Terminal** → accept the warning.
2. Paste these one at a time:

   ```bash
   cd ~/zimrent
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache
   ```

3. Each should end without a red error. Then delete the browser setup page
   you do not need:

   ```bash
   rm -f public/zimrent-setup-*.php
   ```

### B. Without Terminal (browser setup page)

The package includes a one-time page that does the same work.

1. Visit:

   ```
   https://rentals.inspirelabs.co.zw/zimrent-setup-1b27315a523c12ff.php
   ```

2. Wait — it prints what it is doing (this can take a minute).
3. When it finishes it **deletes itself** automatically. If it reports it
   could not delete itself, remove that file in File Manager
   (`/home/USER/zimrent/public/`).

If it stops with a database error, fix `.env` (Step 5) and upload the file
again from the package to retry.

---

## Step 7 — Turn on HTTPS (2 minutes)

1. cPanel search → **SSL/TLS Status**.
2. Tick your domain → **Run AutoSSL**. Wait for a green padlock.
3. Then open the site at `https://rentals.inspirelabs.co.zw`.

To force everyone onto HTTPS, edit `/home/USER/zimrent/public/.htaccess` and
add these two lines directly **after** the line `RewriteEngine On`:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

---

## Step 8 — Add the scheduled job (3 minutes)

Without this, rent invoices never become payable, listings never expire,
promotions never end and maintenance breaches are never escalated.

1. cPanel search → **Cron Jobs**.
2. **Common Settings** → **Once Per Minute (\* \* \* \* \*)**.
3. **Command:**

   ```
   cd /home/USER/zimrent && php artisan schedule:run >> /dev/null 2>&1
   ```

4. **Add New Cron Job**.

If it does not seem to run, replace `php` with the full path, which you can
see in Terminal with `which php` (often
`/opt/cpanel/ea-php83/root/usr/bin/php`).

---

## Step 9 — First sign-in and clean-up (5 minutes)

1. Open `https://rentals.inspirelabs.co.zw` — the marketplace should load
   with listings and photos.
2. Sign in with `admin@system.local` / `password123`.
3. **Change that password now:** profile menu (top right) → **Change password**.
4. Go to **System Users** and deactivate the demo logins you do not need:
   `owner@dzimba.local`, `tenant@dzimba.local`, `staff@dzimba.local`,
   `contractor@dzimba.local` (all use `password123`).
   For a private test site you can keep them — just never leave them on a
   site real users can reach.
5. Create your own accounts: **System Users → Edit** sets a person's roles
   (tick **Owner** to let someone list property).

### Worth testing
- Browse, filter and open a listing; try the **Map** view.
- As an owner, add a property with photos (proves uploads work).
- Open **Help Center** (`/help`) and, as admin, the **Admin Guide**.
- Confirm `https://rentals.inspirelabs.co.zw/.env` gives **403/404** —
  never your settings.

**Never run `php artisan zimrent:seed-demo` on this server.** It is the
load-test generator and inserts ~250,000 fake rows.

---

## Updating the site later

On your machine:

```bash
npm run build
php <scratchpad>/makezip.php      # rebuilds zimrent-deploy.zip
```

On the server: upload and extract over `/home/USER/zimrent` (keep your
`.env`), then in Terminal:

```bash
cd ~/zimrent
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Take a backup first: **cPanel → Backup → Download a MySQL Database Backup**.

---

## If something goes wrong

| What you see | What it means |
|---|---|
| *Vite manifest not found* | `public/build` missing — re-upload it from the package |
| Blank page / **500** | Open `/home/USER/zimrent/storage/logs/laravel.log` in File Manager; the last lines say why |
| *Permission denied* in the log | File Manager → select `storage` → **Permissions** → `0775`, tick **Recurse** |
| Listing photos show a coloured placeholder | `php artisan storage:link` was not run (Step 6) |
| A page shows old settings | Terminal: `php artisan optimize:clear`, then `php artisan config:cache` |
| Directory listing or "Index of /" | Document root is wrong (Step 4) |
| *No supported encrypter found* | `APP_KEY` missing from `.env` — it is in the supplied file, do not delete it |
| Large photo uploads fail | **Select PHP Version → Options** → `upload_max_filesize` and `post_max_size` = `16M` |

Map tiles and the demo listing photos load from the internet (CARTO, Esri,
Unsplash) in the visitor's browser — nothing to configure on the server.

---

## If you cannot change the document root

Some cheap plans force the domain to `public_html`.

1. Move the project to `/home/USER/zimrent` as above.
2. Copy everything **inside** `zimrent/public` into `public_html`.
3. Edit `public_html/index.php` and change the two paths near the top from
   `__DIR__.'/../vendor/autoload.php'` to
   `__DIR__.'/../zimrent/vendor/autoload.php'`, and
   `__DIR__.'/../bootstrap/app.php'` to
   `__DIR__.'/../zimrent/bootstrap/app.php'`.
4. Everything else stays the same.

This is worth avoiding if you can: it splits the site across two folders and
makes updates fiddlier.
