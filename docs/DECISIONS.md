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
