# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What we're building

MaXPos is a SaaS point-of-sale system for Romanian shops (MVP) and restaurants (phase 2). Multi-tenant, cloud-hosted backend with offline-first PWA frontend. Will integrate with Saga accounting software via a .NET sync agent that writes to local Firebird DB.

Current focus: MVP is shop mode only (`operating_mode = 'shop'`). Restaurant mode is in data model but not built yet.

## Stack

- **Backend:** Laravel 11, Filament 4, PHP 8.4, Laravel Sanctum, Laravel Reverb, Spatie Permission, stancl/tenancy (single-DB mode). Dev DB: SQLite. Prod DB: MySQL 8.
- **Frontend:** Next.js 16, React 19, TypeScript, Tailwind CSS 4, shadcn/ui (new-york style), Framer Motion, Dexie.js (IndexedDB), next-intl, Zustand, TanStack React Query, Serwist (PWA/service worker).
- **Sync Agent (later phase):** .NET 8 Windows Service, WebSocket client, FirebirdSql.Data.FirebirdClient.
- **Billing:** Laravel Cashier (Stripe).
- **E-Factura:** ANAF SPV integration (not yet built).

## Monorepo structure

```
source/
├── backend/      # Laravel 11 + Filament 4
├── frontend/     # Next.js 16 PWA
├── docs/         # API endpoint reference
└── sync-agent/   # .NET 8 — phase 4, empty for now
```

## Production deployment

This server (`api.maxpos.ro`) is the live production environment — not a local dev setup.

- **Backend:** PHP 8.4 served via the web server at `api.maxpos.ro`. No `php artisan serve` needed.
- **Frontend:** Next.js running via PM2 on port 3010. Restart after build: `pm2 restart maxpos` (or whatever the process name is).
- **Git remote:** `https://github.com/amenophice/maxpos.git`

⚠ Do NOT modify `.env` files or run `php artisan migrate` without explicit approval — changes are immediately live.

## Build & dev commands

### Backend (from `source/backend/`)

```bash
composer install                       # Install PHP dependencies
php artisan serve                      # Dev server (local dev only, not needed on prod)
php artisan migrate                    # Run migrations (⚠ ask before running on prod)
php artisan db:seed                    # Seed demo data (RolesSeeder, SuperAdminSeeder, DemoShopSeeder)
php artisan test                       # Run full Pest test suite
php artisan test --filter=PosCheckout  # Run a single test by name
php artisan test tests/Feature/Pos/    # Run tests in a directory
vendor/bin/pint                        # Format PHP (PSR-12, Laravel Pint)
vendor/bin/pint --test                 # Check formatting without fixing
```

Testing uses in-memory SQLite (`phpunit.xml`). Tests are written in Pest, not PHPUnit.

### Frontend (from `source/frontend/`)

```bash
pnpm install          # Install dependencies
pnpm dev              # Dev server on http://localhost:3000 (local dev)
pnpm build            # Production build (run this on prod, then restart PM2)
pnpm lint             # ESLint
pnpm test             # Vitest — runs once (vitest run)
pnpm test:watch       # Vitest — watch mode
```

## Architecture

### Backend

**Multi-tenancy:** stancl/tenancy in single-DB mode. Tenant is resolved from the authenticated user's `tenant_id` via `InitializeTenancyForAuthenticatedUser` middleware. Every tenant-scoped model uses the `BelongsToTenant` trait. Super admin (`tenant_id = null`) sees all tenants.

**API layer:** REST endpoints under `/api/v1/` (7 controllers in `App\Http\Controllers\Api\V1`). Routes defined in `routes/api.php` and `routes/tenant.php`. Auth via Sanctum bearer tokens. All responses follow `{ data: ..., meta: ... }` envelope. Money values returned as decimal strings, never floats.

**Key controllers:** AuthController, MeController, ArticleController, CustomerController, CashSessionController, PosController (bootstrap + checkout), ReceiptController (CRUD + items + discount + complete + void).

**Business logic:** `App\Services\ReceiptService` — central service handling receipt creation, item management, discounts, completion (with payment validation and stock level updates), and voiding. Receipt numbering is gapless per-location using `SELECT ... FOR UPDATE` locking.

**Admin panel:** Filament 4 resources for nomenclatoare (Articles, Groups, Gestiuni, Locations, Customers, Barcodes, StockLevels) with full CRUD, relation managers, and custom table/schema classes.

**Permissions:** Spatie Permission with roles (admin, seller, manager, tenant-owner). Key permissions: `pos.sell`, `pos.open-session`, `pos.close-session`, `pos.discount`, `pos.void`.

### Frontend

**Offline-first PWA:** Dexie.js IndexedDB stores catalog (articles, groups, customers, gestiuni). Zustand persists draft receipt to localStorage. Serwist service worker caches static assets. Pending receipts queue syncs when online. Frontend tests live in `src/__tests__/`.

**Auth flow:** Backend Sanctum token stored in httpOnly cookie (`maxpos_session`). `/api/session/token` route retrieves it for client-side API calls. Middleware redirects unauthenticated users to `/login`.

**State:** `pos-store.ts` (Zustand) — draft receipt with lines, customer, discounts, computed totals. `online-store.ts` — network connectivity.

**Data sync:** `lib/sync/bootstrap-sync.ts` pulls catalog from `/pos/bootstrap` (incremental via `?since=`). `lib/sync/receipts-sync.ts` processes offline receipt queue.

**Key pages:** `/login`, `/` (dashboard), `/sale` and `/vanzare` (POS screen), `/stock`, `/receipts`, `/reports`, `/settings`.

**Search:** IndexedDB full-text search with diacritic-aware normalization for Romanian (ă, ș, ț → a, s, t).

**Money:** Decimal.js for all arithmetic — no floating point.

## Conventions

- PSR-12 + Laravel Pint for PHP; ESLint + Prettier for TypeScript.
- Code identifiers in English (`class Article`, `method completeReceipt`).
- User-facing text in Romanian (Filament labels, error messages, frontend via next-intl, default locale: `ro`).
- Validation via Form Requests, never inline.
- Money stored as `decimal(15,3)` or `decimal(10,2)`, never float.
- All migrations must be MySQL-compatible — no SQLite-only SQL.
- Frontend: no hardcoded strings — use next-intl. No mock data in production code paths.
- Path alias: `@/*` maps to `./src/*` in frontend.

## Domain glossary (Romanian to English)

- Articol = Article (product)
- Grupa = Group (category)
- Gestiune = Stock location
- Bon fiscal = Receipt (fiscal)
- Casa de marcat = Fiscal printer / cash register
- Stornare = Void / refund
- Raport Z = End-of-day report
- Sincronizare = Sync (with Saga)
- Vanzare = Sale

## What NOT to do

- No speculative features. Build only what the current task specifies.
- No adding dependencies without proposing them first.
- No skipping tests for business logic.
- No SQLite-specific SQL in migrations.
- No mock data in production code paths. Seeders live in `database/seeders/`.
