# Deployment

How to set up **staging** and **production** on Namecheap Stellar Plus behind Cloudflare, release
new versions, roll back, and keep backups. Staging was written in Sprint 2 step 9; production,
releases and backups in Pilot preparation step 1.

- **Staging** is a full copy of the app using test services only: no real email, no real SMS,
  Paystack in test mode. Try every release here first.
- **Production** is what schools and parents use: real email, Paystack live keys, SMS through
  Arkesel, nightly off-site backups.

The two are set up the same way (sections 1–7). Where they differ, the step says so. Throughout,
`staging.example.com` and `orangetree.example` stand for the base domains you choose. Schools
are reached at `<school>.<base domain>` and the console at `admin.<base domain>`.

**Who does what.** Everything on the server, and every live key, is set by Obed. Nothing in this
repository connects to the server by itself; `scripts/release.sh` only runs when you start it.

---

## 1. Domain and Cloudflare

- [ ] Choose the base domain (staging: a separate domain or a subdomain of the real one).
- [ ] In Cloudflare DNS, add records for the base domain **and** a wildcard `*` pointing to the
      Stellar Plus server IP (from cPanel → "Server Information"). Both **proxied** (orange cloud).
- [ ] SSL/TLS → Overview: set **Full (strict)**.
- [ ] SSL/TLS → Origin Server: create an **Origin certificate** for the base domain and
      `*.<base domain>` (15 years). Keep the certificate and private key for step 2.
- [ ] SSL/TLS → Edge Certificates: turn on **Always Use HTTPS**. Leave Cloudflare's own HSTS
      setting **off**: the app sends the HSTS header itself in staging and production
      (`SecurityHeaders` middleware, 6 months, subdomains included, no preload yet).

## 2. cPanel (Stellar Plus)

