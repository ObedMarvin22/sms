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

## 2026-10-30 — How the school is resolved (IdentifySchool)

- **Decision:** School routes (web, API and Fortify's auth routes) live in a
  `Route::domain('{school}.'.base_domain)` group with the `school` middleware alias.
  `IdentifySchool` reads the `{school}` route parameter, removes it from the route (so
  controllers and Livewire components never receive it), looks up the school, and:
  - 404 for an unknown subdomain
  - 403 suspended page for a suspended school (JSON on the API)
  - otherwise fills `CurrentSchool`, shares `$currentSchool` with views, and sets
    `URL::defaults(['school' => ...])`
  It is placed before authentication in the middleware priority list. The bare domain only
  serves the landing page.
- **Reason:** Using the route's own domain parameter avoids hand-parsing the Host header, and
  Laravel only matches it for hosts ending in the base domain. `URL::defaults` keeps every
  `route()` call on the right subdomain without passing the school around.
- **Alternatives:** Parsing `$request->getHost()` in a global middleware (runs on every route,
  including the bare domain and Livewire assets).

## 2026-10-30 — Livewire updates re-resolve the school

- **Decision:** `IdentifySchool` is registered with `Livewire::addPersistentMiddleware()`.
- **Reason:** Livewire posts component updates to its own `/livewire-…/update` endpoint, which
  has no domain, so route middleware does not run there. Persistent middleware makes Livewire
  rebuild the original page's request (on the current host) and re-run `IdentifySchool`.
  Checked in the browser: the profile form's update request on Greenhill succeeded.
- **Open:** Livewire's `upload-file` and `preview-file` endpoints are also domain-less. Review
  them when the logo upload is built (Sprint 2).

## 2026-10-30 — Test URLs

- **Decision:** `phpunit.xml` sets `APP_URL=http://localhost`. `TestCase::onSchool()` and the
  `OnSchoolSubdomain` trait send test requests to a school's subdomain. The starter kit's tests
  use the trait. The logout test now expects a redirect to the school's own `/`.
- **Reason:** With `APP_URL=http://localhost:8000` from `.env`, some generated URLs carried
  `:8000` and others did not, so tests depended on the dev server's port.

## Open items noted in Sprint 1 step 3

- Public registration is still open on every school (starter kit). Step 5 decides who may
  create accounts (staff are invited).
- `admin` is not yet a reserved subdomain. Step 7 reserves it for the console (and `www`, `api`).
- Emails use `APP_URL` for the header link, not the school's subdomain. Fix with school-branded
  emails.

## 2026-10-30 — `BelongsToSchool` rules

- **Decision:** The trait adds `SchoolScope` (filters by the current school when one is set) and
  model events:
  - **creating:** fill `school_id` from `CurrentSchool`. No current school and no `school_id` →
    `NoCurrentSchool`. A `school_id` for a different school than the current one →
    `CrossSchoolWrite`. With no current school, an explicit `school_id` is allowed (seeders,
    platform code).
  - **updating:** changing `school_id` → `CrossSchoolWrite`.
  `SchoolScope` lives in `App\Support\Tenancy`, outside `App\Models`, so the arch test only sees
  real models. Platform models are listed in `tests/Arch/TenancyTest.php` (`School`, and `User`
  until step 5).
- **Reason:** SPEC section 4, plus failing loudly on the two ways a tenant leak usually happens
  (writing with the wrong id, moving a row).
- **Known limit:** bulk `query()->update([...])` skips model events, so it could change `school_id`
  on the current school's own rows. The scope still stops it touching other schools' rows (tested).
  Never bulk-update `school_id`.
- **Alternatives:** Throw when querying with no current school (stricter, but breaks console
  commands, seeders and the platform console, which SPEC says see all schools).

## 2026-10-30 — Testing the trait with a temporary-table fixture

- **Decision:** `Tests\Fixtures\Models\TenantNote` uses the trait on a MySQL TEMPORARY table
  created per test, with no secondary indexes. `phpstan.neon` analyses `tests/Fixtures` so the
  trait is type-checked through it.
- **Reason:** No real school-owned model exists yet. `CREATE TEMPORARY TABLE` does not commit
  the test transaction, but Laravel adds indexes with a separate `ALTER TABLE`, which does. That
  made RefreshDatabase re-run `migrate:fresh` before every test (34 s for 13 tests, now 3.6 s).
- **Lesson for later:** anything that commits inside a test (DDL, some locking tests) silently
  turns on per-test re-migration. The Sprint 3 concurrency test for admission numbers will need
  real commits; give it its own approach (DatabaseTruncation or a separate connection).

## 2026-10-30 — Users per school: scoped user provider + membership check

- **Decision:** `users.school_id` is nullable (null = Orange Tree platform user) with a foreign key
  and a unique index on `(school_id, email)`. `school_id` is not mass-assignable.
  `config/auth.php` uses the `school_eloquent` driver (`App\Support\Tenancy\SchoolUserProvider`),
  which overrides `newModelQuery()` so every auth lookup (login, session, remember-me, password
  reset) only finds users of the current school, or only platform users when no school is set.
  `EnsureUserBelongsToSchool` (`school.member`) runs after authentication on every school route
  group (web, API, Fortify) and as Livewire persistent middleware: a session from another school
  is logged out, an API token from another school gets 403.
- **Reason:** One override covers every lookup, instead of patching login, reset and session
  separately. The middleware is needed for Sanctum tokens, which load their user from the token
  without going through the provider. A probe confirmed the tests fail (5 of 15) if the provider
  is switched back to plain `eloquent`.
- **Alternatives:** Fortify `authenticateUsing()` (covers login only, not session or reset);
  `BelongsToSchool` on `User` (cannot express platform users with a null school).

## 2026-10-30 — Public registration and passkeys turned off

- **Decision:** `Features::registration()` and `Features::passkeys()` are commented out in
  `config/fortify.php`. The login page hides "Sign up" and the passkey button; the
  `.well-known/passkey-endpoints` route only exists when passkeys are on. `CreateNewUser` still
  assigns the current school in case registration is ever re-enabled.
- **Reason:** Staff are invited and parents activate by phone (SPEC section 5), so open
  self-registration would let anyone create an account at any school. Passkey login finds the
  user by credential, bypassing the scoped provider.
- **Revisit:** Passkeys can return once their login lookup is school-scoped.

## Known limits recorded in Sprint 1 step 5

- `password_reset_tokens` is keyed by email only. If the same email exists in two schools, a reset
  requested on one replaces the other's pending link. Both still reset the correct account (the
  lookup is scoped). A nuisance, not a leak.
- Platform users' emails are not unique at database level (MySQL allows repeated NULLs in a unique
  index). Step 7 must validate uniqueness among platform users.
