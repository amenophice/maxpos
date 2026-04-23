# MaXPos

SaaS point-of-sale for Romanian shops (MVP) and restaurants (phase 2). Multi-tenant, cloud backend with an offline-first PWA storefront. Integrates with Saga accounting via a local .NET sync agent that writes to a local Firebird DB.

## Stack

- **Backend** — Laravel 11, Filament 4, PHP 8.4, Laravel Sanctum, Laravel Reverb, Spatie Permission, stancl/tenancy (single-DB mode). Dev DB: SQLite; Prod DB: MySQL 8.
- **Frontend** — Next.js 14 App Router, TypeScript, Tailwind, shadcn/ui, Framer Motion, Dexie.js (IndexedDB), next-intl.
- **Sync Agent** (phase 4) — .NET 8 Windows Service, WebSocket client, FirebirdSql.Data.FirebirdClient.
- **Billing** — Laravel Cashier (Stripe).
- **E-Factura** — ANAF SPV integration.

## Repo layout

```
MaXPos/
├── backend/      # Laravel 11 + Filament 4
├── frontend/     # Next.js 14 PWA
├── sync-agent/   # .NET 8 (phase 4)
└── docs/
```

## Local dev

- OS: Windows 11, Laravel Herd manages NGINX + PHP 8.4 (serves `backend/` at http://backend.test).
- Node v24, pnpm.
- Frontend: `pnpm dev` on http://localhost:3000.

## Conventions

- Code identifiers in English; user-facing text in Romanian.
- PSR-12 + Laravel Pint; ESLint + Prettier.
- Money: `decimal(15,3)` or `decimal(10,2)`, never float.
- Tests in Pest, not PHPUnit.
- Migrations must be MySQL-compatible.

See `CLAUDE.md` for full context and the current roadmap.
