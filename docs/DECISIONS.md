# Decisions

Non-obvious choices made during the build, newest last. Each entry: date, decision, reason,
alternatives considered. The "Fixed decisions" in `CLAUDE.md` are not repeated here.

---

## 2026-10-29 — Laravel 13 on PHP 8.4

- **Decision:** Build on Laravel 13 (the latest major) with PHP 8.4, locally, in CI and on the server.
- **Reason:** Laravel 13 supports PHP 8.3–8.5 and gets security fixes until Q1 2028. Laravel 12's
  security fixes end in February 2027, a month after the first school goes live. PHP 8.4 is
  mature enough that the SPEC packages support it.
- **Alternatives:** Laravel 12 on the existing local PHP 8.2.12 (rejected because it reaches end
  of life right after launch). PHP 8.5 (rejected for now because some packages may lag behind).
- **To confirm:** that Stellar Plus's cPanel "Select PHP Version" offers 8.4.

## 2026-10-29 — MySQL 8.0 everywhere

- **Decision:** Use MySQL 8.0 for local development, the `orange_sms_test` database and the CI
  service, to match the server.
- **Reason:** The XAMPP install has MariaDB 10.4. It behaves differently from MySQL 8.0 in places
  that matter here: JSON columns, default collations, and `SELECT ... FOR UPDATE` locking
  (which admission and receipt numbers depend on). Tests should run on the same engine as production.
- **Alternatives:** XAMPP's MariaDB 10.4 (wrong engine, and end of life since 2024).
- **Note:** MySQL 8.0 itself reached end of life in April 2026. Ask Namecheap whether 8.4 LTS
  is available. Moving from 8.0 to 8.4 needs no code changes for this app.

## 2026-10-29 — PHP 8.5 locally, guarded to 8.4

- **Decision:** Local PHP is 8.5.12 (XAMPP). `composer.json` sets `config.platform.php` to
  `8.4.1`, so Composer only installs package versions that run on the server's 8.4. CI will run
  on PHP 8.4, so 8.5-only syntax (for example the pipe operator `|>`) fails there.
- **Reason:** Obed's choice. It keeps the existing XAMPP install and still protects the server.
- **Alternatives:** Install PHP 8.4 locally for an exact match.

## 2026-10-29 — Livewire class components

- **Decision:** Starter kit installed with `--livewire-class-components`. Each component is a PHP
  class in `app/Livewire` plus a Blade view, not a single-file component.
- **Reason:** It matches the `app/Livewire/<Module>/` layout in SPEC section 15, and it separates
  logic from markup the way a Razor code-behind does, which keeps Actions easy to call and test.
- **Alternatives:** The default single-file components, which put PHP and Blade in one file.

## 2026-10-29 — Project created without the installer's MySQL flag

- **Decision:** Ran `laravel new` without `--database=mysql`, then switched `.env` and
  `.env.example` to `mysql` / `orange_sms` by hand. Deleted the generated `database/database.sqlite`.
- **Reason:** With `--database=mysql`, the installer runs migrations straight away. Here those
  would have gone into XAMPP's MariaDB 10.4, the wrong engine. MySQL 8.0 setup is step 4.
- **Alternatives:** None worth the side effects.

## 2026-10-29 — Starter kit extras kept

- **Decision:** Kept what the Livewire starter kit ships: `livewire/flux`, `livewire/blaze`,
  `larastan/larastan` with a `phpstan.neon` at **level 7**, and `.github/workflows/tests.yml`.
- **Reason:** They are part of the official starter kit, not packages we chose. Larastan is
  listed in SPEC section 2 anyway.
- **Note:** The workflow (PHP 8.5, SQLite) is replaced in step 5 by the SPEC section 16 CI
  (PHP 8.4, MySQL 8.0).

## 2026-10-29 — Larastan at level 7, not 5

- **Decision:** Keep the starter kit's `phpstan.neon` at level 7 (Obed's choice).
- **Reason:** The code passes at level 7 already. Staying strict from the start is cheaper than
  raising the level later and fixing a backlog. Level 7 also catches union-type mistakes (it
  found the `env()` bool/string issue in `config/sanctum.php`).
- **Alternatives:** Level 5 as SPEC section 2 suggests.

## 2026-10-29 — Permission teams enabled before the first migration

- **Decision:** `config/permission.php` sets `teams => true` and `team_foreign_key => school_id`
  now (Sprint 0), although the roles work is Sprint 1 step 6.
- **Reason:** The published permission migration reads these settings when it runs, so it adds
  `school_id` to `roles`, `model_has_roles` and `model_has_permissions` from the very first
  `migrate`. Turning teams on later would need an extra migration that alters those tables.
- **Alternatives:** Wait for Sprint 1 and write an alter migration.