- [ ] **Select PHP Version**: PHP **8.4**. Extensions on: `bcmath`, `curl`, `exif`, `fileinfo`,
      `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `zip`.
- [ ] **MySQL Databases**: create a database and a user, give the user **all privileges** on that
      database only. Note the full names (cPanel prefixes them with your account name).
      Confirm the server version is MySQL 8.0 or later (phpMyAdmin → "Server version").
- [ ] **Domains**: add the base domain and the wildcard `*.<base domain>`, both with document root
      **`~/app/public`**.
- [ ] **SSL/TLS → Manage SSL sites**: install the Cloudflare Origin certificate and key for the
      domain and the wildcard.
- [ ] **Terminal** (or SSH): check `php -v` shows 8.4, `composer -V` works and `mysqldump --version`
      works (backups need it). If `composer` is missing, install it to `~/bin/composer` from
      getcomposer.org.
- [ ] **SSH access from your machine** (needed by `scripts/release.sh`): cPanel → **SSH Access** →
      Manage SSH Keys → Import your public key (`~/.ssh/id_ed25519.pub` on your machine) and
      authorise it. Namecheap shared hosting listens on port **21098**. Test from Git Bash:

  ```bash
  ssh -p 21098 <cpanel-user>@<server>.web-hosting.com "php -v"
  ```

  If SSH is not offered on the account, ask Namecheap support to turn it on.

## 3. Code

- [ ] Create an SSH key on the server (`ssh-keygen -t ed25519`) and add the public key to the
      GitHub repo as a **read-only deploy key**.
- [ ] Clone into `~/app` and install PHP dependencies (no dev tools on servers):

  ```bash
  git clone git@github.com:ObedMarvin22/sms.git ~/app
  cd ~/app && composer install --no-dev --optimize-autoloader --no-interaction
  ```

## 4. Front-end assets (first time)

The server has no Node (SPEC section 3), and `public/build` is not in Git. From then on
`scripts/release.sh` builds and uploads it (section 9). The very first time, upload it by hand:

```bash
npm ci
npm run build
scp -P 21098 -r public/build <cpanel-user>@<server>.web-hosting.com:~/app/public/
```

The same build includes the parent app (`resources/js/parent`). After an upload, parents' phones
pick up the new version the next time they open the app (the service worker's cache name follows
the build).

## 5. `.env`

- [ ] Copy the example and edit it on the server: `cp ~/app/.env.example ~/app/.env`
- [ ] Protect the file: `chmod 600 ~/app/.env`
- [ ] Set at least:

  | Key | Staging | Production |
  | --- | --- | --- |
  | `APP_NAME` | `"Orange Tree SMS (staging)"` | `"Orange Tree SMS"` |
  | `APP_ENV` | `staging` | `production` |
  | `APP_DEBUG` | `false` | `false` |
  | `APP_URL` | `https://staging.example.com` | `https://orangetree.example` |
  | `APP_BASE_DOMAIN` | `staging.example.com` | `orangetree.example` |
  | `LOG_LEVEL` | `debug` | `warning` |
  | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | cPanel database (step 2) | cPanel database (step 2) |
  | `QUEUE_CONNECTION`, `SESSION_DRIVER`, `CACHE_STORE` | `database` | `database` |
  | `SESSION_SECURE_COOKIE` | `true` | `true` |
  | `SESSION_LIFETIME` | `120` | `60` (minutes idle before sign-in again; SPEC section 14) |
  | `MAIL_MAILER`, `MAIL_*` | a **test inbox** (e.g. Mailtrap sandbox) | the real provider: **Brevo** or **Mailgun** SMTP |
  | `MAIL_FROM_ADDRESS` | an address on the staging domain | e.g. `no-reply@orangetree.example` (SPF/DKIM set up with the provider) |
  | `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY` | **test** keys (`pk_test_…`, `sk_test_…`) | **live** keys (`pk_live_…`, `sk_live_…`), set by Obed |
  | `SMS_DRIVER` | `log` (never real SMS) | `arkesel`, set by Obed once the sender ID is approved |
  | `ARKESEL_API_KEY` | empty | the Arkesel key, set by Obed |
  | `BACKUP_DISKS` | `local` | `r2` (section 10) |
  | `BACKUP_NOTIFY_EMAIL` | your email | your email |
  | `BACKUP_ARCHIVE_PASSWORD` | optional | a long password, **also kept somewhere off the server** |
  | `R2_*` | empty | from section 10 |
  | `SAAS_INVOICE_*` | Orange Tree's invoice details | Orange Tree's invoice details |
  | `SAAS_GRACE_DAYS` | `14` | `14` (days after an invoice's due date before staff screens become read-only) |
  | `VAPID_*` | empty until web push (Sprint 7) | empty until web push (Sprint 7) |

- [ ] Check it: `php artisan app:preflight` lists anything wrong (section 9 explains the checks).

## 6. First run

```bash
cd ~/app
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
php artisan platform:create-admin
```

`platform:create-admin` asks for your name, email and password in the terminal.
Do **not** run `db:seed` on production (it is blocked there). On staging it adds the two demo
schools (demo accounts use the password `password`).

Folder permissions, if cPanel needs them: `chmod -R 775 ~/app/storage ~/app/bootstrap/cache`.

## 7. Cron

- [ ] cPanel → **Cron Jobs** → "Once per minute", command:

  ```bash
  cd ~/app && php artisan schedule:run >> /dev/null 2>&1
  ```

  The scheduler (`routes/console.php`) runs everything else from this one entry:

  | When | What |
  | --- | --- |
  | every minute | `queue:work --stop-when-empty --max-time=50` (emails, SMS, billing chunks) |
  | every minute, weekdays | `attendance:notify-absences` |
  | 01:00 | `saas:census` (Orange Tree invoices) |
  | 02:00 | `paystack:reconcile` |
  | 02:30 | `backup:run` |
  | 03:00 | `sanctum:prune-expired` |
  | 03:15, 03:30 | `backup:clean`, `backup:monitor` |

## 8. Smoke test

After the first run, and after any release that changes sign-in, payments or SMS:

- [ ] `https://admin.<base domain>` shows the console login; log in.
- [ ] Create a school (e.g. `ridge`). The invitation email arrives (staging: in the test inbox)
      within a minute.
- [ ] Open the invitation link: it goes to `https://ridge.<base domain>/...`; set a password.
- [ ] You are asked to set up two-factor (administrator role). Do it with an authenticator app.
- [ ] Work through **Setup checklist** to "Your school is ready".
- [ ] Upload a logo on School profile; it shows on the login page.
- [ ] `https://nowhere.<base domain>` returns 404.
- [ ] `https://<base domain>/storage/...` and `https://<base domain>/.env` are not readable.
- [ ] `curl -sI https://ridge.<base domain>/login` shows `strict-transport-security`,
      `x-content-type-options: nosniff` and `x-frame-options: SAMEORIGIN`.
- [ ] `https://ridge.<base domain>/parent` opens the parent app; Chrome on Android offers to install it.
- [ ] `php artisan about` shows the right environment, debug off, drivers `database`.
- [ ] **Production only, by Obed:** a real Paystack payment of a small amount reaches the school's
      account, records a receipt, and (with SMS on) texts the parent.

## 9. Releasing

Releases go to staging first, then production, from `main` only.

### One command from your machine

Create `scripts/release.env` (not committed) once per server, e.g. for staging:

```bash
DEPLOY_HOST=<cpanel-user>@<server>.web-hosting.com
DEPLOY_PORT=21098
SITE_URL=https://staging.example.com
```

Then, in Git Bash at the project folder:

```bash
bash scripts/release.sh
```

It stops if you are not on `main`, have uncommitted changes, or have not pushed. Then it:

1. builds the front-end files (`npm ci && npm run build`);
2. uploads them to `~/app/storage/release/build` on the server;
3. runs `scripts/deploy.sh` on the server (below);
4. checks that `SITE_URL/up` answers 200, and tells you how to roll back if it does not.

For production, keep a second file and point the script at it, or change `release.env` before
running it again. Release to staging first and click through the smoke test.

### What `deploy.sh` does on the server

1. **`php artisan app:preflight`** while the site is still up. Errors stop the release with
   nothing changed. It checks: debug off, `APP_KEY` set, `APP_URL` on https and matching
   `APP_BASE_DOMAIN`, `database` queue/session/cache, secure cookies, writable folders; in
   production also real mail, a live Paystack key and complete backup settings; on staging no
   live Paystack key and no real SMS. Things you switch on later (Paystack, SMS, encrypted
   backups) only warn.
2. Maintenance mode, with a bypass link printed (`<APP_URL>/release-…`) so you can look around.
3. Notes the current commit in `~/app/.last-release` (for rollback).
4. `git pull --ff-only origin main` and `composer install --no-dev`.
5. `php artisan migrate --force`.
6. Swaps in the uploaded front-end files (the old ones are kept as `public/build.previous`).
7. `php artisan optimize`, `php artisan queue:restart`, back up.

If any step fails, the site **stays in maintenance mode** and the script says so. Fix the problem
and run it again, or roll back.

### Rolling back

```bash
ssh -p 21098 <cpanel-user>@<server>.web-hosting.com "bash ~/app/scripts/deploy.sh --rollback"
```

This returns the code and front-end files to the previous release (one step back only).
**Database migrations are not undone**: the old code runs on the newer database, which is safe
because migrations only ever add (CLAUDE.md). If a migration itself is the problem, restore from
a backup (section 10) instead, and ask before doing so on production.

### By hand (if SSH from your machine is not available)

Upload the build to `~/app/storage/release/build` with cPanel File Manager, then in cPanel
Terminal: `bash ~/app/scripts/deploy.sh`.

## 10. Backups (production)

Every night at 02:30 the database and each school's private files (`storage/app/private`: logos,
payment proofs, imports) are zipped, encrypted with `BACKUP_ARCHIVE_PASSWORD`, and copied to a
private **Cloudflare R2** bucket. Kept: every backup for 7 days, then one a day for 7 more days,
one a week for 4 weeks, one a month for 6 months. Only problems are emailed.

