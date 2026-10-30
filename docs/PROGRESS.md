# Progress

Every step and "Done when" line from `docs/ROADMAP.md`. Tick each one as it is finished.

---

## Sprint 0 — environment and project creation

- [x] 1. Check the local environment, report versions and anything missing, ask Obed for the
      server's PHP and MySQL/MariaDB versions, confirm the latest Laravel supports that PHP version.
- [x] 2. Create the project with the Laravel installer (Livewire starter kit, built-in auth, Pest,
      MySQL). Initialise Git and make the first commit.
- [x] 3. `php artisan install:api`; install the SPEC section 2 packages and publish their
      config/migrations; Larastan with `phpstan.neon` at level 5.
- [x] 4. Configure `.env` and `.env.example` (`APP_BASE_DOMAIN=localhost`, database queue/session/
      cache, Mailpit, SMS `log` driver); create `orange_sms` and `orange_sms_test`; point
      `phpunit.xml` at the test database.
- [x] 5. Folder structure from SPEC section 15, `docs/PROGRESS.md`, `docs/DECISIONS.md`,
      `.github/workflows/tests.yml`.

Done when:
- [x] the app runs at `localhost:8000`
- [ ] tests pass locally and in CI — **local: yes** (also on a fresh clone of the repo).
      **CI: blocked.** GitHub did not start the job: "your account is locked due to a billing
      issue". Re-run once the GitHub billing is fixed.
- [x] first push is on GitHub (`ObedMarvin22/sms`: `main` + `s0/*` branches)

**STOP for review.**

---

## Sprint 1 — foundation: tenancy, auth, roles

- [x] 1. `schools` migration, model, factory, `SchoolStatus` enum.
- [x] 2. `CurrentSchool` holder bound in the container.
- [x] 3. `IdentifySchool` middleware and the subdomain route group (web + API). Unknown → 404;
      suspended → suspended page.
- [x] 4. `BelongsToSchool` trait + Pest architecture test.
- [x] 5. Users per school: nullable `school_id`, composite unique email, login scoped to the
      current school, `EnsureUserBelongsToSchool` middleware for sessions and tokens.
- [x] 6. spatie/laravel-permission with teams (`school_id`); `SeedDefaultRoles` Action with the
      eleven roles and their permissions; team id set in `IdentifySchool`.
- [ ] 7. Orange Tree console on `admin.localhost`: platform-user guard, `CreateSchool` Action
      (school, roles, invitation for first administrator), list of schools.
