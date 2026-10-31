# Roadmap — build order

Work through these steps in order, one at a time. Each sprint ends with a STOP for Obed's review.
Copy every step and every "Done when" line into `docs/PROGRESS.md` as checkboxes in the first
session.

---

## Sprint 0 — environment and project creation

1. Check the local environment: `php -v`, `composer -V`, `node -v`, `npm -v`, `git --version`,
   `mysql --version`. Report versions and anything missing. Ask Obed which PHP and MySQL/MariaDB
   versions Namecheap Stellar Plus offers, and confirm the latest Laravel supports that PHP version.
2. Create the project with the Laravel installer: Livewire starter kit, Laravel's built-in auth,
   Pest, MySQL. Initialise Git and make the first commit.
3. Run `php artisan install:api`. Install the packages listed in `docs/SPEC.md` section 2 and
   publish their config/migrations. Install Larastan, add `phpstan.neon` at level 5.
4. Configure `.env` and `.env.example` (`APP_BASE_DOMAIN=localhost`, database drivers for queue,
   session and cache, Mailpit, SMS `log` driver). Create the `orange_sms` and `orange_sms_test`
   databases and point `phpunit.xml` at the test database.
5. Create the folder structure from `docs/SPEC.md` section 15, `docs/PROGRESS.md`,
   `docs/DECISIONS.md`, and `.github/workflows/tests.yml` (CI from SPEC section 16).

Done when: the app runs at `localhost:8000`, tests pass locally and in CI, first push is on GitHub.
**STOP for review.**

---

## Sprint 1 — foundation: tenancy, auth, roles

1. `schools` migration, model, factory, `SchoolStatus` enum.
2. `CurrentSchool` holder bound in the container.
3. `IdentifySchool` middleware and the subdomain route group (web + API). Unknown → 404;
   suspended → suspended page.
4. `BelongsToSchool` trait + Pest architecture test.
5. Users per school: nullable `school_id`, composite unique email, login scoped to the current
   school, `EnsureUserBelongsToSchool` middleware for sessions and tokens.
6. spatie/laravel-permission with teams (`school_id`); `SeedDefaultRoles` Action with the eleven
   roles and their permissions; team id set in `IdentifySchool`.
7. Orange Tree console on `admin.localhost`: platform-user guard, `CreateSchool` Action (school,
   roles, invitation for first administrator), list of schools.
