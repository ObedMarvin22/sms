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

## 2026-10-30 — Demo seeder

- **Decision:** `DemoSchoolsSeeder` creates Greenhill (Creche, KG, Primary, JHS) and Hillview
  (Primary, JHS, SHS), both active, each with an administrator and a class teacher, plus an
  Orange Tree console super admin. All use the password `password`; credentials are in
  `docs/DEVELOPMENT.md`. It refuses to run in production and can run twice without duplicates.
  It skips the invitation email (sets a known password instead). The starter kit's
  `WithoutModelEvents` was removed from `DatabaseSeeder`.
- **Reason:** Roadmap Sprint 1 step 10. Model events must stay on while seeding:
  `BelongsToSchool` fills `school_id` and the activity log records changes through them.
  The class teachers give a quick sign-in without setting up two-factor.

## 2026-10-30 — Staff shell: theme and navigation

- **Decision:** `App\Support\Branding\SchoolTheme` turns `primary_colour` into Flux's accent CSS
  variables, written as an inline `style` on `<html>` (it beats both the light and `.dark` theme
  rules). Only a strict `#RRGGBB` value is used; anything else keeps the default theme. Text on the
  accent is black or white by WCAG contrast. `App\Support\Navigation\StaffNavigation` is the single
  list of sidebar items (label, icon, route, optional `PermissionKey`, group); an item shows only if
  its route exists and the user `can()` its permission in the current school, so items can be listed
  before their screen is built. The sidebar calls it directly.
- **Reason:** One place to maintain the menu; a page never shows a link the user cannot open.
- **Not a view composer:** planned as one, but `<x-layouts::app.sidebar>` renders under a hashed
  anonymous-component namespace (e.g. `e60dd9d2…::app.sidebar`), so a composer keyed on
  `layouts.app.sidebar` silently never ran (the menu test caught it). Calling the class from the
  layout is simpler and cannot silently break.
- **Reminder:** Tailwind only emits classes it saw at the last build. Run `npm run build` after
  adding classes (noted in docs/DEVELOPMENT.md); the badge was blank until the rebuild.

## 2026-10-30 — School profile, contacts and logos

- **Decision:** `schools` gets `phone` (`+233XXXXXXXXX`), `email` and `address` columns (Obed's
  choice over keeping them in the settings JSON). `App\Support\Phone::normalise()` accepts common
  Ghanaian formats and rejects anything else. `UpdateSchoolProfile` tidies input (trim, lowercase
  email) before validating. `App\Support\Settings\SchoolSettings` wraps `settings` JSON with
  defaults; features add their keys when built. `School` logs profile changes ("schools" log),
  and activity entries about a `School` take that school's id.
- **Logos:** PNG/JPG/WebP up to 1 MB (no SVG: it can carry scripts), stored on the private
  `local` disk at `schools/{id}/branding/logo.{ext}`; the previous file is deleted on replace.
  Served only by `{school}.domain/branding/logo`, which reads the *current* school's logo and
  takes no id or path, so one school cannot request another's file. Public, because the login
  page shows it. Laravel's own `/storage/...` route for the private disk refuses unsigned
  requests (403), and this app never signs logo URLs.
- **Livewire uploads (open item from Sprint 1 closed):** the temporary upload cap is 2 MB.
  I first added `auth:web,platform` to the upload route; a real upload in the browser then
  failed with 401. That route has no school domain, so `IdentifySchool` does not run and the
  school-scoped user provider cannot see school staff. `auth` was removed again. Protection
  rests on Livewire's signed, expiring upload URL (issued only on pages the user could open),
  private temporary storage, and the save Action's permission check and file validation.
- **Lesson:** any domain-less route cannot rely on `auth` for school staff. Keep school
  features inside the `{school}` route group.
- **Test speed:** tests that seed roles for two schools take ~0.9 s each (permission grants are
  activity-logged). Not re-migration (checked). Revisit if the suite becomes a burden.

## 2026-10-30 — Academic years and terms

- **Decision:** `academic_years` and `terms` are school-owned (`BelongsToSchool`). "Exactly one
  current year and term per school" is enforced by the database: `is_current` is stored as
  `true` or NULL (a model mutator turns `false` into NULL) with a unique `(school_id, is_current)`
  index, which allows many NULLs but one `true`. `SetCurrentTerm` clears the old rows, then sets
  the new term and its year, in one transaction with `lockForUpdate()`. A school chooses terms (3)
  or semesters (2) in `SchoolSettings` (`academic_period`, default terms); fixed once it has a year.
  A new year comes with all its terms; dates must sit inside the year, be in order without
  overlap, and each census date must fall inside its term. The first year's first term becomes
  current automatically. The form is prefilled from `App\Support\Academic\DefaultCalendar`
  (Ghanaian calendar, census = term start + 14 days, Obed approved). Year names: "2026/2027".
- **Reason:** SPEC section 6. A database guarantee beats an application check under concurrency.
- **Bugs caught by tests:** `SetCurrentTerm` first used `update(['is_current' => false])`, which
  is silently ignored because `is_current` is not mass-assignable (third time this trap appeared;
  use `forceFill`). `DeleteAcademicYear` trusted a stale model's `is_current`; it now asks the
  database.
- **Isolation tests:** `tests/Feature/Tenancy/IsolationTest.php` holds a dataset of school-owned
  models. Each new model must be added there (invisible, not updatable/deletable from another
  school, stamped with its school).

## 2026-10-30 — Classes and streams; no levels table

- **Decision:** No `levels` table (SPEC section 13 lists one). Levels are the fixed `SchoolLevel`
  enum; a school's offered levels are on `schools.levels_offered`; each class stores its `level`.
  `school_classes` (model `SchoolClass`; `class` is reserved in PHP) and `streams` are school-owned.
  Class names are unique per school (case-insensitive check in the Action, unique index on
  `(school_id, name)`); stream names unique per class and stored upper-case. Every class keeps at
  least one stream, because students are enrolled into a stream. `GenerateDefaultClasses` creates
  the usual Ghanaian classes for the offered levels (Creche; Nursery 1–2; KG 1–2; Primary 1–6;
  JHS 1–3; SHS 1–3), each with stream "A"; re-running only adds missing names. Sequence is
  level × 100 + position, so later classes still sort within their level.
- **Reason:** One source of truth for levels instead of a table that duplicates an enum. Obed
  approved the "Primary 1–6" naming.
- **Later:** from Sprint 3, enrolments will block deleting classes and streams that are in use.

## 2026-10-30 — Subjects and SHS programmes

