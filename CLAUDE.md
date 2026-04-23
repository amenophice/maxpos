\# MaXPos — context for Claude Code



\## What we're building

MaXPos is a SaaS point-of-sale system for Romanian shops (MVP) and restaurants (phase 2). Multi-tenant, cloud-hosted backend with offline-first PWA frontend. Will integrate with Saga accounting software via a .NET sync agent that writes to local Firebird DB.



\## Stack

\- Backend: Laravel 11, Filament 4, PHP 8.4, Laravel Sanctum, Laravel Reverb, Spatie Permission, stancl/tenancy (single-DB mode)

\- Frontend: Next.js 14 App Router, TypeScript, Tailwind CSS, shadcn/ui, Framer Motion, Dexie.js for IndexedDB

\- Dev DB: SQLite. Prod DB: MySQL 8.

\- Sync Agent (later phase): .NET 8 Windows Service, WebSocket client, FirebirdSql.Data.FirebirdClient

\- Billing: Laravel Cashier (Stripe)

\- E-Factura: ANAF SPV integration



\## Local dev environment

\- OS: Windows 11

\- Web server: Laravel Herd (manages NGINX + PHP 8.4 automatically — do not install Apache or configure vhosts)

\- Node: v24, package manager: pnpm

\- Repo root: C:\\dev\\MaXPos

\- Sites folder in Herd points to C:\\dev\\MaXPos, so backend/ will auto-serve at http://backend.test

\- Frontend runs via `pnpm dev` on http://localhost:3000



\## Monorepo structure

C:\\dev\\MaXPos\\

├── CLAUDE.md

├── README.md

├── .gitignore

├── backend/      (Laravel 11 + Filament 4)

├── frontend/     (Next.js 14 PWA)

├── sync-agent/   (.NET 8 — phase 4, empty for now)

└── docs/



\## Conventions

\- PSR-12, Laravel Pint for PHP formatting, ESLint + Prettier for TS

\- Code identifiers in English (class Article, method completeReceipt)

\- User-facing text in Romanian (Filament labels, error messages, emails)

\- Every tenant-scoped model uses stancl/tenancy BelongsToTenant trait

\- All migrations must be MySQL-compatible — no SQLite-only SQL, no raw JSON functions that differ between DBs

\- Validation via Form Requests, never inline

\- Tests written in Pest, not PHPUnit

\- API responses wrapped as { data: ..., meta: ... }

\- Money stored as decimal(15,3) or decimal(10,2), never float



\## Domain glossary (Romanian → English)

\- Articol = Article (product)

\- Grupa = Group (category)

\- Gestiune = Stock location

\- Bon fiscal = Receipt (fiscal)

\- Casa de marcat = Fiscal printer / cash register

\- Stornare = Void / refund

\- Raport Z = End-of-day report

\- Sincronizare = Sync (with Saga)

\- Transfer date = Push receipts to Saga



\## What NOT to do

\- No speculative features. Build only what the current task specifies.

\- No adding dependencies without proposing them first.

\- No skipping tests for business logic.

\- No SQLite-specific SQL in migrations.

\- No hardcoded strings in frontend — use next-intl from day one (default: ro).

\- No mock data in production code paths. Seeders live in database/seeders/.



\## Current focus

MVP is shop mode only (operating\_mode = 'shop'). Restaurant mode is in data model but not built yet.



\## Roadmap

1\. Multi-tenant bootstrap + auth + onboarding wizard

2\. Nomenclatoare (articles, groups, gestiuni, customers) — Filament CRUD

3\. Receipt domain + API for POS

4\. Next.js PWA scaffold

5\. POS shop screen with offline queue

6\. Sync Agent (.NET) + WebSocket protocol

7\. Fiscal printer integration (Print Server pattern)

8\. E-Factura

9\. Billing + self-service onboarding

