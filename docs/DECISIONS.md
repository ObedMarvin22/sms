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

## 2026-10-30 — Permission team = current school, via a team resolver

- **Decision:** `config/permission.php` uses `App\Support\Tenancy\CurrentSchoolTeamResolver`,
  which returns `CurrentSchool::id()` (an explicit `setPermissionsTeamId()` overrides it, which
  Spatie's own artisan commands use). `IdentifySchool` does not call `setPermissionsTeamId()`:
  setting `CurrentSchool` is enough. This is how the roadmap's "team id set in IdentifySchool" is met.
- **Reason:** One source of truth. Spatie's registrar is a long-lived singleton, so a team id set
  in middleware would carry into the next queued job, whereas `CurrentSchool` is scoped and reset.
  A probe confirmed 11 tests fail with Spatie's default resolver.
- **Alternatives:** `setPermissionsTeamId()` in `IdentifySchool` (plus remembering to reset it in
  every job and console command).

## 2026-10-30 — Default roles and permissions

- **Decision:** Nine school roles (`RoleKey`) and twenty shared permissions (`PermissionKey`);
  the default table lives in `RoleKey::defaultPermissions()` and was agreed with Obed
  (Sprint 1 step 6 plan). Notable choices: headteacher sees finance but cannot change fees or
  record payments; gate staff only release children; parents only get `portal.children` (which
  children they see comes from guardian links). Proprietor, headteacher, administrator and bursar
  `requiresTwoFactor()`. The two platform roles come with the console guard in step 7.
  `SeedDefaultRoles` adds missing permissions and roles but never changes an existing role's
  permissions, because schools may have edited them.
- **Reason:** SPEC section 5. Re-running the Action must be safe after a school customises roles.
- **Note:** Adding a new default permission later needs a separate, deliberate step to grant it
  to existing schools' roles (re-running SeedDefaultRoles will not).

## 2026-10-30 — Platform users and the console

- **Decision:** Orange Tree staff are `users` rows with `school_id` null and a `platform_role`
  column (`PlatformRoleKey`: `super_admin`, `support`). They sign in on `admin.APP_BASE_DOMAIN`
  with a separate `platform` session guard that reuses the scoped user provider (no current
  school there, so it only finds platform users). Only `super_admin` may create schools
  (`create-schools` gate). The first super admin is created with `php artisan
  platform:create-admin`, which prompts in the terminal so no password passes through chat.
- **Reason:** Spatie role assignments include the team id in their primary key, so a platform
  user (no school) cannot hold a Spatie role. Two fixed roles do not need a role system.
- **Deferred:** support's time-limited, logged "view as" (SPEC section 5) is not in the roadmap
  yet; raise it when the console grows.

## 2026-10-30 — `{school}` never matches a reserved subdomain

- **Decision:** A global route pattern for `{school}` excludes `ReservedSubdomains::ALL`
  (`admin`, `www`, `api`, `mail`, `app`, `staging`, `test`), set in `AppServiceProvider::register()`.
  `CreateSchool` also rejects them.
- **Reason:** Fortify registers its `{school}.domain/login` route before `routes/web.php` loads, so
  `admin.localhost/login` was caught by the school group and returned 404. The pattern must be in
  `register()`: route patterns apply when a route is created, and Fortify creates its routes
  while booting.

## 2026-10-30 — Invitations: signed URL + password fingerprint

- **Decision:** `SchoolInvitation` emails a `URL::temporarySignedRoute` link (7 days) to
  `{school}.domain/invitation/{user}/{fingerprint}`. The fingerprint is an HMAC (app key) of the
  user's current password hash, so the link stops working once a password is set; no table is
  needed. The page checks the user belongs to the current school (route model binding is not
  school-scoped). Accepting sets the password, marks the email verified and signs the user in.
  `CreateSchool` sends the invitation only after its transaction commits.
- **Reason:** Laravel password-reset tokens expire in 60 minutes and share the email-keyed table
  (step 5 limit). Signed URLs also bind the host, so a link cannot be used on another school.
- **Alternatives:** An `invitations` table with tokens (more moving parts for the same result).

## 2026-10-30 — Spatie roles always use the "web" guard

- **Decision:** `User::$guard_name = 'web'`.
- **Reason:** Spatie otherwise uses the active guard. When a console super admin (platform guard)
  created a school, assigning the "administrator" role looked for it on the platform guard and failed.

## 2026-10-30 — Model date types are CarbonImmutable

- **Decision:** `User` and `School` docblocks type dates as `CarbonImmutable`.
- **Reason:** The starter kit calls `Date::use(CarbonImmutable::class)`, so Eloquent returns
  immutable dates; its docblocks said `Illuminate\Support\Carbon`. PHPStan caught the mismatch.

## 2026-10-30 — Activity log: school-aware model, users and roles

- **Decision:** `activity_log.school_id` (nullable, indexed, no foreign key so the trail outlives
  what it describes). `App\Models\Activity` extends Spatie's model: it applies `SchoolScope` and,
  on create, takes the current school or else the subject's `school_id` (so a console change to a
  school's user shows up in that school's log); platform-only events keep null. It is exempt from
  the `BelongsToSchool` arch rule for that reason.
  - **Users** (`LogsActivity`, log "users"): only `name`, `email`, `platform_role`,
    `two_factor_confirmed_at`, dirty values only. A password change is a separate
    `password_changed` entry with no value. Secrets are never logged (tested).
  - **Roles** (log "roles"): `config/permission.php` `events_enabled = true`;
    `App\Listeners\LogRoleChanges` records role assigned/removed and permission granted/revoked,
    with names in `properties`.
  The causer is whoever is signed in on the active guard (school user or platform user).
- **Reason:** CLAUDE.md requires logging permission changes; SPEC section 14 lists the activity log.
- **Note:** Creating a school logs its default permission grants (one entry per role). That is an
  accurate record of who set the school up, so it is kept.
- **Note:** `school_id` and `platform_role` are not mass-assignable on `User`; `update([...])`
  silently ignores them. Set them explicitly (a test tripped over this).

## 2026-10-30 — Required two-factor for money and approval roles

- **Decision:** The starter kit's Fortify two-factor is kept. `EnsureTwoFactorEnabled`
  (`two-factor.required`) sits on the signed-in staff route group (dashboard now, staff screens
  later) and on Livewire's persistent list. If any of the user's roles *in the current school*
  `requiresTwoFactor()` (proprietor, headteacher, administrator, bursar) and two-factor is not
  confirmed, the user is sent to Settings → Security, which shows a permanent "required" notice
  until it is done. Settings, Fortify's two-factor routes and logout are in other route groups,
  so they are never blocked; no allow-list is needed.
- **Reason:** SPEC section 5. Roles are per school, so a bursar elsewhere who teaches here is not
  forced to set it up here (tested).
- **Known gap:** the flash message is used up by the password-confirmation redirect, so the
  "Confirm password" screen does not say why it appeared. The security page notice explains it
  right after. Revisit with the Sprint 2 app shell.
- **Deferred:** two-factor for Orange Tree console users. The console has its own login and would
  need its own challenge. Recommended for super admins before go-live.
- Sprint 4 step 10 ("Staff 2FA if not done in Sprint 1") is therefore already covered.