- **Decision:** One `subjects` row per name per school, with a `levels` list (Obed approved), so
  "English Language" is one subject taught at Primary, JHS and SHS. Codes optional, unique per
  school. `programmes` (SHS only) link subjects through `programme_subject` as core or elective;
  that pivot carries `school_id` and is a `BelongsToSchool` pivot model. Preset GES subjects and
  six SHS programmes (approved lists in `App\Support\Academic\DefaultSubjects`) are safe to re-run.
  Programmes have no sidebar item; the Subjects screen links to them for SHS schools, and the
  page returns 404 otherwise.
- **Not verified in the browser** (usage limit): the Subjects and Programmes screens are covered
  by Livewire tests only so far.

## 2026-10-30 — Class–subject mapping

- **Decision:** `class_subject` (pivot model `ClassSubject`, `BelongsToSchool`) links a class to
  the subjects it takes. A subject may only be linked to a class at a level the subject is taught
  at. `ApplyDefaultClassSubjects` gives every class all its level's subjects (only adds).
  `SetClassSubjects` replaces one class's list and logs what was added and removed. SHS classes
  list every subject offered there (core and electives); which electives a *student* takes comes
  with their programme (Sprint 3 enrolment).
- **Later:** recorded scores (Sprint 5) must block removing a subject from a class.
- **Not checked in the browser:** covered by Livewire tests (the browser pane was not drawing
  reliably this session).

## 2026-10-30 — Setup wizard and "ready for students"

- **Decision:** `App\Support\Setup\SetupProgress` computes each `SetupStep` (profile: levels and a
  phone; academic year: a current term; classes; subjects; class subjects: every class has one).
  A step is open once all earlier steps are done. The wizard (`/setup`, "Setup checklist" in the
  sidebar) links each step to the screen already built for it and offers the same quick Actions
  (only for open steps). Completion is recorded once in `SchoolSettings` (`setup_completed_at`).
  Administrators see a dashboard banner until setup is complete; nobody is force-redirected.
  `EnsureSchoolReadyForStudents` (`school.ready`) needs a current term and a class; it redirects
  to the wizard (JSON 409 for APIs) and is applied to student routes from Sprint 3.
- **Reason:** Roadmap Sprint 2 step 7. One class decides "done" for the wizard, the banner and the
  middleware, so they cannot disagree.
- **Verified:** a test takes a brand-new school (created via the console Action) to complete
  setup using only the Actions; the browser showed the banner and correct step states on Hillview.

## 2026-10-30 — Permission checks enforced by tests

