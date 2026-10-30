# Deployment

This file covers **staging** only (Sprint 2 step 9). Production setup, the release script and
backups come with "Pilot preparation" in `docs/ROADMAP.md`.

Staging is a full copy of the app on Namecheap Stellar Plus behind Cloudflare, using test
services only: no real email, no real SMS, Paystack in test mode.

Throughout, `staging.example.com` stands for the staging base domain you choose. Schools will be
reached at `<school>.staging.example.com` and the console at `admin.staging.example.com`.

---

## 1. Domain and Cloudflare

- [ ] Choose the staging base domain (a separate domain, or a subdomain of the real one).
- [ ] In Cloudflare DNS, add records for the base domain **and** a wildcard `*` pointing to the
      Stellar Plus server IP (from cPanel → "Server Information"). Both **proxied** (orange cloud).
- [ ] SSL/TLS → Overview: set **Full (strict)**.
- [ ] SSL/TLS → Origin Server: create an **Origin certificate** for `staging.example.com` and
      `*.staging.example.com` (15 years). Keep the certificate and private key for step 2.
- [ ] SSL/TLS → Edge Certificates: turn on **Always Use HTTPS**. (HSTS later, for production.)

## 2. cPanel (Stellar Plus)

- [ ] **Select PHP Version**: PHP **8.4**. Extensions on: `bcmath`, `curl`, `exif`, `fileinfo`,
      `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `zip`. Confirm 8.4 is offered
      (still open from Sprint 0).
- [ ] **MySQL Databases**: create a database and a user, give the user **all privileges** on that
      database only. Note the full names (cPanel prefixes them with your account name).
      Confirm the server version is MySQL 8.0 or later (phpMyAdmin → "Server version").
- [ ] **Domains**: add the staging domain and the wildcard `*.staging.example.com`, both with
      document root **`~/app/public`**.
- [ ] **SSL/TLS → Manage SSL sites**: install the Cloudflare Origin certificate and key for the
      domain and the wildcard.
- [ ] **Terminal** (or SSH): check `php -v` shows 8.4 and `composer -V` works. If `composer` is
      missing, install it to `~/bin/composer` from getcomposer.org.

## 3. Code

- [ ] Create an SSH key on the server (`ssh-keygen -t ed25519`) and add the public key to the
      GitHub repo as a **read-only deploy key**.
- [ ] Clone into `~/app`:

  ```bash
  git clone git@github.com:ObedMarvin22/sms.git ~/app
  ```

- [ ] Install PHP dependencies (no dev tools on servers):

  ```bash
  cd ~/app && composer install --no-dev --optimize-autoloader --no-interaction
  ```

## 4. Front-end assets

The server has no Node (SPEC section 3), and `public/build` is not in Git. Build on your machine
and upload it:

```bash
npm ci
npm run build
scp -r public/build <cpanel-user>@<server>:~/app/public/
```

(Or upload the `public/build` folder with cPanel File Manager.) Repeat after every change that
touches CSS, JS or Blade classes. A later step can have CI build and ship this automatically.

## 5. `.env` for staging

- [ ] Copy the example and edit it on the server: `cp ~/app/.env.example ~/app/.env`
- [ ] Set at least:

  | Key | Staging value |
  | --- | --- |
  | `APP_NAME` | `"Orange Tree SMS (staging)"` |
  | `APP_ENV` | `staging` |
  | `APP_DEBUG` | `false` |
  | `APP_URL` | `https://staging.example.com` |
  | `APP_BASE_DOMAIN` | `staging.example.com` |
  | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | the cPanel database and user from step 2 |
  | `QUEUE_CONNECTION`, `SESSION_DRIVER`, `CACHE_STORE` | `database` (already the default) |
  | `SESSION_SECURE_COOKIE` | `true` |
  | `MAIL_MAILER` and `MAIL_*` | a **test inbox** service (e.g. Mailtrap sandbox), never a real provider |
  | `MAIL_FROM_ADDRESS` | an address on the staging domain |
  | `SMS_DRIVER` | `log` |
  | `VAPID_*` | leave empty until web push (Sprint 7) |

- [ ] Paystack (from Sprint 4): **test** keys only (`sk_test_…`, `pk_test_…`). Never live keys on
      staging.
- [ ] Protect the file: `chmod 600 ~/app/.env`

## 6. First run

```bash
cd ~/app
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
php artisan platform:create-admin
```

`platform:create-admin` asks for your name, email and password in the terminal.
Do **not** run `db:seed` on staging unless you want the demo schools (it is blocked in
production but allowed on staging; demo accounts use the password `password`).

Folder permissions, if cPanel needs them: `chmod -R 775 ~/app/storage ~/app/bootstrap/cache`.

## 7. Cron

- [ ] cPanel → **Cron Jobs** → "Once per minute", command:

  ```bash
  cd ~/app && php artisan schedule:run >> /dev/null 2>&1
  ```

  The scheduler (`routes/console.php`) starts `queue:work --stop-when-empty --max-time=50` each
  minute, without overlapping, so queued emails and jobs run with no long-running process.

## 8. Smoke test

- [ ] `https://admin.staging.example.com` shows the console login; log in.
- [ ] Create a school (e.g. `ridge`). The invitation email arrives in the test inbox within a
      minute (it is sent straight away; the cron covers queued work later).
- [ ] Open the invitation link: it goes to `https://ridge.staging.example.com/...`, set a password.
- [ ] You are asked to set up two-factor (administrator role). Do it with an authenticator app.
- [ ] Work through **Setup checklist** to "Your school is ready".
- [ ] Upload a logo on School profile; it shows on the login page.
- [ ] `https://nowhere.staging.example.com` returns 404.
- [ ] `https://staging.example.com/storage/...` and `https://staging.example.com/.env` are not readable.
- [ ] `php artisan about` on the server shows `staging`, debug off, drivers `database`.

## 9. Updating staging

Until the release script exists (Pilot preparation):

```bash
cd ~/app
php artisan down --retry=60
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize
php artisan up
```

Then upload `public/build` again if assets changed (step 4).

## Not covered yet

- Production setup, `deploy.sh` and HSTS (Pilot preparation).
- Backups to Google Drive or Cloudflare R2 (`spatie/laravel-backup`), monthly test restore.
- Bulk email through Brevo or Mailgun, and the SMS provider (Sprint 4).
- CI building and shipping `public/build`.
