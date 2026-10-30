# Local development

## Prerequisites

- PHP 8.5 (XAMPP folder) with `gd`, `zip`, `intl`, `sodium`, `pdo_mysql` enabled. Composer is
  pinned to PHP 8.4.1 (`config.platform.php`) to match the server; CI runs PHP 8.4.
- MySQL 8.0 (Windows service `MySQL80`) on port 3306. Keep XAMPP's MariaDB stopped.
- Node 24 and npm.
- Mailpit: `C:/tools/mailpit/mailpit.exe` (SMTP on 1025, inbox at http://localhost:8025).
  It does not start by itself.

## First run

```bash
composer install
npm install
npm run build
php artisan migrate:fresh --seed
```

`.env` holds the `orange_sms` database user and password (never committed).
Tests use the `orange_sms_test` database.

## Running

```bash
php artisan serve
```

```bash
C:/tools/mailpit/mailpit.exe
```

| What | URL |
| --- | --- |
| Landing page (no school) | http://localhost:8000 |
| Greenhill Academy | http://greenhill.localhost:8000 |
| Hillview School | http://hillview.localhost:8000 |
| Orange Tree console | http://admin.localhost:8000 |
| Mailpit inbox | http://localhost:8025 |

`*.localhost` resolves to your machine in the browser; no hosts-file changes are needed.

Queued work (student imports, later bills and report cards) uses the `database` queue. On the
server cron runs it every minute; locally, keep a worker running while you test it:

```bash
php artisan queue:work --stop-when-empty
```

Tailwind only includes classes it found at the last build. After adding new classes to a
view, run `npm run build` (or keep `npm run dev` running) or they will have no styles.

## Demo accounts (`php artisan migrate:fresh --seed`)

All demo accounts use the password `password` (`DemoSchoolsSeeder::PASSWORD`). The seeder
refuses to run in production.

| Where | Email | Role | Notes |
| --- | --- | --- | --- |
| greenhill | `admin@greenhill.test` | Administrator | Must set up two-factor on first sign-in |
| greenhill | `teacher@greenhill.test` | Class teacher | Class teacher of Primary 4A; no two-factor required |
| hillview | `admin@hillview.test` | Administrator | Must set up two-factor on first sign-in |
| hillview | `teacher@hillview.test` | Class teacher | Class teacher of Primary 4A; no two-factor required |
| console | `admin@orangetree.test` | Super admin | Can create schools |

Each school is set up for the current school year (first term current, default classes and
subjects; Hillview also has SHS programmes) with 600 students and their guardians. Brothers and
sisters share guardian records, and guardian phones are made-up `+233…` numbers. A fixed random
seed per school gives the same students every time (Greenhill's first student is Grace Annan).

Seeding takes about 80 seconds on a laptop (every admission runs through the real Actions). For
a quicker reset set a smaller number in `.env`, e.g. `DEMO_STUDENTS_PER_SCHOOL=100`. The tests
use 24 (phpunit.xml).

To create a real console account (prompts for the password in your terminal):

```bash
php artisan platform:create-admin
```

## Checks (run before every commit)

```bash
composer test
```

This runs Pint (`--test`), PHPStan (level 7) and the Pest suite, the same checks as CI.
