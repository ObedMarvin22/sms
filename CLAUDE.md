# Orange Tree School Management SaaS — instructions for Claude Code

You are building a multi-school SaaS (school management system + Parent–Teacher Interconnect)
for Orange Tree Technologies, Ghana. The full specification is in `docs/SPEC.md` and the build
order is in `docs/ROADMAP.md`. Read both before starting any work. Progress is tracked in
`docs/PROGRESS.md` and decisions in `docs/DECISIONS.md` (create both in the first session).

## Who you are working with

- Obed is an experienced .NET developer (C#, ASP.NET WebForms/MVC, .NET 8, Blazor) who is
  learning Laravel through this project. He wants to understand everything that gets built.
- When you introduce a Laravel concept, explain it in 2–4 sentences, and compare it to the .NET
  equivalent where one exists (middleware ≈ ASP.NET Core middleware, Eloquent ≈ EF Core,
  service container ≈ built-in DI, Form Requests ≈ model validation, Artisan ≈ dotnet CLI).
- Keep explanations short and practical. Code first, then the why.

## How to work

1. Work on ONE roadmap step at a time, in order. Never jump ahead.
2. Before writing code for a step, give a short plan: files you will create or change, and the tests.
3. Create a branch per step: `s<sprint>/<short-topic>` (e.g. `s1/identify-school-middleware`).
4. Write the feature test(s) alongside the code. Run `php artisan test` and fix failures before
   saying a step is done. Run `vendor/bin/pint` and `vendor/bin/phpstan analyse` too.
5. Commit in small commits with prefixes: `feat:`, `fix:`, `test:`, `refactor:`, `chore:`, `docs:`.
6. After each step, write a short summary in this exact shape:
   - **Built:** what now exists
   - **Laravel concepts:** what Obed should understand from this step
   - **Verify it yourself:** commands or browser steps to see it working
7. Tick the step in `docs/PROGRESS.md`. Record any non-obvious choice in `docs/DECISIONS.md`
   (date, decision, reason, alternatives).
8. STOP at the end of every sprint. Summarize the sprint, list anything deferred, and wait for
   Obed's approval before starting the next sprint.

## Ask Obed before you

- add a Composer or npm package that is not listed in `docs/SPEC.md`
- change any decision in the "Fixed decisions" section below
- write a migration that drops or renames a column or table with data
- touch anything related to production, live Paystack keys, or real SMS sending
- skip, merge or reorder roadmap steps

## Fixed decisions (do not change without asking)

- Laravel (latest stable), PHP version matching what Namecheap Stellar Plus offers, MySQL/MariaDB
  matching the server. Pest for tests. Livewire + Alpine + Tailwind for staff screens.
- Parent PWA: Vue 3 in `resources/js/parent`, built by Vite, mounted on one Blade page at
  `/parent` on each school's subdomain. It talks ONLY to the versioned API `/api/v1` (Sanctum).
- Multi-tenancy: ONE database, every school-owned table has an indexed `school_id`. School is
  resolved from the subdomain (`{school}.APP_BASE_DOMAIN`). Hand-built, no tenancy package.
- Orange Tree console runs on `admin.APP_BASE_DOMAIN`.
- Shared hosting: no long-running processes, no websockets, no Node on the server, no headless
  Chrome. Queues use the `database` driver and run from cron via the scheduler. PDFs use dompdf.
  Real-time features use polling (10–15 s) plus web push.
- Money is stored as integer pesewas. The fee ledger is append-only; corrections are reversals.
- Fee payments settle directly to each school via Paystack subaccounts. Orange Tree never holds
  school money.

## Non-negotiable rules

- Every school-owned model uses the `BelongsToSchool` trait (global scope + auto-fill school_id).
  An architecture test enforces this. Never use `withoutGlobalScopes()` outside platform code,
  and when you must, add a comment explaining why.
- Unique indexes on school-owned tables are composite with `school_id`.
- Business rules live in Action classes (`app/Actions/<Module>/<Verb><Noun>.php`, method
  `handle()`). Livewire components, API controllers, jobs and commands only call Actions.
- Every Action has a feature test. Tenancy, money and grading code must be fully tested.
- Tests never call real external APIs: use `Http::fake()`, `Notification::fake()`, `Queue::fake()`.
- Tests use a real MySQL test database (`orange_sms_test`), not SQLite.
- Never commit secrets. All keys come from `.env`; keep `.env.example` up to date.
- Every change to grades, fees, payments, permissions, pickup persons and attendance after lock
  is written to the activity log.
- Phone numbers are stored normalised in `+233XXXXXXXXX` format.
- Use PHP backed enums for fixed sets of values (statuses, term types, roles' internal keys).

## Local development

- Run the app with `php artisan serve`; schools are reached at `http://<subdomain>.localhost:8000`
  and the console at `http://admin.localhost:8000`. `APP_BASE_DOMAIN=localhost`.
- Seed two demo schools, `greenhill` and `hillview`, with realistic data (about 600 students
  each) via `php artisan migrate:fresh --seed`.
- Emails go to Mailpit locally. SMS uses a `log` driver locally.
