# Specification — Orange Tree School Management SaaS

## 1. Product

A SaaS for many Ghanaian schools, run by Orange Tree Technologies. Each school gets its own
subdomain (`greenhill.yourapp.com`). It supports Creche/KG, Primary, JHS (BECE), SHS (WASSCE)
and international curricula, day and boarding schools. The flagship feature is the
Parent–Teacher Interconnect (PTI): parents talk to teachers, follow their children's attendance,
fees, homework and results, and control who may pick their children up.

Business model: Orange Tree charges each school per active student per term (census date).
Schools buy SMS/WhatsApp messaging credit from Orange Tree.

Launch target: first school live for the start of second term (January 2027). Report cards must
work before the end of second term (around April).

## 2. Stack and packages

| Need | Choice |
| --- | --- |
| Framework | Laravel (latest stable) with Pest |
| Staff UI | Livewire starter kit, Alpine.js, Tailwind |
| Parent PWA | Vue 3 + Vite in `resources/js/parent`, calls `/api/v1` |
| API auth | laravel/sanctum (via `php artisan install:api`) |
| Roles/permissions | spatie/laravel-permission with **teams** enabled, team key `school_id` |
| Audit log | spatie/laravel-activitylog |
| Backups | spatie/laravel-backup (off-site: Google Drive or Cloudflare R2) |
| PDFs | barryvdh/laravel-dompdf |
| Excel import/export | maatwebsite/excel |
| Web push | laravel-notification-channels/webpush |
| Payments | Paystack REST API via Laravel HTTP client (no SDK package) |
| SMS | Own `SmsGateway` interface; first driver for one Ghanaian provider (Hubtel, Arkesel or mNotify — Obed will choose); `log` driver for local |
| Static analysis | larastan/larastan (start level 5), Laravel Pint |

## 3. Hosting constraints (Namecheap Stellar Plus, behind Cloudflare)

- Code lives in `~/app`; the domain's document root and a wildcard `*` subdomain both point to
  `~/app/public`. Cloudflare proxies `@` and `*` with an Origin certificate, SSL Full (strict).
- One cron entry: `* * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1`.
  The scheduler runs `queue:work --stop-when-empty --max-time=50` every minute, without overlapping.
- `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database`.
- Heavy work (term billing, report card PDFs, imports, bulk SMS) is queued and chunked
  (10–100 items per job) to stay within shared-hosting CPU and memory limits.
- No websockets, no Node at runtime, no Chrome/Browsershot. Assets are built locally/CI.
- Bulk email goes through a transactional provider (e.g. Brevo, Mailgun), not cPanel mail.
- Uploads use Laravel's storage layer under `schools/{school_id}/...` so they can move to S3/R2.
- Everything is configured from `.env`, so the app can move to a VPS without code changes.

## 4. Multi-tenancy

1. `schools` table: name, subdomain (unique), status enum (trial, active, suspended), logo path,
   colours, levels offered, plan, paystack_subaccount_code, sms_sender_id, settings (JSON),
   timestamps.
2. `CurrentSchool` class bound as a scoped singleton in the container.
3. `IdentifySchool` middleware: strip `config('app.base_domain')` from the host, look up the
   subdomain, 404 for unknown, a "suspended" page for suspended (staff read-only after SaaS
   grace period; parents keep read access), fill `CurrentSchool`, and set the Spatie permission
   team id. Applied to a `Route::domain('{school}.' . config('app.base_domain'))` group for web
   and API routes.
4. `BelongsToSchool` trait: global scope `where table.school_id = current school` when a school
   is set; `creating` hook fills `school_id`.
5. Users have nullable `school_id` (null = Orange Tree platform user). Email unique per school.
   Login only finds users of the current school. Middleware rejects a session or token used on a
   different school's subdomain.
6. Tests: an isolation test for every school-owned model (created in school A, invisible from B,
   cannot be updated/deleted/downloaded from B) and a Pest architecture test that every model in
   `App\Models` except platform models uses `BelongsToSchool`.

## 5. Roles (eleven defaults per school, editable permissions)

Platform: Orange Tree super admin; Orange Tree support (time-limited, logged "view as").
School: Proprietor/owner; Headteacher; Administrator/registrar; Bursar/accountant;
Class teacher; Subject teacher; House master/mistress; Gate/pickup staff (today's pickup lists
and releasing children only); Parent/guardian. A user can hold several roles.