- **Decision:** `tests/Feature/Security/RouteGuardsTest.php` reads the route table: every signed-in
  school route must have a `can:` check unless it is on a short allow-list of things every staff
  member may use (dashboard, own account settings, two-factor setup, password confirmation,
  logout, `api/user`). Every school route must run `IdentifySchool` and `EnsureUserBelongsToSchool`;
  every console route except its login needs the platform guard. `SettingsAccessTest` builds the
  list of `can:settings.manage` screens from the routes and checks each one per role (six roles
  and a member without roles refused; proprietor, headteacher, administrator allowed; another
  school's administrator sent to login). Livewire keeps re-running `Authorize` on updates.
- **Reason:** Sprint 2 "teacher refused on settings (tested)", and future screens are covered
  without anyone remembering to add a test. A probe (removing the group's `can:`) made the guard
  test fail and list all eight settings routes.
- **Found:** no unguarded routes; nothing needed fixing.
- **Open:** whether parents may open the staff dashboard at all is decided with the parent app
  (Sprint 4).

## 2026-10-30 — Staging and the scheduled queue worker

- **Decision:** `routes/console.php` schedules `queue:work --stop-when-empty --max-time=50` every
  minute with `withoutOverlapping()` (SPEC section 3), driven by one cron `schedule:run` entry.
  `docs/DEPLOYMENT.md` is a hand-run staging checklist (Cloudflare wildcard + Origin cert + Full
  strict, cPanel PHP 8.4 and MySQL 8, deploy key and `git clone` into `~/app`, staging `.env`
  with test services only, first run, cron, smoke test, manual update steps).
- **Open:** front-end assets are built locally and uploaded (`public/build` is not in Git and the
  server has no Node). Automating this from CI is a follow-up (with the release script in Pilot
  preparation).

## 2026-10-30 — Staff, role granting and assignments

- **Decision:** `staff` (soft deletes) with optional `user_id`: staff with an email get a login,
  roles and the Sprint 1 invitation; without one they are records only (Obed's choice). Adding an
  email later gives them a login. `users.deactivated_at`: the school-scoped user provider skips
  deactivated users, so leavers cannot sign in and an open session ends on the next request.
  `MarkStaffLeft` deactivates the login and removes their assignments (record kept).
  `teaching_assignments` (one teacher per subject per stream per year, Obed's choice) and
  `class_teacher_assignments` (one per stream per year); a teacher can only be assigned to a
  subject the stream's class takes, and only active staff. Index names are given explicitly
  because MySQL limits them to 64 characters.
- **Role granting (changed with Obed during the step):** the strict "only roles whose permissions
  you hold" rule meant administrators could not give teacher roles (they lack attendance.take,
  grades.enter, pickup.release). Now only **sensitive** permissions must be held by the granter
  (`PermissionKey::isSensitive()`: settings, roles, staff, locked-attendance edits, grades
  approval, fees, payments, finance view, pickup restrictions, audit). So administrators can set
  up teachers, but cannot make anyone bursar. Never the Parent role; you cannot remove your own
  proprietor/administrator role.
- **Gotchas found:** `stream()` is a method on Livewire's base component (renamed to
  `selectedStream`); a new `Staff` model did not know its DB default status until
  `$attributes = ['status' => 'active']` was added.

## 2026-10-30 — Students and admission numbers

- **Decision:** `students` (soft deletes, unique `(school_id, admission_number)`) and
  `admission_sequences` (one counter per school and scope). Format per school in SchoolSettings:
  `admission_prefix` (default: initials of the school name) and `admission_number_format`
  (default `{PREFIX}/{YYYY}/{SEQ:4}`; tokens {PREFIX}, {YYYY}, {YY}, {SEQ:n}). With the year in the
  format, numbering restarts each year (scope = year), otherwise one sequence ("all").
  A typed-in number is kept if unique (also against deleted students); the generator skips numbers
  already in use. `GenerateAdmissionNumber` locks the counter row (`SELECT … FOR UPDATE`) inside
  the transaction that saves the student; the unique index is the safety net.
- **Concurrency bug found by the test:** two first-ever admissions in a scope both ran
  `INSERT IGNORE` on the counter and then `FOR UPDATE`, and MySQL killed one with a deadlock.
  Fix: select-for-update first, insert only when missing, and `DB::transaction(..., attempts: 5)`
  in `AdmitStudent` and the generator so MySQL deadlocks are retried. The worker script now exits
  non-zero on failure (it first printed the error and exited 0, which hid the crash).
- **Test design:** `tests/Concurrency` is its own suite outside `tests/Feature` (no rolled-back
  transaction): 5 real PHP processes admit 20 students each with real commits, expecting exactly
  0001–0100. Without the lock the test fails with a duplicate (verified). It cleans up its rows.
- **Screen:** Students (`students.view`; admit/edit with `students.manage`) behind `school.ready`.
  The add/edit modal is only rendered for users who can manage students.

## 2026-10-30 — Enrolments

- **Decision:** `enrolments` (school-owned): student, academic year, stream, optional programme
  (required for SHS streams, refused for others), `starts_on`/`ends_on`, `status`
  (`EnrolmentStatus`), reason, and `is_current` (true/NULL with a unique `(student_id, is_current)`
  index, as for terms). History is never overwritten: `App\Actions\Students\Enrolments::end()`
  closes the current enrolment, then `start()` opens the next. Admission now needs a stream and
  enrols into the current year (the admission date must fall inside it). `MoveStudent` (same
  year), `PromoteStudents` (promote / repeat same class / graduate, into a year that starts after
  the current one ends, new enrolment on that year's first day), `WithdrawStudent` (withdrawn,
  transferred to another school, graduated). Foreign keys restrict deletes; `DeleteClass`,
  `DeleteStream` and `DeleteAcademicYear` refuse anything with enrolments (promised in Sprint 2).
- **Screens:** the Students list shows the current class; the student page shows the history and
  (with `students.manage`) move / leaving; Promotion defaults each student to the first stream of
  the next class, or graduation from the last class.
- **Note:** tests that admit students must now set up a current year and classes; the concurrency
  test creates them with real commits and cleans them up.

## 2026-10-30 — Parent accounts are per school (Obed's decision)

- **Decision:** A guardian record (and later their parent-app login) belongs to one school. A
  parent with children at two Orange Tree schools has two separate accounts, possibly with the
  same phone number, and signs in on each school's subdomain.
- **Reason:** It fits the fixed decisions (parent PWA at `/parent` on each school's subdomain,
  sessions and tokens bound to one school, `EnsureUserBelongsToSchool`) and keeps each school in
  control of its own data under Act 843 (no school can change a parent's details at another).
- **Rejected:** one platform-level parent account across schools (would need a central parent
  domain, cross-school token rules and shared ownership of the parent's details).
- **Possible later:** a "switch school" convenience that links accounts with the same phone.

## 2026-10-30 — Guardian rules (Sprint 3 step 4)

- **One guardian per phone per school.** `unique (school_id, phone)`; the check also counts
  soft-deleted guardians, so an archived record is never duplicated (it will be restored instead
  when deleting guardians is built). Siblings share one guardian record. *Rejected:* matching on
  name too (names vary in spelling far more than phone numbers do).
- **Relationship, access level, primary contact and pickup belong to the link, not the guardian.**
  A father can have full access for one child and limited for another (e.g. a step-child).
  Access levels: full, limited (attendance and notices only), none (contact only). They are
  enforced in the parent API from Sprint 4.
- **`can_pick_up` defaults to yes** for guardians. Spec section 11 adds separately registered
  pickup persons later; this flag lets a school mark a guardian who must not collect a child
  (logged, like all pickup changes).
- **A child always keeps one guardian and exactly one primary contact.** The first guardian
  becomes primary; removing the primary passes it to the next oldest link. The is_primary
  column uses the true-or-NULL unique-index pattern, like `is_current`.
- **Links are saved as models, never `attach()`/`detach()`**, so BelongsToSchool fills school_id
  and the activity log records every change.
- **Deferred:** guardian ID numbers with encrypted casts (SPEC §9) until a screen needs them;
  deleting/archiving a guardian record.

## 2026-10-30 — Student import (Sprint 3 step 5)

- **A file with any error is refused whole (Obed's choice).** The dry run lists every problem
  by row and column; nothing is imported until the file is clean, so a school never ends up
  with half a class. *Rejected:* importing good rows and skipping bad ones.
- **The file is checked again when Import is clicked**, not trusted from the preview, and the
  upload is deleted once its rows are stored (`student_import_rows`), so pupils' data does not
  sit in a file on the server.
- **Rows are stored, then queued 100 per job without `Bus::batch`.** Each row is imported in
  its own transaction and only while `pending`, so a retried job never admits twice; the last
  job to find no pending rows marks the import finished. Progress is counted from the rows.
  *Rejected:* batches (extra moving parts; progress from rows is enough and survives retries).
- **A row that breaks between preview and import fails alone** (e.g. its admission number was
  typed in by hand meanwhile); the reason is shown and the rest continue.
- **Jobs carry the school id and run through `RunInSchool` job middleware**, which wraps the
  job in `CurrentSchool::runFor()`. Every school job implements `SchoolJob`; BelongsToSchool then
  scopes and guards everything inside the job, as in a request.
- **Guardians in the file:** at least one per student; a phone already on file (or earlier in
  the file) is linked, not an error; the same new phone with two different names is an error.
- **Accepted spellings:** Excel date cells, `YYYY-MM-DD` and `DD/MM/YYYY`; phone numbers Excel
  turned into numbers (`244123456`); `JHS 1A`/`jhs1a`, and a single-stream class without its
  letter; gender `F`/`M`; relationships by value or label.
- **Speed:** 600 rows import in about 20 s locally (≈3.5 s per 100-row job); the job timeout is
  45 s inside the worker's 50 s run. Re-measure on the server before the pilot.
- **Only `.xlsx`.** One format to support and test; CSV loses leading zeros and dates.
- **`AdmitStudent::prepare()`** holds every admission check without saving, so the dry run
  cannot drift from real admission rules.

## 2026-10-30 — Attendance register (Sprint 3 step 6)

- **One register a day, in the morning (Obed's choice).** Latecomers are marked Late.
  `attendance_sessions` is unique per (school, stream, date). *Rejected:* morning and afternoon
  registers (twice the teacher's work; can be added later as a session "period" column).
- **Statuses:** present, late, absent, excused. Late counts as in school.
- **Who takes it:** the stream's class teacher for the current year (`class_teacher_assignments`
  via their staff record), or anyone with `attendance.edit_locked` (heads, administrators). Other
  teachers are refused even though their role has `attendance.take`.
- **Which days:** today or earlier, Monday to Friday, within the current term. *Deferred:*
  holidays calendar and Saturday schools (a school setting later).
- **Who is on it:** students enrolled in the stream that day. A student moved on day D is on the
  new stream's register from D; a leaver is still on the register on their last day.
- **Lock time** is a school setting (default 10:00, `schools.settings.attendance_lock_time`).
  Ghana is on UTC all year, the same as `APP_TIMEZONE`, so no conversion. Before it the class
  teacher may correct freely; after it only `attendance.edit_locked` may change marks, with a
  reason.
- **Logging:** only changes after the lock are logged, one activity entry per changed mark (old,
  new, reason, who). Logging every morning's marks (≈600 rows a day per school) would flood the
  activity log with nothing worth auditing.
- **Concurrent saves** of the same register lock the stream row first, so they run one after
  the other; the unique index is the safety net.

## 2026-10-30 — Absence notification (Sprint 3 step 7)

- **Notices go out at the lock time, not on save.** A command (`attendance:notify-absences`,
  every weekday minute from the scheduler) notifies each school once its registers lock, so a
  teacher's corrections before then never reach parents. An absence an administrator marks
  after the lock is notified when they save. *Rejected:* notifying on save (false alarms from
  mistakes fixed minutes later).
- **Once per student per day**, recorded in `attendance_records.absence_notified_at`. A student
  with no guardian to tell stays unstamped, so a guardian added later that day still hears.
  Only Absent notifies; Late and Excused do not. Earlier days are never notified.
- **Recipients:** every linked guardian except access level "none" (contact only).
- **Notifications are stored against the guardian** (Guardian is Notifiable), not a user, so
  the parent app can show them from activation even though most guardians have no login yet.
  The `notifications` table has `school_id`; `App\Models\Notification` extends Laravel's
  `DatabaseNotification` with `BelongsToSchool`.
- **"Log for now":** `LogChannel` writes the message SMS would carry, with the phone, to
  `storage/logs/notifications.log`. Sprint 4 step 5 replaces it with the SMS channel.
- **Deferred:** a correction notice when an absence is reversed after notifying; a school
  setting to switch notices off; web push and SMS (Sprint 4).

## 2026-10-30 — Search, class lists and class-list PDF (Sprint 3 step 8)

- **Student search matches each word**, so "ama mensah" finds Ama Mensah. Phone-shaped input
  (3+ digits and nothing else) also finds students through any guardian's phone or alternative
  phone, so the front desk can find a child from a parent's number. Digits inside admission
  numbers do not count as a phone.
- **The student list shows Active students by default**; "All" shows every status. Filters
  (class, status, gender) live in the URL so a filtered list can be bookmarked.
- **Class lists use the register's rule** (`AttendanceRules::enrolmentsOn`) for who is in a
  stream on a day, so the list and the register never disagree. Anyone with `students.view`
  may open any stream's list; a class teacher starts on their own.
- **PDF:** dompdf with a plain Blade template and inline CSS 2.1 (dompdf has no flexbox or
  Tailwind). The private logo is embedded as a data URI because `enable_remote` stays off.
  **Font subsetting turned on** in `config/dompdf.php`: without it, every PDF embeds the whole
  DejaVu font (~1.1 MB for a one-page list).
- **Guardian phones are shown as stored** (+233…). *Deferred:* a local display format (024…).

## 2026-10-30 — Demo seeder and list-page speed (Sprint 3 step 9)

- **Demo data goes through the Actions** (AdmitStudent, AddGuardian, LinkGuardian, setup
  Actions), so it obeys the real rules: admission numbers, enrolments, one primary contact,
  activity log. Cost: about 80 s for 1,200 students locally. *Rejected:* bulk inserts (fast but
  could drift from the rules and skip the log).
- **Deterministic:** each school has its own seeded `Random\Randomizer` (Mt19937 seeded with the
  subdomain), separate from PHP's global random state, so names and phones are the same on
  every run. Ghanaian first names (including day names), surnames and mobile prefixes.
- **Families:** about one in three families have two or three children in the school and share
  guardian records. About 60% of children have both parents listed; some aunts/uncles are
  "contact only".
- **Size from config** (`app.demo_students_per_school`, env `DEMO_STUDENTS_PER_SCHOOL`, default
  600; phpunit.xml uses 24). Student seeding is skipped for a school that already has students.
- **Speed check, 600 students per school (EXPLAIN):** the student list walks the existing
  (school, status, last name, first name) index in order; class lists use the stream index and
  sort about 50 rows; guardian phone lookup uses the unique index. Word searches (`%word%`) scan
  the school's rows, which is fine at this size. **No new indexes were needed.** *Revisit:*
  full-text search if a school passes a few thousand students.
- **N+1 guard:** `tests/Feature/Performance/ListPagesTest.php` requires every list page to run
  the same number of queries for 10 and 20 students (after a warm-up request that fills the
  permission cache).

## 2026-10-30 — Fee setup, part A (Sprint 4 step 1)

- **`Money` value object now, not in step 3.** Fee structures store amounts, so the minimal
  version (`App\Support\Money\Money` + `App\Casts\MoneyCast`) came forward from step 3. Whole
  pesewas in `unsignedBigInteger` columns; typed input like "1,250.50" or "GH₵80" is parsed with
  a regex, never through floats. JSON and the activity log see the pesewa integer.
- **Residence lives on the student** (`students.residence`, day by default, logged). A student
  can change between terms without a new enrolment; bills (step 2) will record the residence they
  were billed at. *Rejected:* on the enrolment (a mid-year change would need a new enrolment).
- **Boarding is a school setting** (`offers_boarding`, School profile, default off). Off hides the
  Boarding column and the residence switch; it cannot be turned off while anyone is a boarder.
- **Fee structures are per term** × class × residence (unique), because first term usually
  carries extras. "Copy from <previous term>" fills a new term in one click, leaving classes that
  already have a structure untouched and skipping archived items.
- **Fee items are archived, never deleted**, so structures and future bills keep their names. An
  archived item may stay on a structure it is already on but cannot be added to another.
- **Optional items are opt-in per student** and stay on until removed. The amount comes from the
  student's fee structure. An item cannot be made compulsory while students take it.
- **Permissions:** fee screens need `finance.view` (route); changes need `fees.manage` (checked
  in each component), so the headteacher sees fees read-only. Every change is in the activity
  log under `fees` (items, structures, lines, student options) or `students` (residence).
- **Amounts:** more than GH₵0 and at most GH₵1,000,000 per line, at most two decimal places.

## 2026-10-30 — Fee setup, part B: discounts, scholarships, instalment plans (Sprint 4 step 1)

- **Stored now, applied in step 2.** This part only records who gets what; `GenerateTermBills`
  turns it into bill lines and due dates.
- **Percentages are basis points** (`App\Support\Money\Percent`: 1250 = 12.5%), parsed from text
  like money, so no floats anywhere in fees. Discounts and scholarships take a percentage
  (0.01–100%) or a fixed amount, off the whole bill or off chosen fee items.
- **Discount kinds:** sibling (automatic: billing gives it to the 2nd and later children who share
  a guardian, ordered by admission date; never given by hand; only one in use), staff child and
  custom (given on the student's page). A discount given by hand cannot become the sibling kind.
- **Scholarships share `student_discounts`** with hand-given discounts: `discount_id` NULL means a
  scholarship with its own name, optional sponsor, size, items and optional last term. One table
  keeps billing simple (every reduction for a student is one query). The unique index
  (school, student, discount_id) still stops a discount being given twice, while NULLs let a
  student hold several scholarships.
- **Instalment plans are per term:** 1–6 steps, due dates inside the term and strictly in order,
  shares adding up to exactly 100%. One default plan per term (true-or-NULL unique index, as with
  `is_current`); a term's first plan starts as the default. Students follow the default unless
  given another; **with no plans, the whole bill is due on the first day of term.** A plan with
  students on it cannot be deleted.
- **Stacking** (how a sibling discount, a staff-child discount and a scholarship combine on one
  bill) is decided in step 2, where it can be tested against real bills.
- Every change is logged under `fees`.

## 2026-10-30 — Term billing (Sprint 4 step 2)

- **Reductions stack, each on the full price** (Obed's choice). Order: sibling discount,
  hand-given discounts (by name), scholarships (by name). A percentage is worked out on each
  covered line's full price; a fixed amount is taken line by line. Nothing takes a line below
  zero, so a bill can reach GH₵0 but never go negative; later reductions get only what is left.
  Each reduction is its own negative bill line. *Rejected:* only the largest reduction.
- **Rounding:** percentages round to the nearest pesewa, half up (`BillStudent::percentOf`).
  Instalments round the same way and the last one takes the remainder, so they always add up
  to the net exactly.
- **A bill holds this term's charges only.** `arrears` is the ledger balance just before the bill,
  printed for information; "total payable" is arrears + net. Arrears are never charged twice
  because the balance always comes from the ledger.
- **`ledger_entries` came forward from step 3** (one change of order, agreed): issuing a bill
  writes one debit (none for a GH₵0 bill). The table has no `updated_at`; `LedgerEntry` throws on
  any update or delete. Payments, adjustments and reversals arrive in step 3.
- **One bill per student per term** (unique index), issued under a lock on the student row; a
  second attempt gets the existing bill back. Running billing again bills only students without
  a bill, so late admissions are billed by simply running it again.
- **Sibling order:** children sharing any guardian, by admission date then id. Only active,
  currently enrolled siblings count, so when the eldest leaves the next child pays full price.
- **Students who cannot be billed are skipped with a reason** (no structure for their class and
  residence, not enrolled this year, nothing to charge, already billed by someone else) and
  listed on the run. A run is finished when billed + skipped = students in it; the student list
  is taken once, inside the transaction that creates the run.
- **Only the current academic year's terms** can be billed. Bills are never edited; mistakes
  are corrected with ledger adjustments (step 3).
- **Logging:** the run is logged (started, finished with counts and total), not each bill: the
  bill, its lines and its ledger entry are the record. Same reasoning as attendance.
- **Preview is an estimate** (compulsory items × students per class, before discounts and optional
  items), so it stays fast for 600 students on shared hosting.
- The generic isolation test now touches `created_at` for models without `updated_at`.

## 2026-10-30 — Payments and receipts (Sprint 4 step 3)

- **Receipt numbers:** `{PREFIX}/R/{year}/{00001}`, the prefix admission numbers use, counted per
  school and per **year of issue** in `receipt_sequences` under a row lock (as admission numbers).
  Never reused: a reversed payment keeps its number.
- **Payments go to the student's account** (ledger balance), not to one bill. Overpaying is
  allowed (the screen warns); the credit shows as negative arrears on the next bill.
- **Methods:** cash, bank deposit, mobile money (Paystack is step 4). Bank and MoMo need a
  reference (spaces removed, upper-cased), unique per school and method, and proof (JPG, PNG or
  PDF up to 5 MB, private disk). Cash needs neither. If saving fails, the uploaded proof is
  deleted again.
- **Payment date:** today or up to 30 days back, never in the future (late-reported deposits).
- **Never edit, never delete.** `Payment` throws on any change other than the reversal columns
  and on delete. A mistake is reversed: a `reversal` ledger entry for the amount, the payment
  marked with who, when and why; the receipt reprints with a REVERSED stamp.
- **Adjustments:** a manual charge or credit with a reason (`adjustment` ledger entry), for
  things like a uniform added or fees waived. Logged under `payments`.
- **Permissions:** recording needs `payments.record`; reversing and adjusting need `fees.manage`,
  so a clerk can record without being able to undo. Payments and receipts are visible with
  `finance.view` or `payments.record` (Gate `view-payments`). `NavItem` now accepts a Gate
  ability name as well as a permission.
- **Guardians are told** (`PaymentReceived`: in-app plus the log stand-in for SMS) with the new
  balance; "contact only" guardians are skipped, as with absences.
- **Amount in words** on receipts is our own small class (`AmountInWords`, British "and"), no
  package.

## 2026-10-30 — Paystack (Sprint 4 step 4)

- **Test keys only.** `.env.example` has empty `PAYSTACK_*` lines. Every test fakes HTTP and calls
  `Http::preventStrayRequests()`, so nothing reaches Paystack from the suite. Live keys go only on
  production, set by Obed.
- **Webhook stores, then queues.** `POST /webhooks/paystack` (main domain, CSRF off for
  `webhooks/*`) checks `x-paystack-signature` (HMAC-SHA512 of the raw body), stores the event
  under a unique key (`event:data.id`) and answers 200 at once; `ProcessPaystackEvent` (cron
  queue) does the rest within a minute. A repeated delivery is stored and queued once.
  *Rejected:* doing the verify call inside the webhook request (timeouts on shared hosting).
- **Never trust the webhook alone.** `SettlePaystackTransaction` always calls Paystack's verify
  endpoint and records the payment only when it says success, in GHS, for exactly the expected
  total. Otherwise: failed (no money taken) or flagged "needs attention" (nothing recorded).
  It is the only way a Paystack payment is recorded; staff cannot record one by hand.
- **The school comes from our reference** (`OT{school id}-{ULID}`, unique across schools), never
  from metadata in the event. `ProcessPaystackEvent` is platform code: it finds the transaction
  without the school scope (commented), then works inside that school.
- **Paystack payments reuse `RecordPayment`** with method `paystack`, no user (recorded_by null),
  the Paystack reference, and a note when the parent paid the charge. Receipt numbers, ledger,
  guardian notice and logging are therefore identical to cash payments.
- **Charge:** schools absorb it by default; "parents pay it" grosses the amount up so the school
  receives the full fee: total = amount / (1 − rate), rounded up to the pesewa. Rate in config
  (`PAYSTACK_CHARGE_BASIS_POINTS`, default 195 = 1.95%). The subaccount bears the charge in
  both cases (`bearer: subaccount`).
- **Email for Paystack:** the payer's or primary guardian's email if valid, else
  `{phone digits}@{subdomain}.{base domain}` (Paystack only needs a valid-looking address).
- **Subaccounts from the console** (super admin): bank or MoMo provider from Paystack's list
  (cached a day), account checked with `/bank/resolve`, `percentage_charge` 0 (Orange Tree never
  holds school money). Changing a connected account is deferred (done in Paystack's dashboard
  for now).
- **Nightly `paystack:reconcile`** (02:00): re-verifies pending and paid transactions from the last
  3 days per connected school; records missed successes, flags anything that no longer matches.
  Flags show on Fees → Payments; nothing is reversed automatically.
- **Return page** `/pay/return` on the school's subdomain verifies immediately (the webhook may be
  later) and tells the parent the outcome. Staff can create a payment link on the student page
  (useful before the parent app exists).
- `PaystackEvent` is a platform model (no `school_id`), listed in the architecture test.

## 2026-10-30 — SMS through Arkesel (Sprint 4 step 5)

- **Provider: Arkesel** (Obed's choice), API v2 `POST /api/v2/sms/send` with the `api-key` header;
  numbers sent as `233…` without the plus. Behind our `SmsGateway` interface, so another provider
  is one class. **The `log` driver is the default** (local, tests, staging); `SMS_DRIVER=arkesel`
  only on production, set by Obed. Tests fake HTTP and block stray requests.
- **Credit in SMS units** (one unit = one segment), sold by Orange Tree outside the app; the console
  records top-ups with a note. `message_credit_ledger` is append-only (top_up +, message −,
  refund +). The SPEC lists it as a platform table; it has `school_id` and the school scope so a
  school sees only its own balance, and Orange Tree reads it through `runFor`.
- **Never send on credit.** `QueueSms` locks the school row, checks the balance and takes the
  units in one transaction, so concurrent messages cannot overdraw. Too few units: the SMS is
  kept as "not sent" (nothing taken); the in-app notice still goes.
- **Each SMS is a queued job** (`SendSms`, 3 tries at 0, 1 and 5 minutes). After the last try
  the units are returned once (`FailSms`). A sent message is never sent again.
- **Segments:** GSM-7 160/153 (extension characters count twice), otherwise UCS-2 70/67. The
  payment SMS writes "GHS" instead of "₵", because one "₵" makes the whole SMS Unicode and
  roughly doubles the cost.
- **Each notice is opt-in per school** (School profile → SMS: absence, payment); none by default.
  In-app notices always go. `SmsChannel` replaced the temporary `LogChannel`.
- **Low-credit warning:** finance staff (`fees.manage`) get one in-app warning when credit falls
  below the school's threshold (default 100 units); it re-arms once credit is back above it.
  `User::notifications()` now uses the school-owned `Notification` model, like guardians.
- **Sender ID per school**, set in the console once Arkesel approves it (3–11 letters, digits or
  spaces); until then `SMS_DEFAULT_SENDER` (OrangeTree).
- **Deferred:** delivery reports from Arkesel (we record "sent" when Arkesel accepts the message),
  and sending to a guardian's alternative phone.

## 2026-10-30 — Parent app sign-in (Sprint 4 step 6, part A)

- **Parent logins are rows in `users`** (Obed's choice), with the Parent role and
  `guardians.user_id` pointing to them. `users.phone` is new (unique per school, like the email)
  and `users.email` is now nullable: parents have a phone and no email.
- **One code flow for activation and forgotten passwords.** `POST /api/v1/auth/otp` texts a code;
  `POST /api/v1/auth/set-password` (phone, code, password twice) creates the login on first use,
  or sets a new password later. This replaced separate "activate" and "reset" endpoints from the
  plan: with one flow the API never says whether a number is already activated.
- **The API never reveals who is registered.** "Send code" gives the same answer for any number.
  The no-credit message and the 60-second wait apply to every number; a number with no guardian
  (or only contact-only links) simply gets no text. "No access to the parent app" is only said
  after a right code or password.
- **Codes:** 6 digits, only a hash stored (`parent_otps`, school-owned), 10 minutes, 5 wrong tries,
  only the newest works, each works once.
- **Codes are paid from the school's SMS credit** (Obed's choice) and always sent, whatever the
  school chose under School profile → SMS. They go through the same queued `SendSms`, so a code
  can take up to a minute on shared hosting (cron). **Staff never see a code:** Fees → Messages
  shows it as `******`, and the stored message has the code removed once sent or failed.
- **Tokens:** one Sanctum token per device with the `parent` ability, ending 90 days after it was
  issued (Obed's choice). Signing out ends that device's token; setting a new password ends all.
  Expired rows are pruned nightly (`sanctum:prune-expired`). Parent routes need a bearer token
  with the `parent` ability and a linked guardian (`parent` middleware), plus `portal.children`
  (the Parent role's permission), so a school can switch the app off by editing the role.
- **Rate limits** (per school host): codes 3 per phone per 10 minutes and 10 per IP an hour;
  code checks 5 per phone a minute; logins 5 per phone and IP a minute. Phones are normalised
  first, so "024…" and "+233 24…" count as one number.
- **Parent-only users are refused on the staff login** (settles the open item from Sprint 2).
  Staff who are also parents still sign in.
- **Switched-off users lose their tokens too:** `EnsureUserBelongsToSchool` now refuses a token
  whose user is deactivated (401), since Sanctum loads token users without the scoped provider.
- **Moved to step 8 (Obed's OK):** `GET /api/v1/announcements`, built with its table.

## 2026-10-31 — Parent API data (Sprint 4 step 6, part B)

- **Endpoints** (all need a parent token): `GET children`, `GET children/{id}`,
  `GET children/{id}/attendance?month=YYYY-MM`, `GET children/{id}/bills`,
  `GET children/{id}/statement`, `POST children/{id}/payments` (amount),
  `GET notifications`, `POST notifications/{id}/read`, `POST notifications/read-all`.
  Money is sent as `{pesewas, display}`.
- **A child is only reached through the guardian's own link** (`FindParentChild`). Another
  family's child, a contact-only child, or a deleted child answers **404, not 403**, so the API
  never confirms that a child exists. Bills, statement and payments need full access: limited
  gets **403** (the parent can see the child, just not the fees).
- **What limited access means, now applied everywhere:** attendance and notices, but no amounts.
  The children list sends `balance: null` for them, the notifications list leaves out payment
  notices for them, and **`RecordPayment` now sends payment notices (in-app and SMS) only to
  full-access guardians.** Before this, limited guardians got the payment notice with the balance
  (Sprint 4 step 3); that contradicted the access level's meaning.
- **Teachers' attendance notes are not sent to parents**: they are written for staff.
- **Notifications are filtered at the database**: the guardian's own, about a child they can
  still see. Marking read works on the same filter, so another guardian's notice is a 404.
- **Paying:** `StartParentPayment` calls the existing `InitialisePaystackPayment` with the
  guardian as payer and the parent's login as `initiated_by`; Paystack returns them to the
  school's `/pay/return` page. Limited to 10 starts a minute per parent.
- **The statement moved into an Action** (`BuildStatement`) so the staff fees panel and the API
  show the same lines.
- **Children list is a fixed number of queries** (9) however many children the parent has.

## 2026-10-31 — Parent PWA (Sprint 4 step 7)

- **npm packages (Obed's OK):** `vue` 3.5, `@vitejs/plugin-vue` 6 and `vue-router` 5. No Pinia,
  Axios or PWA plugin: Vue's `reactive()` holds the little shared state, and `api.js` wraps
  `fetch`. **No JS test runner yet** (Obed's choice); Pest covers the Laravel side and the screens
  were clicked through at phone size.
- **One page, hash URLs** (`/parent#/children/5`): Laravel serves only `/parent`, so every screen
  works from the one cached page, offline too, with no catch-all route.
- **Hand-written service worker** (`resources/js/parent/sw.js`, served by Laravel with a
  `VERSION` from the build manifest). It caches the app page and the built files only. **API
  answers are never cached** (Obed's choice), so a shared or lost phone holds no children, fees or
  notices; offline shows "You're offline" over the cached app. Signing out clears the token and
  the cache.
- **Scope is `/parent`, not `/parent/`.** The browser check found that a `/parent/` scope leaves
  out the app page itself (`/parent`), so the worker never controlled it and the manifest's start
  page was outside its own scope. The worker is served with `Service-Worker-Allowed: /parent`.
- **Per-school manifest and icons:** the school's name and colour; icons are its initials drawn
  with GD (DejaVu Sans Bold from dompdf), because logos are private files. 192 and 512 px,
  "any maskable".
- **The token lives in `localStorage`.** Simple and survives app restarts. The risk is script
  injection on the page, so the app shows only text from our own API through Vue's escaping
  (no `v-html`).
- **Unread badge:** polled every 60 seconds while the app is on screen, and on returning to it.
  Web push comes with chat (Sprint 7).
- **Paying:** the app starts the payment, shows fees, Paystack charge and total, then opens
  Paystack. The return page now has "Back to the parent app" (to that child).
- **Own small CSS bundle:** the parent app's Tailwind scans only its own files (3.6 kB gzipped,
  44 kB JS gzipped), so it loads quickly on mobile data.
- **Not checked in the browser:** the install prompt (Chrome only offers it on a real
  installable visit, not in the preview pane), offline mode, and fee screens with real bills
  (local demo data has none). The API behind the fee screens is covered by tests.

## 2026-10-31 — Announcements (Sprint 4 step 8)

- **Who sends to whom (Obed's choice):** the whole school or any class needs
  `announcements.send` **and** `students.manage` (heads, administrators, proprietor). A class
  teacher sends only to the streams they are class teacher of this year (the register's rule).
  No new permission, so existing schools need no grant step.
- **In-app only (Obed's choice):** each family gets an `AnnouncementPosted` notice (badge) and
  the announcement in the app's News tab. No SMS: a school-wide one would cost about one unit per
  family. Can be added later as a per-announcement choice with the cost shown first.
- **Never edited once sent (Obed's choice):** withdraw (hidden from parents, kept, logged with
  who and when) and send a corrected one. Withdrawing also deletes its notices, so the badge
  does not point at something parents can no longer open. Senders withdraw their own; heads any.
- **Text only (Obed's choice):** attachments come with chat (Sprint 7).
- **Who receives it:** guardians with full **or limited** access (notices are part of limited)
  to a child currently in a target stream, or every such guardian for the whole school. Notices
  are worked out when sent; the News list is worked out when read, from the children's current
  streams, so a child who moves class sees the new class's announcements from then on.
- **Sent in chunks of 200 families** (`SendAnnouncementNotices` job via the cron-run queue), so
  sending never waits on hundreds of rows. Safe to run twice: a family that has the notice is
  skipped, and nothing goes out after a withdrawal.
- **"Seen by N of M families"** counts the notices opened (opening the announcement, or tapping
  its notice, marks it read). It reads the notices' JSON column without an index; fine at today's
  sizes (689 families locally), to revisit if schools grow far larger.
- **API:** `GET /api/v1/announcements` and `/{id}`, newest first, each with the names of the
  family's children it concerns. Another class's or a withdrawn announcement is 404. The sender's
  name is not shown to parents: it comes from the school.
- **Different from Sprint 7's class broadcasts**, which are part of chat (parents can reply
  privately). Announcements are one-way.

## 2026-10-31 — SaaS census and invoices (Sprint 4 step 9)

- **Plans table (Obed's choice):** `plans` holds Orange Tree's rate per active student per term;
  each school has `plan_id`. The rate and plan name are **copied onto every invoice**, so a later
  price change never alters an invoice. The old free-text `schools.plan` column (empty on every
  school) is left in place; dropping it is a later clean-up, with Obed's OK.
- **Only active schools are billed (Obed's choice):** trial and suspended schools get no
  invoice; the census run still shows a trial school's count.
- **Census count:** students enrolled on the census date by the register's rule (started on or
  before it; not ended before it; a leaver counts on their last day; a student moved that day
  counts once). Each student counts once, even when an old enrolment ends and a new one starts
  that day (e.g. promotion). Deleted student records do not count.
- **`saas:census` runs daily at 01:00** and bills each active school whose **current** term's
  census date has passed with no live invoice, so a missed night is caught up the next. It does
  not go back to earlier terms (a school joining mid-year is not billed for terms it did not use).
  `--school=` and `--dry-run` for checking. Staff can also issue the invoice from the school's
  Billing page.
- **One invoice per school per term:** a `live` column that is true, or NULL once voided, under a
  unique index with school and term (the true-or-NULL pattern from `is_default`). A voided term
  can be invoiced again.
- **Invoice numbers `OT/INV/{year}/{0001}`, one series for all schools**, from
  `saas_invoice_sequences` under a row lock; tested with five real processes taking 100 numbers
  at once (no gaps, no repeats). Numbers are never reused, even after a void.
- **Payments recorded by hand (Obed's choice):** amount, date, method, reference. Part payments
  allowed; more than the balance refused; the invoice is Paid once covered. Payments are never
  changed or deleted (the model refuses). An invoice with payments cannot be voided. Online
  payment to Orange Tree (Paystack) is a later step.
- **Status is worked out, not stored:** void, paid, overdue (past due with a balance) or unpaid.
  `SaasInvoice::whereStatus()` does the same in SQL for the list filters.
- **Due 30 days after issue** (`SAAS_DUE_DAYS`). Overdue invoices are shown in red in the
  console; **nothing in the school changes yet**. The read-only lock after the grace period (SPEC
  section 8) is its own step, added as Pilot preparation step 4 (Obed's choice).
- **Platform tables, console only:** `plans`, `saas_invoices`, `saas_invoice_sequences`,
  `saas_payments` have no school scope and are listed in the architecture test. Every Orange Tree
  user can view billing (`view-billing`); only super admins issue, record and void
  (`manage-billing`). Plans, invoices, payments and school plan changes are in the activity log.
- **Invoice PDF** (A4, dompdf) with Orange Tree's details from `.env` (`SAAS_INVOICE_*`, blank
  until Obed fills them) and a "How to pay" box. Not yet looked at by eye.

## 2026-10-31 — Production setup and releases (Pilot preparation step 1)

- **One-command releases (Obed's choice):** `scripts/release.sh` on Obed's machine checks that
  `main` is clean and pushed, builds the front-end, uploads it over SSH, runs
  `scripts/deploy.sh` on the server and checks `/up`. The server never needs Node, and nothing
  connects to the server unless Obed runs the script.
- **`deploy.sh`:** pre-flight while still up → maintenance mode with a bypass secret → note the
  previous commit → pull → composer → migrate → swap in the uploaded build → optimize →
  queue:restart → up. On failure the site stays down with instructions. `--rollback` returns code
  and front-end one release back (migrations are never undone; they only ever add). Everything
  sits inside `main()` so the `git pull` can replace the script while it runs.
- **Tried for real against a throwaway clone** on the test database: a release (two commits
  forward, build swapped), a rollback, a refused second rollback, and a release failing after
  maintenance mode (stays down; rollback then works). That trial found that the previous commit
  was recorded only at the end, so a failed release could not be rolled back; it is now recorded
  before anything changes. Not yet run on Stellar Plus itself.
- **Security headers in the app (Obed's choice):** `SecurityHeaders` middleware in production and
  staging: HSTS 6 months with subdomains, no preload yet (hard to undo); `nosniff`;
  `X-Frame-Options: SAMEORIGIN`; `Referrer-Policy: strict-origin-when-cross-origin`. Cloudflare's
  own HSTS stays off to avoid two sources of truth; "Always use HTTPS" on.
- **Backups to Cloudflare R2 (Obed's choice):** `league/flysystem-aws-s3-v3` added (R2 is
  S3-compatible; Obed's OK). Nightly: the database and `storage/app/private` only (code is in
  Git; `.env` is kept by hand and never backed up). Encrypted with `BACKUP_ARCHIVE_PASSWORD`,
  verified after zipping, kept 7 days / 7 dailies / 4 weeklies / 6 monthlies, only problems
  emailed. The local backup folder is excluded so backups never contain older backups. A
  monthly test restore on staging is a checklist in DEPLOYMENT.md.
- **`app:preflight` (Obed's choice):** read-only checks run by `deploy.sh` before the site goes
  down. Errors stop the release (debug on, no key, http, base domain mismatch, non-database
  drivers, insecure cookies, test mail in production, test Paystack key in production, live
  Paystack key or real SMS on staging, incomplete R2 settings, unwritable folders). Warnings for
  things Obed switches on later (no Paystack yet, SMS still logged, backups not off-site or not
  encrypted).
- **Idle timeout:** `SESSION_LIFETIME=60` in production (Laravel's session lifetime counts from
  the last request, so it is an idle timeout).

## 2026-10-31 — Import hardened for real school files (Pilot preparation step 2)

- **Balances brought forward (Obed's choice):** optional `opening_balance` column. Each amount
  becomes one ledger adjustment, "Balance brought forward from previous records", through
  `AdjustBalance` (so it is logged under "payments"), recorded as made by the person who started
  the import. "1,250.50" or "GH₵ 1,250" is owed; "-200", "(200)" or "200 CR" is in credit. Only
  staff with `fees.manage` may import balances; others get a row error asking them to clear the
  column. The preview shows the count and totals owed and in credit before anything is saved.
- **The same child twice is refused (Obed's choice):** first and last name with the date of
  birth, or with the class when the date of birth is blank, matched against students on file
  (any status) and earlier rows. The message names the student on file and their admission
  number. A date that was typed but cannot be read does not fall back to the class (it already
  has its own error); this was found by the 600-row test, where two planted bad dates shared a
  name and class. So importing the same file twice now gives errors, not doubled students.
- **Names typed all in capitals are tidied (Obed's choice):** "AMA SERWAA MENSAH" → "Ama Serwaa
  Mensah", for students and guardians. Any lower-case letter leaves the name exactly as typed
  (McCarthy, deGraft).
- **Error report (Obed's choice):** "Download the file with errors marked" returns the school's
  rows with an "errors" column and the problem cells in red, made on request (nothing stored).
  The columns are the template's, so the fixed report uploads again as it is (tested).
- **Also accepted now:** non-breaking and zero-width spaces (cleaned from every cell); two phones
  in a guardian cell (first is the phone, second the alternative phone); dates in words ("8 Sep
  2015", "8th September 2015", "September 8, 2015"; two-digit years still refused); other heading
  names ("Surname", "DOB", "Sex", "Adm No", "Form", "Parent Phone", "Arrears"…), used only when
  the template's own heading is missing.
- **Day or boarding:** optional `residence` column (blank is day), checked against the school's
  "offers boarding" setting, applied with `SetResidence` when the row is imported.
- **New columns go at the end** of the template (`residence`, `opening_balance`), so files made
  from the old template still read.
- **Not yet:** a real file from the pilot school. The 600-row test file is made up, with every
  kind of mistake above; the school's own layout may still surprise us.