## 2026-10-29 — Dedicated local MySQL user

- **Decision:** The app connects as `orange_sms`, which has full rights on `orange_sms` and
  `orange_sms_test` only, not as `root`. The password is random and lives only in `.env`.
  `.env.example` keeps `DB_PASSWORD` empty, and a test checks that it stays empty.
- **Reason:** Least privilege, and the root password never has to be shared or stored in the
  project. It also matches the server, where cPanel gives each app its own user.
- **Alternatives:** Use `root` locally (simpler, but a test or migration bug could touch other
  databases).

## 2026-10-29 — Tests override queue, session, cache and mail

- **Decision:** `phpunit.xml` keeps the starter kit's test overrides: `QUEUE_CONNECTION=sync`,
  `SESSION_DRIVER=array`, `CACHE_STORE=array`, `MAIL_MAILER=array`. Only the database moves
  to MySQL (`orange_sms_test`).
- **Reason:** Tests stay fast and isolated, and `Queue::fake()` / `Mail::fake()` still work. The
  `database` drivers used in production are checked through `.env.example` by
  `tests/Feature/EnvironmentTest.php`.
- **Alternatives:** Run tests on the database drivers too (slower, with no real gain).

## 2026-10-29 — CI shape

- **Decision:** One `tests` job on every push and pull request, with a `mysql:8.0` service and
  PHP 8.4, and Pint, PHPStan and Pest as separate steps. Database credentials come from
  job-level environment variables, which override `.env` (copied from `.env.example`). Action
  versions stay pinned to commit SHAs (from the starter kit), and the kit's
  `.github/dependabot.yml` keeps them up to date weekly.
- **Reason:** Matches SPEC section 16 and the server. Separate steps show at a glance which
  check failed. SHA pinning protects against a tampered action tag.
- **Alternatives:** The starter kit's `composer ci:check` single step (less clear failures).

## 2026-10-29 — Arch tests in their own suite

- **Decision:** `tests/Arch` is a separate `Arch` suite in `phpunit.xml`, so it is not covered by
  `RefreshDatabase` and runs in under a second (`php artisan test --testsuite=Arch`). It starts
  with "no debug calls" and Pest's PHP preset. The `BelongsToSchool` and backed-enum rules
  arrive in Sprint 1 with the code they check.
- **Note:** Pest arch rules only inspect namespaced classes (checked with a probe class).
- **Note:** The starter kit's Fortify classes in `app/Actions/Fortify` use `create()`/`reset()`
  as Fortify requires, not `handle()`. The Sprint 1 Actions rule must exempt them.

## 2026-10-30 — `schools` table shape

- **Decision:** `status` is a `string(20)` column (default `trial`, indexed) cast to
  `SchoolStatus`, not a MySQL `ENUM`. `levels_offered` is JSON cast with
  `AsEnumCollection::of(SchoolLevel::class)`. Colours are two `string(7)` hex columns. `plan` is a
  nullable string until the `plans` table exists (Sprint 4). `paystack_subaccount_code` is nullable
  and unique. `sms_sender_id` is `string(11)` (the SMS sender ID limit). `subdomain` is
  `string(63)` (the DNS label limit) and unique.
- **Reason:** New statuses need no `ALTER TABLE`, and the PHP enum stays the single source of
  truth. Typed casts mean code never compares raw strings.
- **Alternatives:** MySQL `ENUM` columns; colours and plan inside `settings` JSON (harder to
  validate and query).

## 2026-10-30 — All enums are string-backed

- **Decision:** Every enum in `App\Enums` is string-backed, enforced by an arch test.
- **Reason:** Readable values in the database, logs and API responses. A unit test pins each
  enum's stored values, because renaming one would orphan existing rows.
- **Alternatives:** Int-backed enums (smaller, but opaque in data and the API).

## 2026-10-30 — `CurrentSchool` is scoped, and `require()` throws

- **Decision:** `CurrentSchool` is registered with `$this->app->scoped(...)`. Code that must have a
  tenant calls `require()`, which throws `NoCurrentSchool`. `get()` and `id()` return null for
  code that may legitimately run without one (platform/console code).
- **Reason:** The queue worker on the server is one process handling many jobs. A scoped
  binding is reset before each job, so one school's context can never leak into another
  school's job. A test proves this (it fails if the binding is changed to `singleton`).
  Failing loudly is safer than silently running a query without a tenant.
- **Alternatives:** `singleton` (leaks across jobs); a static/global helper (hidden state, hard to
  test); a tenancy package (ruled out in CLAUDE.md).
- **Open:** Queued jobs must carry the school id and restore it when they run. This is added with
  the first tenant-aware job (Sprint 3 import / Sprint 4 billing).