Staff log in with email + password; 2FA required for anyone handling money or approvals.
Parents log in with phone number: activate with a one-time SMS code, then set a password.
One parent account should be able to see children in different schools (open decision —
confirm with Obed before building the guardian/user model).

## 6. Academic structure

academic_years → terms (or semesters; per school) with start, end and census dates; exactly one
current year and term per school. Levels (Creche, Nursery, KG, Primary, JHS, SHS) → classes
(e.g. JHS 2) → streams (JHS 2A). Students join a stream via a yearly **enrolment**; promotions,
transfers and withdrawals create new enrolment records (history is kept). SHS has programmes
(General Science, Business, General Arts, etc.) with core and elective subjects.
teaching_assignments link teacher + subject + stream + year and decide who enters which scores.

Admission numbers: per-school format (e.g. `GHS/2026/0001`), generated in a DB transaction with a
row lock so concurrent admissions never collide.

## 7. Assessment and grading (Phase 2)

- Assessment scheme per level: weighted components (class exercises, tests, project, exam) with
  school-set weights (e.g. 30% CA / 70% exam).
- Grade scales per level, configurable bands: descriptive for Creche/KG (with teacher comments),
  numeric 1–9 (BECE-style), A1–F9 (WASSCE-style), international boards as entered by the school.
- Configurable aggregates (e.g. core subjects + best 2 electives). Class positions on/off per school.
- Promotion rules per level (minimum average, headteacher override).
- Scores lock after submission; changes need admin approval and are logged.
- Report cards: one Blade template per level, rendered with dompdf, school logo and colours;
  generated as queued jobs in batches of 10–20; published to parents only after headteacher
  approval.

## 8. Fees, payments and SaaS billing

- Fee items (tuition, PTA dues, feeding, boarding, transport, books, ...) grouped into fee
  structures per class and day/boarding status; optional items per student.
- Term billing job creates a bill per enrolled student; applies discounts (sibling, staff child),
  scholarships, instalment plans; carries arrears forward.
- `ledger_entries` is append-only. Balance = sum of entries. Corrections are reversing entries.
  Bursars cannot edit or delete entries.
- `RecordPayment` Action for cash, bank deposit and direct MoMo payments with proof upload and
  numbered receipt PDF (receipt numbers per school, generated with a row lock).
- Paystack flow:
  1. PWA calls API to initialise a transaction with a unique reference, the school's subaccount,
     and student + bill ids in metadata.
  2. Parent pays (card/MoMo) on Paystack checkout; money settles to the school.
  3. Webhook (one platform route, school resolved from metadata/reference) verifies the
     `x-paystack-signature` header = HMAC-SHA512 of the raw body with the secret key, then calls
     Paystack's verify endpoint before trusting the payment.
  4. Record once per reference (unique index on reference; idempotent). Issue receipt, notify.
  5. Nightly reconciliation command compares Paystack transactions with the ledger and flags
     mismatches.
  Each school chooses whether it absorbs Paystack charges or passes them to parents.
- SaaS billing: scheduled command on each term's census date counts active enrolments per school
  and creates a `saas_invoice` (count × per-student rate). Grace period, then staff read-only.
  Data is never deleted for non-payment.

## 9. Notifications

All events go through Laravel notifications. Channels in order of cost: in-app (always),
web push, email, SMS, WhatsApp (Phase 3, Meta Business API with approved templates).
Paid channels draw from a per-school credit wallet (`message_credit_ledger`), checked before
sending; never send on credit; warn the bursar when low. Schools choose which event types use
paid channels. Each school has its own SMS sender ID.

## 10. Parent–Teacher Interconnect (PTI)

- One-to-one chat per child: (child, guardian, teacher). Open anytime.
- Class broadcasts from class teachers (one-way; parents reply privately).
- Attachments: images and PDFs up to 5 MB, no video, stored per school.
- Read receipts; polling every 10–15 s while open; web push when closed; for urgent message
  types only, fallback SMS/WhatsApp if push not opened within a school-set delay.
- Context panel for teachers: the child's recent attendance, scores and homework.
- Homework and progress feed for parents.
- Safeguarding defaults (school-configurable): messages never deletable and retained for the
  school's retention period; head/admin can review a thread when flagged or after a complaint
  (every review logged); report button on both sides; phone numbers/emails never shown;
  teacher quiet hours (no notifications, messages still arrive); a school-set response
  expectation shown to parents; no teacher–student messaging; when a teacher leaves, threads move
  to the class teacher.