### Set up once

- [ ] Cloudflare → **R2** → Create bucket, e.g. `orange-tree-backups` (private; no public access).
- [ ] R2 → **Manage API tokens** → Create a token with **Object Read & Write** on that bucket only.
      Note the Access Key ID, Secret Access Key and the S3 endpoint
      (`https://<account-id>.r2.cloudflarestorage.com`).
- [ ] In production `.env`:

  ```bash
  BACKUP_DISKS=r2
  BACKUP_NOTIFY_EMAIL=you@example.com
  BACKUP_ARCHIVE_PASSWORD=<long random password; also store it in your password manager>
  R2_ACCESS_KEY_ID=...
  R2_SECRET_ACCESS_KEY=...
  R2_BUCKET=orange-tree-backups
  R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
  ```

- [ ] `php artisan optimize`, then run one backup now and check it arrived:

  ```bash
  php artisan backup:run
  php artisan backup:list
  ```

### Test restore (first Monday of each month)

A backup nobody has restored is not a backup. On **staging** (never on production):

- [ ] Download the newest zip from the R2 bucket (Cloudflare dashboard).
- [ ] Open it with the archive password (7-Zip). It holds `db-dumps/mysql-*.sql` and
      `app/private/...`.
- [ ] Import the SQL into the staging database (phpMyAdmin → Import, or
      `mysql -u <user> -p <database> < mysql-*.sql`), and copy `app/private` over
      `~/app/storage/app/private`.
- [ ] `php artisan optimize`, then log in to a school and open a student and a receipt.
- [ ] Note the date and the backup's date in this list:

  | Restored on | Backup from | By | OK? |
  | --- | --- | --- | --- |
  | | | | |

## Not covered yet

- CI building and shipping `public/build` (blocked while GitHub billing is locked).
- Two-factor for console users.
- HSTS preload (after production has run on HTTPS for some months).