- [ ] 8. Activity log on users and roles.
- [ ] 9. Staff 2FA (starter kit's feature if present; otherwise note it for Sprint 4).
- [ ] 10. Seeder: two demo schools (`greenhill`, `hillview`) with an admin each.

Done when:
- [ ] each subdomain shows its own school's login; unknown subdomain 404s; suspended shows the page
- [ ] a Greenhill user cannot log in on Hillview; a Greenhill session/token is rejected on Hillview
- [ ] creating a school in the console seeds roles and invites the admin
- [ ] isolation and architecture tests pass; CI green

**STOP for review.**

---

## Sprint 2 — school setup and academic structure

- [ ] 1. Staff app shell: layout with school logo and colours; sidebar filtered by permissions.
- [ ] 2. School profile and settings (logo upload under the school's storage folder, colours,
      contacts, levels offered, settings JSON).
- [ ] 3. Academic years and terms/semesters (dates, census date, one current year/term).
- [ ] 4. Levels, classes and streams with default class generation from levels offered.
- [ ] 5. Subjects (preset list per level, editable) and SHS programmes with core/elective subjects.
- [ ] 6. Class–subject mapping.
- [ ] 7. Setup wizard for new schools (steps 2–6 in order); student entry blocked until a current
      term and a class exist.
- [ ] 8. Permission checks on every screen, with tests that a teacher is refused on settings screens.
- [ ] 9. Staging environment checklist written to `docs/DEPLOYMENT.md`.

Done when:
- [ ] a fresh school completes the wizard unaided
- [ ] logos cannot be read across schools
- [ ] teacher refused on settings (tested)
- [ ] CI green

**STOP for review.**

---

## Sprint 3 — people and attendance

- [ ] 1. Staff records, email invitations, role assignment, teaching assignments.
- [ ] 2. Students with per-school admission number format, generated under a row lock
      (concurrency test).
- [ ] 3. Enrolment on admission; promotion, transfer and withdrawal create new enrolment records.
- [ ] 4. Guardians: create/link, relationship, access level, primary contact, `+233`
      normalisation, duplicate detection by phone. (First ask Obed: one parent account across
      schools vs per school.)
- [ ] 5. Excel import for students + guardians: template download, dry-run preview with row
      errors, queued chunked import (100 rows per job).
- [ ] 6. Daily attendance register by class teacher; lock at a school-set time; admin-only edits
      after lock, logged.
- [ ] 7. Absence notification to guardians (in-app + log for now).
- [ ] 8. Student search, class lists, printable class list PDF.
- [ ] 9. Seeder upgrade: ~600 students per demo school with guardians; list pages stay fast.

Done when:
- [ ] 600-row import works with clear errors
- [ ] concurrent admissions never collide (tested)
- [ ] promotion keeps history
- [ ] teachers can only register their own class
- [ ] absence creates notification
- [ ] CI green

**STOP for review.**

---

## Sprint 4 — fees, payments, parent PWA, SMS

- [ ] 1. Fee items, fee structures (class × day/boarding), optional items, discounts,
      scholarships, instalment plans.
- [ ] 2. `GenerateTermBills` queued, chunked job; arrears carried forward.
- [ ] 3. Append-only ledger, `Money` cast, balance calculation, `RecordPayment` Action, receipt
      numbers under row lock, receipt PDF.
- [ ] 4. Paystack: `PaystackClient`; subaccount creation from the console; transaction
      initialisation; webhook with signature check, verify call and idempotency; nightly
      reconciliation command. All tested with `Http::fake()`.
- [ ] 5. SMS: `SmsGateway` interface, `log` driver, one provider driver (ask Obed which),
      notification channel, per-school credit wallet with low-credit warning; absence and
      payment notifications via SMS where enabled.
- [ ] 6. Parent API v1: phone OTP activation, password, Sanctum tokens, endpoints for children,
      attendance, bills, payment initialisation, announcements, notifications (scoped strictly
      to the guardian's own children — tested).
- [ ] 7. Parent PWA v1 (Vue 3): login, children list, child dashboard, announcements,
      notifications; manifest + service worker; installable.
- [ ] 8. Announcements: school-wide and class broadcasts.
- [ ] 9. SaaS census command and invoices in the console. (Cut line: 8 and 9 may slip a week.)
- [ ] 10. Staff 2FA if not done in Sprint 1.

Done when:
- [ ] term bills correct for day and boarding students
- [ ] Paystack test payment updates balance, issues receipt and SMS
- [ ] duplicate webhook recorded once and bad signature rejected (tested)
- [ ] a parent sees only their own children (tested)
- [ ] SMS stops when credit runs out
- [ ] CI green

**STOP for review — Phase 1 complete.**

---

## Pilot preparation (no new features)

- [ ] 1. `docs/DEPLOYMENT.md`: first-time server setup and the release script.
- [ ] 2. Import tooling hardened for the pilot school's real data.
- [ ] 3. Fix bugs found during pilot testing on staging.

---

## Phase 2

- [ ] Sprint 5 — assessments. **STOP for review.**
- [ ] Sprint 6 — grading and report cards. **STOP for review.**
- [ ] Sprint 7 — PTI chat. **STOP for review.**
- [ ] Sprint 8 — pickup, homework, boarding. **STOP for review.**
- [ ] Sprint 9 — finance reports and dry run. **STOP for review.**

(Each Phase 2 sprint will be broken into steps here before it starts. Phase 3 needs Obed's
go-ahead.)