- Meeting slot booking (Phase 3).

## 11. Authorized pickup and driver verification (PTI, Phase 2)

- Parents register pickup persons (drivers, riders, relatives) in the PWA: name, photo, phone,
  relationship; vehicle type and plate number for drivers/riders. Each is linked to specific
  children and is permanent or valid for set dates.
- Adding a permanent person: parent confirms with a one-time SMS code; the person gets a personal
  pickup PIN. On their first pickup, staff compare the person's face to the photo and mark them
  verified.
- One-time substitute: parent registers before a school-set cut-off (e.g. 1pm); substitute gets a
  one-time code by SMS; class teacher and gate staff alerted immediately.
- Removing a person takes effect immediately.
- Every guardian of the child is notified of any change.
- Restrictions ("not allowed to collect", e.g. custody orders) are set only by head/admin, cannot
  be overridden by parents, and show prominently at handover.
- Handover screen (class teacher or gate staff): today's authorised persons with photos,
  substitutes and restrictions. Staff check face + PIN/code, tap Release → log child, person,
  staff member, time → notify parent immediately. If not verified: do not release, hold the child,
  contact the parent through the app, head/admin decides.
- Boarders on exeat use the same check.

## 12. Boarding (Phase 2)

Houses, dormitories, beds, bed allocations, house masters/mistresses, boarding fee items, exeat
requests from parents with house master approval (pickup uses section 11).

## 13. Data model (main tables)

| Area | Tables |
| --- | --- |
| Platform (no school_id) | schools, plans, saas_invoices, saas_payments, message_credit_ledger, platform users |
| People | users, staff, guardians, students, guardian_student (relationship, access level, primary contact) |
| Academic | academic_years, terms, levels, classes, streams, programmes, subjects, class_subject, teaching_assignments, enrolments |
| Attendance | attendance_sessions, attendance_records |
| Assessment | assessment_schemes, assessment_components, grade_scales, grade_bands, scores, term_results, report_cards |
| Finance | fee_items, fee_structures, fee_structure_items, bills, bill_lines, payments, ledger_entries, discounts, receipts |
| Boarding | houses, dormitories, beds, bed_allocations, exeats |
| Communication | conversations, messages, message_attachments, message_flags, announcements, notifications, homework |
| Pickup | pickup_persons, pickup_person_student, one_time_pickups, pickup_restrictions, handovers |
| System | roles, permissions, activity_log, settings, jobs, failed_jobs |

Conventions: soft deletes on students, guardians, staff; money in integer pesewas with a `Money`
cast; scores, bills and attendance link to an enrolment.

## 14. Security and data protection

- HTTPS + HSTS; 2FA for money/approval roles; rate limits on login and OTP endpoints.
- Sessions bound to one subdomain, idle timeout.
- Files outside public, served through short-lived signed URLs after type/size validation.
- Sensitive fields (e.g. guardian ID numbers) use Laravel encrypted casts.
- Activity log on grades, fees, payments, permissions, pickup, late attendance edits.
- Nightly encrypted off-site backups, 30-day retention; monthly test restore.
- Full data export for a school on exit (Excel + PDFs).
- Ghana Data Protection Act, 2012 (Act 843): schools are controllers, Orange Tree is processor.

## 15. Folder structure

```text
app/
  Actions/<Module>/        business operations, handle() method
  Enums/                   PHP backed enums
  Http/Controllers/Api/V1/ thin API controllers + API Resources
  Http/Middleware/         IdentifySchool, EnsureUserBelongsToSchool, ...
  Livewire/<Module>/       staff screens
  Models/                  Eloquent models
  Models/Concerns/         BelongsToSchool
  Notifications/           one class per notification
  Services/Paystack/       Paystack client
  Services/Sms/            SmsGateway interface + drivers
  Support/Tenancy/         CurrentSchool
resources/js/parent/       Vue 3 parent PWA
tests/Feature/<Module>/    feature tests; tests/Feature/Tenancy/ isolation tests
tests/Unit/                pure calculations
tests/Arch/                architecture tests
```

## 16. CI

GitHub Actions on push and pull request: MySQL (or MariaDB, matching the server) service,
`setup-php` with the server's PHP version, `composer install`, `npm ci && npm run build`,
`pint --test`, `phpstan analyse`, `php artisan test`.