8. Activity log on users and roles.
9. Staff 2FA (use the starter kit's feature if present; otherwise note it for Sprint 4).
10. Seeder: two demo schools (`greenhill`, `hillview`) with an admin each.

Done when:
- each subdomain shows its own school's login; unknown subdomain 404s; suspended shows the page
- a Greenhill user cannot log in on Hillview; a Greenhill session/token is rejected on Hillview
- creating a school in the console seeds roles and invites the admin
- isolation and architecture tests pass; CI green
**STOP for review.**

---

## Sprint 2 — school setup and academic structure

1. Staff app shell: layout with school logo and colours; sidebar filtered by permissions.
2. School profile and settings (logo upload under the school's storage folder, colours,
   contacts, levels offered, settings JSON).
3. Academic years and terms/semesters (dates, census date, one current year/term).
4. Levels, classes and streams with default class generation from levels offered.
5. Subjects (preset list per level, editable) and SHS programmes with core/elective subjects.
6. Class–subject mapping.
7. Setup wizard for new schools (steps 2–6 in order); student entry blocked until a current term
   and a class exist.
8. Permission checks on every screen, with tests that a teacher is refused on settings screens.
9. Staging environment checklist written to `docs/DEPLOYMENT.md` (Obed sets it up by hand).

Done when: a fresh school completes the wizard unaided; logos cannot be read across schools;
teacher refused on settings (tested); CI green. **STOP for review.**

---

## Sprint 3 — people and attendance

1. Staff records, email invitations, role assignment, teaching assignments.
2. Students with per-school admission number format, generated under a row lock (concurrency test).
3. Enrolment on admission; promotion, transfer and withdrawal create new enrolment records.
4. Guardians: create/link, relationship, access level, primary contact, `+233` normalisation,
   duplicate detection by phone.
   (Before this step: ask Obed to confirm one parent account across schools vs per school.)
5. Excel import for students + guardians: template download, dry-run preview with row errors,
   queued chunked import (100 rows per job).
6. Daily attendance register by class teacher; lock at a school-set time; admin-only edits after
   lock, logged.
7. Absence notification to guardians (in-app + log for now).
8. Student search, class lists, printable class list PDF.
9. Seeder upgrade: ~600 students per demo school with guardians; check list pages stay fast
   (add indexes where needed).

Done when: 600-row import works with clear errors; concurrent admissions never collide (tested);
promotion keeps history; teachers can only register their own class; absence creates
notification; CI green. **STOP for review.**

---

## Sprint 4 — fees, payments, parent PWA, SMS

1. Fee items, fee structures (class × day/boarding), optional items, discounts, scholarships,
   instalment plans.
2. `GenerateTermBills` queued, chunked job; arrears carried forward.
3. Append-only ledger, `Money` cast, balance calculation, `RecordPayment` Action, receipt numbers
   under row lock, receipt PDF.
4. Paystack: `PaystackClient` service; subaccount creation from the console; transaction
   initialisation; webhook with signature check, verify call and idempotency; nightly
   reconciliation command. All tested with `Http::fake()`.
5. SMS: `SmsGateway` interface, `log` driver, one provider driver (ask Obed which), notification
   channel, per-school credit wallet with low-credit warning. Switch absence and payment
   notifications to SMS where the school enables it.
6. Parent API v1: phone OTP activation, password, Sanctum tokens, endpoints for children,
   attendance, bills, payment initialisation, announcements, notifications (API Resources,
   rate limits, scoped strictly to the guardian's own children — tested).
7. Parent PWA v1 (Vue 3): login, children list, child dashboard (attendance, fee balance, pay),
   announcements, notifications; manifest + service worker; installable.
8. Announcements: school-wide and class broadcasts.
9. SaaS census command and invoices in the console. (Cut line: 8 and 9 may slip a week.)
10. Staff 2FA if not done in Sprint 1.

Done when: term bills correct for day and boarding students; Paystack test payment updates
balance, issues receipt and SMS; duplicate webhook recorded once and bad signature rejected
(tested); a parent sees only their own children (tested); SMS stops when credit runs out;
CI green. **STOP for review — Phase 1 complete.**

---

## Pilot preparation (no new features)

1. `docs/DEPLOYMENT.md`: first-time server setup and the release script (see below).
2. Import tooling hardened for the pilot school's real data.
3. Fix bugs found during pilot testing on staging.
4. Read-only lock for schools past the SaaS grace period (SPEC section 8: staff can view but not
   change, parents keep access). Added in Sprint 4 step 9 with Obed's OK: it is in the SPEC but
   had no roadmap step, so it is not new scope.

Release script (`deploy.sh` on the server):

```bash
#!/bin/bash
set -e
cd ~/app
php artisan down --retry=60
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

---

## Phase 2

- **Sprint 5 — assessments:** assessment schemes and components per level, score entry by
  teaching assignment, submission and locking, admin unlock with log.
- **Sprint 6 — grading and report cards:** grade scales and bands, weighted totals, aggregates,
  positions on/off, promotion rules, report card templates per level, queued PDF batches,
  headteacher approval, publishing to parents.
- **Sprint 7 — PTI chat:** conversations per child, class broadcasts, attachments, polling,
  read receipts, web push, email channel, all safeguarding defaults from SPEC section 10.
- **Sprint 8 — pickup, homework, boarding:** everything in SPEC sections 11 and 12, plus homework
  by class and subject visible to parents.
- **Sprint 9 — finance reports and dry run:** collections, debtors, reconciliation reports;
  full report-card dry run with demo data; fixes.

Each Phase 2 sprint ends with a **STOP for review**.

## Phase 3 (do not start without Obed's go-ahead)

WhatsApp Business API, timetable, HR and payroll (SSNIT, PAYE), inventory and stores, library,
transport, PTA meetings and slot booking, school calendar, online admissions, student portal,
analytics dashboards, native mobile app on the existing API.
