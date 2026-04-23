# Changelog

All notable changes to the MaXPos project are documented here.

## [Unreleased] — 2026-04-23 — Backend bootstrap (Prompt 1)

### Added

- **Monorepo layout** at `C:\dev\MaXPos` with `backend/`, `frontend/`, `sync-agent/`, `docs/` folders plus root `README.md` and `.gitignore` (PHP, Node, .NET, OS). `git init` run at repo root.
- **Laravel 11** (`^11.31`) installed fresh into `backend/` via `composer create-project`.
- **SQLite dev DB** at `backend/database/database.sqlite`. `.env` and `.env.example` configured: `APP_NAME=MaXPos`, `APP_URL=http://backend.test`, `APP_LOCALE=ro`, `APP_FALLBACK_LOCALE=en`, `APP_TIMEZONE=Europe/Bucharest`, `SANCTUM_STATEFUL_DOMAINS=localhost:3000,backend.test`, `FRONTEND_URL=http://localhost:3000`.
- **Composer packages**:
  - `filament/filament` v4.11.0 (resolved from `^4.0`)
  - `stancl/tenancy` v3.10.0
  - `spatie/laravel-permission` v6.25.0
  - `laravel/sanctum` v4.3.1
  - `laravel/cashier` v16.5.1 (installed; migrations/config not published yet)
  - dev: `pestphp/pest` v3.8.6, `pestphp/pest-plugin-laravel` v3.2, `laravel/pint` v1.29.1 (shipped)
- **Filament admin panel** at `/admin`: brand name `MaXPos Admin`, amber primary colour, Romanian UI via `APP_LOCALE=ro` (Filament ships `ro` translations).
- **User model** implements `FilamentUser`; access gated on `super-admin` role (Spatie). `HasApiTokens`, `HasRoles`, `Notifiable` traits wired.
- **Seeders** `RolesSeeder` (creates `super-admin`, `tenant-owner`, `cashier` roles) and `SuperAdminSeeder` (creates `admin@maxpos.ro` / `password`, assigns `super-admin`). Wired into `DatabaseSeeder`.
- **stancl/tenancy single-database mode**: `DatabaseTenancyBootstrapper` removed from `config/tenancy.php`; `central_domains` set to `[127.0.0.1, localhost, backend.test]`. Subdomain middleware not wired yet — future prompt.
- **Custom `Tenant` model** at `app/Models/Tenant.php` extending `Stancl\Tenancy\Database\Models\Tenant` with `HasDatabase`, `HasDomains` and `getCustomColumns()` returning `[id, name, cui, operating_mode, trial_ends_at, subscription_status]`. Wired via `config/tenancy.php::tenant_model`.
- **Tenants migration** `2019_09_15_000010_create_tenants_table.php` extended with: `uuid id` (primary), `name`, `cui` (nullable unique), `operating_mode` enum (`shop|restaurant|mixed`, default `shop`), `trial_ends_at` (nullable timestamp), `subscription_status` (nullable string), `data` (nullable json), timestamps.
- **Sanctum SPA stateful auth**: `statefulApi()` middleware wired in `bootstrap/app.php`, `routes/api.php` added with `/api/user` behind `auth:sanctum`.
- **CORS** published to `config/cors.php` and configured: allowed origins `http://localhost:3000` (from `FRONTEND_URL`) and `http://backend.test`, `supports_credentials=true`, paths include `api/*`, `sanctum/csrf-cookie`, `login`, `logout`.
- **Pest tests** (`backend/tests/Feature/`):
  - `AppBootsTest` — `/` returns 200; `/up` health check returns 200
  - `AdminPanelTest` — unauthenticated `/admin` redirects to `/admin/login`; seeded super admin authenticates and reaches the panel
  - `TenantTest` — tenant can be created and persisted; unknown `operating_mode` rejected by DB
  - `tests/Pest.php` extended with `RefreshDatabase`; `phpunit.xml` configured for in-memory SQLite during tests
- `composer audit` clean — no vulnerability advisories after upgrading Filament to v4.11.0.

### Not done (reserved for later prompts)

- Tenant-specific tables (articles, receipts, etc.) — Prompt 2.
- Tenant subdomain identification middleware wiring.
- `laravel/cashier` migrations/config publishing.
- `laravel/reverb` (listed in stack but not a backend-bootstrap package).
- Frontend and sync-agent folders intentionally left empty.

## Manual verification checklist

Herd auto-serves `backend/` at `http://backend.test`. No `php artisan serve` needed.

- [ ] **Welcome page** — Open http://backend.test → should render the Laravel welcome page (status 200).
- [ ] **Health check** — Open http://backend.test/up → JSON/text 200 response.
- [ ] **Admin login (Romanian)** — Open http://backend.test/admin → redirects to `/admin/login`. UI strings (button labels, placeholders) should render in Romanian because `APP_LOCALE=ro`.
- [ ] **Super admin login** — From `/admin/login`, sign in with `admin@maxpos.ro` / `password`. Should land on the Filament dashboard with the `MaXPos Admin` brand name.
- [ ] **Pest green** — From `backend/`, run `./vendor/bin/pest`. Expected: 6 tests, all passing, ~12s.
- [ ] **Pint clean** — From `backend/`, run `./vendor/bin/pint --test`. Expected: no changes required.
- [ ] **composer audit** — From `backend/`, run `composer audit`. Expected: no advisories.

## Known notes

- `composer.json` initially resolved `filament/filament` to v4.0.0 because PowerShell consumed the caret in `composer require "filament/filament:^4.0"`. The constraint in `composer.json` has been corrected to `^4.0` and the lock file now pins v4.11.0. Two CVEs present in v4.0.0 (recovery-codes reuse, XSS via summarizer) are resolved at this version.
