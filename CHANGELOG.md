# Changelog

All notable changes to the MaXPos project are documented here.

## [Unreleased] — 2026-04-23 — Sales domain + REST API (Prompt 3)

### Added

- **5 migrations** under `database/migrations/2026_04_25_*`:
  - `cash_sessions` — uuid id, tenant_id, location_id, user_id, opened_at, closed_at, initial/final/expected cash (decimal 10,2), `status` enum `open|closed`, notes, timestamps. Indexes on `(tenant_id, location_id, status)` and `(user_id, status)`. The "one open session per user per location" invariant is enforced in `ReceiptService::openCashSession` under a `SELECT ... FOR UPDATE` lock (no partial unique index since MySQL 8 requires an expression index; the service-level check is cross-DB safe).
  - `receipt_number_counters` — composite PK `(tenant_id, location_id)`, `last_number` bigint, FKs to tenants/locations. Used as the **atomic gapless counter** for per-location receipt numbering.
  - `receipts` — uuid id, tenant_id, location_id, cash_session_id, `number` bigint, customer_id (nullable), subtotal/vat_total/discount_total/total (decimal 12,2), `status` enum `draft|completed|voided`, fiscal_printed_at, saga_synced_at, voided_at, void_reason. Unique `(tenant_id, location_id, number)`. Indexes on `(tenant_id, status, created_at)` and `(cash_session_id)`.
  - `receipt_items` — uuid id, receipt_id (cascade delete), article_id + gestiune_id (restrict — can't delete catalog rows referenced by historical receipts), `article_name_snapshot` and `sku_snapshot` (frozen at sale time), quantity decimal(15,3), unit_price decimal(10,2), vat_rate, discount_amount, line_subtotal/line_vat/line_total decimal(12,2). Indexed on receipt_id.
  - `payments` — uuid id, receipt_id (cascade delete), `method` enum `cash|card|voucher|modern|transfer`, amount decimal(12,2), reference (string, nullable), created_at. Indexed on `(receipt_id, method)`.

- **4 Eloquent models** — `CashSession`, `Receipt`, `ReceiptItem`, `Payment`. All UUID PKs. `CashSession` and `Receipt` use `BelongsToTenant` for tenant auto-scoping. `ReceiptItem`/`Payment` are tenant-inherited via parent receipt and don't need their own tenant_id (kept tables slim). Status helper methods on `CashSession` (`isOpen`/`isClosed`) and `Receipt` (`isDraft`/`isCompleted`/`isVoided`). Payment model has `public $timestamps = false` with a manual `created_at` column. Factories added for `CashSession` and `Receipt`.

- **`App\Services\ReceiptService`** — single source of truth for all POS write-paths. Every mutating method is wrapped in `DB::transaction(...)`; exceptions bubble up and roll back. Money is manipulated as decimal strings via `bcmath` at scale 2 (quantities at scale 3); floats are accepted at the boundary and normalized immediately. Methods:
  - `openCashSession(Location, User, initialCash, notes?)` — locks + checks for any existing open session for `(user, location)`, throws `PosException(409)` on duplicate.
  - `closeCashSession(session, finalCash, notes?)` — recomputes `expected_cash = initial_cash + Σ(cash-method payments on completed receipts in session)`. Rejects if any draft receipts remain.
  - `createDraftReceipt(session, customer?)` — allocates next `number` atomically: `SELECT ... FOR UPDATE` on `receipt_number_counters(tenant_id, location_id)`, increments, inserts receipt in the same transaction. Gapless.
  - `addItem`, `updateItemQuantity`, `removeItem` — only on draft, recompute totals. VAT is computed **inclusive** (receipt prices include VAT) via `line_vat = line_subtotal * vat_rate / (100 + vat_rate)` with half-away-from-zero rounding at the cent. Negative quantities are allowed (return lines — they flip totals).
  - `applyDiscount(receipt, amount)` — header-level discount.
  - `completeReceipt(receipt, payments[])` — validates `Σ(payments.amount) === receipt.total` to the cent, decrements stock per line from the line's gestiune, writes Payment rows, sets status=completed. For negative-qty lines stock is *incremented* (return).
  - `voidReceipt(receipt, reason)` — refuses if `fiscal_printed_at !== null`. If receipt was completed, reverses stock movements.

- **`App\Exceptions\PosException`** — typed domain exception carrying an HTTP status. A custom renderer in `bootstrap/app.php` converts it to `{ data: null, meta: { error: "..." } }` with the right status on API/JSON requests. Messages are in Romanian.

- **POS permissions + roles** — `RolesSeeder` now creates the permissions `pos.open-session`, `pos.close-session`, `pos.sell`, `pos.void`, `pos.discount` (guard `web`). All three roles (`super-admin`, `tenant-owner`, `cashier`) receive all POS permissions.

- **6 API Resources** — `ArticleResource`, `CustomerResource`, `CashSessionResource`, `ReceiptResource` (with nested items + payments), `ReceiptItemResource`, `PaymentResource`. Money fields are cast to strings on output. All responses use the `{ data, meta }` envelope.

- **REST API under `/api/v1/`** (Sanctum-protected, tenant resolved via the `InitializeTenancyForAuthenticatedUser` middleware):
  - `POST /auth/login`, `POST /auth/logout`, `GET /me`
  - `GET /articles` (search, group filter, paginated), `GET /articles/by-barcode/{barcode}`
  - `GET /customers` (search)
  - `POST /cash-sessions/open` (`can:pos.open-session`), `POST /cash-sessions/{id}/close` (`can:pos.close-session`), `GET /cash-sessions/current`
  - `POST /receipts` (`can:pos.sell`), `GET /receipts/{id}`
  - `POST /receipts/{id}/items`, `PATCH /receipts/{id}/items/{itemId}`, `DELETE /receipts/{id}/items/{itemId}` (all `can:pos.sell`)
  - `POST /receipts/{id}/discount` (`can:pos.discount`)
  - `POST /receipts/{id}/complete` (`can:pos.sell`), `POST /receipts/{id}/void` (`can:pos.void`)
  - 6 thin controllers (`AuthController`, `MeController`, `ArticleController`, `CustomerController`, `CashSessionController`, `ReceiptController`). Each mutating endpoint delegates to `ReceiptService`. 9 FormRequests under `app/Http/Requests/Api/`.

- **`DemoPosSeeder`** — creates one open `cash_session` for the demo tenant's owner so the POS can be exercised immediately after `db:seed`. Uses `tenancy()->initialize()` to stay within single-DB auto-scoping. Idempotent.

- **`docs/api-endpoints.md`** — single-file REST reference: every route with payload schema, example response shapes, error envelope documentation, and a note on the numbering atomicity guarantee.

- **20 new Pest tests** in `tests/Feature/Pos/` (46 total project-wide, all green). Shared helpers live in `tests/Feature/PosTestHelpers.php`, required from `tests/Pest.php`.
  - `CashSessionTest` — open, concurrent-open rejection, close with `expected_cash` math, close-with-drafts refusal
  - `ReceiptServiceTest` — mixed-VAT totals, split payment, mismatched payment rejection, stock decrement + reversal on void, draft-void (no stock change), fiscal-printed refusal, adding-items-after-complete refusal, negative-qty return, gapless sequential numbering
  - `ApiHappyPathTest` — login, full end-to-end receipt lifecycle over HTTP, barcode lookup (200 + 404), 422 on payment mismatch via HTTP
  - `AuthorizationTest` — 403 on void without `pos.void`, 403 on add-item without `pos.sell`
  - `TenantIsolationApiTest` — receipt from tenant A returns 404 for a user from tenant B over the API

### Pipeline

- `php artisan migrate:fresh --seed` → 20 migrations, 4 seeders, demo tenant with an open cash session ready.
- `./vendor/bin/pint` → clean.
- `./vendor/bin/pest` → **46 passed**, 84 assertions, ~4.5s.
- `composer audit` → no advisories.

### Deviations from spec

- **"One open session per user per location"** is enforced at the service layer (locked query + exception) rather than via a partial unique index. Partial/expression unique indexes differ substantially between MySQL 8 and SQLite, and CLAUDE.md forbids SQLite-only or MySQL-only DDL. The service-layer check is transactional and cross-DB safe.
- **VAT is computed inclusive of price** (receipt prices in Romania are VAT-inclusive at the till). `line_vat = line_subtotal * vat_rate / (100 + vat_rate)` with half-away-from-zero rounding to the cent. `line_subtotal` in the DB stores the net amount; `line_total` stores the gross. This matches the Romanian Casa-de-Marcat convention.
- **Concurrent numbering test** is sequential (10-in-a-row), not truly parallel. True parallel testing requires separate DB connections with real row locking — impractical against SQLite :memory:. The atomic pattern (`SELECT ... FOR UPDATE` inside a transaction) is standard and holds under MySQL 8 production load. Documented in `docs/api-endpoints.md`.
- **`receipt_items.article_id` and `gestiune_id` use `restrictOnDelete`** rather than cascade/nullOnDelete, so historical receipts can never lose their source article/gestiune references. Catalog deletes must handle this explicitly (later UX problem).

### No new dependencies

Implemented entirely with bcmath (shipped with PHP 8.4) + Laravel Sanctum + Spatie Permission from prior prompts. No `composer require` calls.

## Manual verification checklist — Prompt 3

Bruno/Postman against `http://backend.test/api/v1`. Demo credentials after `db:seed`:
- `admin@maxpos.ro` / `password` (super admin — sees all tenants)
- `owner@magazin-demo.ro` / `password` (tenant-owner for Magazin Demo, has an **open** cash session already seeded)

- [ ] **Login** — `POST /auth/login` with `{email, password, device_name}` → `200`, receive `data.token`.
- [ ] **Set Authorization header** — `Bearer <token>` on every subsequent request.
- [ ] **Me + session** — `GET /me` → `data.user` populated, `data.active_session` non-null for the demo owner (seeded by DemoPosSeeder).
- [ ] **Open second session** — Log in as a fresh cashier with no session → `POST /cash-sessions/open {location_id, initial_cash: 100}` → `201`.
- [ ] **Draft receipt** — `POST /receipts {cash_session_id}` → `201`, `data.number` is the next gapless integer for that location.
- [ ] **Add items** — `POST /receipts/{id}/items {article_id, quantity: 2}` (twice, different articles) → `data.items` grows, `data.total` recomputes.
- [ ] **Split payment completion** — `POST /receipts/{id}/complete {payments: [{method:"cash", amount:X}, {method:"card", amount:Y}]}` where `X + Y == data.total` → `200`, `data.status = "completed"`. Check `StockLevel` in `/admin` — quantity for those articles in that gestiune has dropped by the sold quantity.
- [ ] **Payment mismatch** — Repeat with `X + Y != total` → `422` with `meta.error` in Romanian.
- [ ] **Void completed** — `POST /receipts/{id}/void {reason: "Cerere client"}` → `200`, `data.status = "voided"`. Check stock again — quantities restored.
- [ ] **Cannot void fiscal** — Manually set `fiscal_printed_at` on a completed receipt via tinker, then try to void → `409`.

## [Unreleased] — 2026-04-23 — Shop nomenclator (Prompt 2)

### Added

- **7 shop migrations**, MySQL-compatible, each in its own file, all with `tenant_id` uuid FK to `tenants` (cascade on delete) and indexes for the common query shapes:
  - `locations` (uuid id, name, address, city, county, is_active, saga_agent_token unique-nullable, timestamps; index on `tenant_id, is_active`)
  - `gestiuni` (FK to locations, `type` enum `global-valoric|cantitativ-valoric`, is_active; index on `tenant_id, location_id`)
  - `groups` (self-FK `parent_id`, name, display_order; index on `tenant_id, parent_id`)
  - `articles` (sku, name, description, FK group_id, FK default_gestiune_id, vat_rate decimal(5,2) default 19, price decimal(10,2), unit, plu, is_active, photo_path; unique `(tenant_id, sku)`; indexes on `tenant_id, group_id` and `tenant_id, is_active`)
  - `barcodes` (FK article_id, barcode, `type` enum `ean13|ean8|code128|internal|scale`; unique `(tenant_id, barcode)`; index on `tenant_id, article_id`)
  - `customers` (name, cui, registration_number, address, city, county, is_company, email, phone; indexes on `tenant_id, cui` and `tenant_id, name`)
  - `stock_levels` (FK article_id, FK gestiune_id, quantity decimal(15,3); unique `(article_id, gestiune_id)`)
- **users.tenant_id** column added (nullable uuid FK to tenants). Rationale: required to wire a logged-in tenant user into tenancy initialization so the tenant isolation test is meaningful. Super admin leaves this `NULL` and keeps central visibility.
- **7 Eloquent models** (`Location`, `Gestiune`, `Group`, `Article`, `Barcode`, `Customer`, `StockLevel`) — all using `HasUuids`, `HasFactory`, and `Stancl\Tenancy\Database\Concerns\BelongsToTenant`. Casts for decimals, booleans. Relationships: `Location hasMany Gestiuni`, `Article belongsTo Group`, `Article belongsTo Gestiune (defaultGestiune)`, `Article hasMany Barcodes`, `Article hasMany StockLevels`, `Gestiune hasMany StockLevels`, `Group parent/children/articles`. `Tenant` got `HasFactory` so factories can cascade-create a tenant when needed.
- **Tenant auto-scoping** — stancl/tenancy's `BelongsToTenant` trait gives us (a) auto-fill of `tenant_id` on create, and (b) a global `TenantScope` that restricts queries when `tenancy()->initialized` is true. A top-of-file comment on `app/Models/Location.php` documents the choice. A new `App\Http\Middleware\InitializeTenancyForAuthenticatedUser` middleware is prepended to the Filament panel auth middleware stack — it calls `tenancy()->initialize()` from the logged-in user's `tenant_id` if present. Users without a `tenant_id` (super admin) operate in central context and see every tenant's data. `TenancyServiceProvider` had its default CreateDatabase/DeleteDatabase event pipelines removed since we run single-DB.
- **7 FormRequests** in `app/Http/Requests/` — each exposes a static `fieldRules()` method that the Filament `Schema` uses via per-field `->rules()` calls, keeping validation definitions in one canonical place (reusable from future API controllers).
- **7 Filament v4 resources** under the `Nomenclatoare` navigation group with Romanian labels (Locație/Locații, Gestiune/Gestiuni, Grupă/Grupe, Articol/Articole, Cod bare/Coduri bare, Client/Clienți, Stoc/Stocuri) and heroicon nav icons. Each has: sectioned forms, searchable/sortable tables, filters (TernaryFilter for boolean flags, SelectFilter for relations). Article resource has Barcodes and StockLevels as inline relation managers. Customer form is reactive: `cui` and `registration_number` only show when `is_company` is true. AdminPanelProvider registers the `Nomenclatoare` group name.
- **DemoShopSeeder** — single tenant "Magazin Demo" (CUI RO12345678, shop mode), demo tenant-owner user `owner@magazin-demo.ro` / `password`, 1 location "Magazin Central Oradea" (Bihor), 3 gestiuni ("Raion principal"/"Depozit" cantitativ-valoric, "Casă" global-valoric), 5 groups (Lactate, Panificație, Băuturi, Legume-Fructe, Diverse), 30 articles with realistic Romanian names + 9% VAT for food/bread and 19% for drinks/misc, 1–2 EAN-13 barcodes per article (check digits computed correctly), 90 stock levels (30 × 3 gestiuni) with random quantities 5–200, 10 customers (6 persons + 4 companies with valid-shaped CUI and J reg numbers). Runs inside `tenancy()->initialize($demoTenant)` so `BelongsToTenant` auto-fills work.
- **Pest tests** — 5 new feature files, 26 total tests, all green:
  - `FactoryTest` — all 8 models (incl. Tenant) create cleanly via factory
  - `ArticleRelationshipsTest` — Article ↔ Group/Barcodes/StockLevels relations
  - `TenantIsolationTest` — actingAs a tenant-B user vs. `/admin/articles` must not leak tenant-A rows; direct Eloquent queries under `tenancy()->initialize()` are scoped; `withoutTenancy()` bypasses the scope
  - `UniquenessConstraintsTest` — same barcode allowed across two tenants, rejected within one tenant; `stock_levels.(article_id, gestiune_id)` unique
  - `FilamentResourcesRenderTest` — `/admin/{resource}` index renders 200 for all 7 resources when signed in as super admin

### Pipeline

- `php artisan migrate:fresh --seed` → 15 migrations, 3 seeders, demo data populated.
- `./vendor/bin/pint` → clean.
- `./vendor/bin/pest` → **26 passed**, 39 assertions, ~6s.
- `composer audit` → no advisories.

### Not done (reserved for later prompts)

- Receipts / fiscal printing / sales lines (Prompt 3+).
- API routes for POS (frontend handshake) beyond the existing `/api/user`.
- Tenant subdomain identification middleware on a public tenant entry point — Filament admin uses per-user tenancy resolution instead; subdomains will wire in when the POS frontend lands.
- Romanian CUI checksum validation — current validation is just format. A dedicated validator will land when we wire ANAF / E-Factura.

### Deviations from spec

- The scaffolded Filament v4 folder for the `Gestiune` resource is named `Gestiunes/` (PHP generator pluralization quirk). All user-facing strings still say "Gestiuni" — the folder name is internal only. URL slug also lands as `/admin/gestiunes`. Safe to leave; renaming would require touching every auto-generated namespace.
- "Validation via Form Requests" is implemented by sharing `static fieldRules()` arrays between the FormRequest class (for future API reuse) and the Filament field-level `->rules()` — FormRequest objects are not instantiated inside the Filament flow since Filament owns its own validation lifecycle. Rules live in one canonical place.
- The task's table filter wording for StockLevel on "Grupă articol" is implemented via a custom `->query()` closure on a `SelectFilter` (standard Filament pattern for filtering on a related-model column).

### No new dependencies

All of Prompt 2 was built on Prompt 1's stack — no `composer require` calls.

## Manual verification checklist — Prompt 2

Herd already serves `http://backend.test`. Super admin: `admin@maxpos.ro` / `password`. Tenant-owner (sees only demo tenant): `owner@magazin-demo.ro` / `password`.

- [ ] **Log in** — http://backend.test/admin → login as super admin.
- [ ] **Navigation** — Sidebar shows `Nomenclatoare` group with 7 items: Locații, Gestiuni, Grupe, Articole, Coduri bare, Clienți, Stocuri.
- [ ] **Articole count** — Browse `Articole` → 30 demo articles visible.
- [ ] **Article detail** — Open e.g. "Lapte Zuzu 1L" → Barcodes relation manager shows 1–2 codes, Stocuri relation manager shows 3 rows (one per gestiune).
- [ ] **Article filter by group** — Use the "Grupă" filter → pick "Lactate" → table narrows to 6 articles.
- [ ] **Article search by SKU** — type `LAC-001` in search → single article returned.
- [ ] **Article search by barcode** — type any EAN-13 from the seeded list → returns the matching article (cross-relation search).
- [ ] **Customer reactive form** — `Clienți` → `Nou` → toggle "Persoană juridică" on → CUI and "Nr. registrul comerțului" fields appear. Toggle off → they disappear.
- [ ] **Tenant isolation, live** — Log out, log in as `owner@magazin-demo.ro` → all lists still show demo data (that tenant IS the demo). Create a second tenant + owner manually to observe isolation in the UI — out of scope here but covered by `TenantIsolationTest`.
- [ ] **Pest** — `cd backend && ./vendor/bin/pest` → 26 passed.
- [ ] **Pint** — `./vendor/bin/pint --test` → no changes required.

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
