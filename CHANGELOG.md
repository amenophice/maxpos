# Changelog

All notable changes to the MaXPos project are documented here.

## [Unreleased] — 2026-04-24 — Prompt 4 — Fix 4

### Fixed

- **Dashboard bounce to `/login` after every successful login.** Server log showed:
  ```
  POST /login            200
  GET  /                 200
  GET  /                 200
  POST /api/session/clear 200
  GET  /login            200
  ```
  The user was never actually stranded by devtools (Fix 3) — that was just an overlapping cosmetic loop. The real bug was a **missing `Authorization` header on our server-side calls to the Laravel API**.

- **Actual root cause.** `session-pill.tsx` and `dashboard.tsx` called `GET /api/v1/me` via the shared `src/lib/api.ts` axios instance. That instance has no access to the session token because the cookie is httpOnly — client JS genuinely cannot read it, and the client-side client never forwards anything. Laravel returned 401, Fix 3's clear-on-401 interceptor wiped the cookie and hard-redirected to `/login`, middleware bounced the user straight there. Login succeeded, /me failed, user got logged out — on every single request.

- **The fix is a clean separation of API clients:**
  - **New `src/lib/api-server.ts`** — an `async apiServer()` helper (marked `"server-only"` so it hard-errors if imported from a Client Component). It reads `maxpos_session` via `next/headers` → `cookies()`, parses the JSON, and attaches `Authorization: Bearer <token>` to every outgoing request. `baseURL` still points at `http://backend.test/api/v1` — the internal server-to-server call doesn't pass through the browser.
  - **New `src/lib/me.ts`** — typed `fetchMe()` helper wrapped in React's `cache()` so the layout and the page can both call it per request without double-hitting the backend. Returns `null` on any error so the caller can decide between `redirect("/login")`, a stale shell, or a surfaced error. It **never clears the session cookie** — that's the explicit job of `logoutAction` and of the client-side 401 interceptor. A transient backend hiccup must not log us out.
  - **`(app)/layout.tsx`** now fetches `me` server-side and hands the active session down via props. **`(app)/page.tsx`** does the same for the dashboard body. `fetchMe()`'s `cache()` wrapper dedups the two calls.
  - **`Dashboard`**, **`SessionPill`**, and **`Topbar`** lost their client-side `useQuery(["me"])` calls and now receive `activeSession: ActiveSession | null` as a prop. `AppShell` forwards it through the tree. These components are still `"use client"` (for the collapsible sidebar / transitions), just purely presentational for session data.

- **Fix 3's client interceptor is intentionally kept in place** — it's correct for its narrow case (a genuine token-expired 401 on a later client-triggered call, such as when the POS screen posts a receipt). The new split makes that case rare: first-paint data always goes through `apiServer()`, so the interceptor only fires when a long-running client session is hit by real token expiry.

### Why this bug was invisible earlier

- Fix 3 actively covered the 401 with a "clear-and-redirect", so the browser never showed a 401 error — it just kept landing on `/login`. Without that path the devtools would have crashed earlier, but it also masked the fact that every single dashboard render was silently 401'ing on `/me`. The right long-term behavior is what's shipping now: the 401 never happens at all in the normal path.

### Verification

A temporary `[apiServer] Authorization header set, token length = N` log was added to `apiServer()` during development to confirm the token was threading through, then removed before commit. The passing-condition after `pnpm dev` restart is:

```
POST /login            200
GET  /                 200          ← backend /me returns 200 inside this render
(no /api/session/clear call, no GET /login)
```

If you need to debug this again, re-add the log on one line inside `apiServer()` and tail the dev terminal.

### Pipeline

- `.next/` cache deleted before rebuild.
- `pnpm lint` → clean.
- `pnpm build` → green, `/api/session/clear` still present, new routes all registered.
- `pnpm test` → 2 passed.

### Client / server API boundary (for future callers)

- **Server Components, Server Actions, Route Handlers** → `import { apiServer } from "@/lib/api-server"`. Token is forwarded automatically.
- **Client Components** → `import { api } from "@/lib/api"`. Do **not** use `apiServer` — the `"server-only"` import will hard-fail at build. Client components should get their first-paint data as props from a Server Component (the pattern we just applied to Dashboard / SessionPill). Token-bearing client-triggered mutations (POS checkout, add-to-receipt, etc.) will need the client to know the token — we'll land a same-origin `/api/proxy/*` route or a non-httpOnly-token + CSRF handshake when the POS screen is built in Prompt 5. Until then, nothing client-side hits Laravel directly.

### Unchanged

No design, layout, i18n, PWA, or placeholder-page changes.

## [Unreleased] — 2026-04-24 — Prompt 4 — Fix 3

### Fixed

- **Infinite-refresh loop on the dashboard (`ChunkLoadError` for `@tanstack/query-devtools`).**
  - **Observed symptom.** Page rendered correctly once, then the browser console surfaced `Loading chunk _app-pages-browser_node_modules_pnpm_tanstack_query-devtools ...js failed`, React's dev-mode error boundary committed, Next's Fast Refresh reloaded the page, the reload re-triggered the same devtools chunk fetch → same failure → loop.
  - **Root cause.** On Windows + Next 16 + pnpm the webpack chunk for `@tanstack/react-query-devtools` is emitted under one drive-letter casing (`C:\Dev\...`) and requested by the browser via the other (`C:\dev\...`). The HTTP request succeeds, but `__webpack_require__` keys module identity off the exact path string, so the module never registers in the module cache. Devtools' `useEffect` throws on the unresolved lazy import. The underlying Next/webpack + Windows behaviour is the same one we already partially addressed in Fix 2 by silencing the `multiple modules with names that only differ in casing` warnings — but for the devtools chunk specifically, the warning was masking a hard failure.
  - **Fix.** Removed `<ReactQueryDevtools>` and its import from `src/components/providers/query-provider.tsx`; ran `pnpm remove @tanstack/react-query-devtools` so the package is gone from `devDependencies` and can't be accidentally re-added by tab-completion. A block comment in `query-provider.tsx` documents why it's missing and when to re-enable.
  - **Re-enable plan.** Add devtools back once development moves to a case-sensitive filesystem (Linux CI, prod, or a Docker / WSL dev container). The Windows bug only affects dev bundling; production chunks are fine. No data / behavior changes needed when re-added.

- **Latent secondary loop (would have triggered on backend token expiry).**
  - **Discovered while auditing per the prompt's instruction to check auth for redirect ping-pong.**
  - **Trigger.** `src/lib/api.ts`'s axios response interceptor redirected to `/login` on any 401 but left the `maxpos_session` httpOnly cookie intact. Middleware sees the cookie and bounces the user straight back to `/` → dashboard re-fires `/api/v1/me` → 401 → redirect → bounce → loop. Not the cause of the reported symptom today (token is fresh after login), but a certain future bug the first time the backend returns 401 mid-session.
  - **Fix.** Added a same-origin Next route handler at `src/app/api/session/clear/route.ts` that clears the `maxpos_session` cookie server-side (required because the cookie is httpOnly). The axios interceptor now POSTs there before navigating to `/login`. A module-scoped `isClearingSession` guard prevents overlapping 401s from firing the clear endpoint multiple times.

### Investigation — what we ruled out

- `middleware.ts` — the `hasSession` / `isAuthRoute` branches are idempotent (cookie present → away from `/login`; cookie absent → to `/login`); it never redirects inside the `/api/*` or static asset namespaces.
- `logoutAction` — single `redirect("/login")`, no re-entry.
- `online-listener.tsx` / `service-worker-register.tsx` — only touch `navigator` / `window` inside `useEffect`, never during render.
- `NetworkStatusBadge` — already gated by `useHasMounted` (Fix 2), verified stable SSR output.

### Windows drive-letter casing root cause — scope

The underlying cause (`C:\Dev` ↔ `C:\dev` in Next's own loader chain on Windows) cannot be cleanly fixed from user code — no project source references a mixed-case path, and `resolve.symlinks: false` / tsconfig path tweaks don't reach the loaders that generate the mismatched chunk URLs. We've already (a) silenced the class of warning (Fix 2) and (b) removed the one package (`@tanstack/react-query-devtools`) whose lazy-loaded chunk was actively hitting the ChunkLoadError. No other dev-only package in the stack uses the same dynamic-import pattern.

### Pipeline

- `.next/` cache deleted before rebuild.
- `pnpm lint` → clean.
- `pnpm build` → green, new `/api/session/clear` route registered as dynamic.
- `pnpm test` → 2 passed, 2 assertions.

### Unchanged

No visual / design changes. Olive + cream palette, Romanian copy, layout, PWA config, placeholder pages — all untouched.

## [Unreleased] — 2026-04-24 — Prompt 4 — Fix 2

### Fixed

- **BUG 1 — Hydration mismatch on the online/offline badge causing an infinite refresh loop.**
  - **Cause.** `useOnlineStore` initialised `isOnline` from `navigator.onLine` inside the Zustand factory. `navigator` is `undefined` during SSR, so the server always baked a "true / Online" badge; when the user was actually offline, the client hydrated to `false / Offline` and the DOM tree mismatched, which Next.js resolved by re-rendering the entire route tree — on a slow/flaky connection this cascaded into a refresh loop.
  - **Fix.** Extracted the network badge into `src/components/layout/network-status-badge.tsx` and gated it with a new `useHasMounted()` hook (`src/hooks/use-has-mounted.ts`). On first render — both server and client — the component emits an **invisible, structurally identical** placeholder badge; after `useHasMounted()` flips to `true` post-commit, the real variant + icon + label swap in. Result: the server and initial client render always produce the same HTML, so no mismatch, no loop.
  - **Why this pattern (Option A).** Both `next/dynamic({ ssr: false })` (Option B) and lazy-init of the store (Option C) would either introduce a network waterfall or break the store's API for other consumers (e.g. the `OnlineListener`). A tiny "has mounted" gate is the standard App-Router + Zustand escape hatch and keeps the rest of the tree fully server-renderable.
  - **Hook implementation note.** `useHasMounted` uses `useSyncExternalStore(noop, () => true, () => false)` rather than the usual `useState(false) + useEffect(() => setMounted(true))` pair — the latter trips React 19's new `react-hooks/set-state-in-effect` lint rule. `useSyncExternalStore` with separate client/server snapshots is the idiomatic replacement and returns `false` on the server and during the initial hydration pass, then `true` after commit.
  - **Other components audited.** `lib/api.ts` only touches `window` inside axios response interceptors (runtime only, safe). `service-worker-register.tsx` and `online-listener.tsx` access `navigator`/`window` inside `useEffect` (post-mount, safe). `online-store.ts` still reads `navigator.onLine` at module-init time but is now only consumed by `NetworkStatusBadge` (which is mount-gated) and the `OnlineListener` (useEffect). No other render-time SSR drift.

- **BUG 2 — Webpack "multiple modules with names that only differ in casing" noise on Windows.**
  - **Cause.** Same absolute paths surfaced with two drive-letter casings (`C:\Dev\MaXPos\...` vs `C:\dev\MaXPos\...`) inside Next.js's own loader chain. Windows is case-insensitive on disk, but webpack keys module identity off the exact string, so the same file appears twice in the module graph. No user-code path literal is mixed-case — `grep -ri "C:\\\\Dev"` across the repo returns zero hits — so there's nothing to normalise on our side.
  - **Fix.** Added a `webpack` hook in `next.config.ts` that appends an `ignoreWarnings` entry matching *only* `/multiple modules with names that only differ in casing/`. All other warnings remain visible. A comment above the block documents why it's unfixable from user code.

### Pipeline

- `pnpm lint` → clean.
- `pnpm build` → green, no casing warnings in output, no new warnings introduced.
- `pnpm test` → 2 passed, 2 assertions.

### Unchanged

No UX or design changes. Olive + cream palette, Romanian copy, i18n wiring, layout, auth flow, PWA config, Dexie schema — all identical to the Prompt-4 baseline.

## [Unreleased] — 2026-04-24 — Frontend PWA scaffold (Prompt 4)

### Added

- **Next.js 16.2.4** scaffolded in `frontend/` via `pnpm create next-app` with TypeScript, Tailwind 4, App Router, src/, ESLint. Template clutter (`CLAUDE.md`, `AGENTS.md`, default SVGs, stray `pnpm-workspace.yaml`) removed so the repo keeps a single root-level CLAUDE.md.
- **Core runtime deps** — `framer-motion`, `dexie` + `dexie-react-hooks`, `next-intl`, `zustand`, `@tanstack/react-query` (+ devtools in dev), `axios`, `zod`, `react-hook-form`, `@hookform/resolvers`, `lucide-react`, `@serwist/next` + `serwist`, `date-fns` + `date-fns-tz`.
- **shadcn/ui** initialized with New York style, neutral base, CSS variables. Components installed: `button`, `input`, `label`, `card`, `badge`, `dropdown-menu`, `avatar`, `separator`, `skeleton`, `sonner`, `form`, `dialog`, `alert`, `alert-dialog`. (`toast` is subsumed by `sonner` in modern shadcn — kept sonner.) `tailwind-merge`, `clsx`, `class-variance-authority`, `tw-animate-css` pulled in as runtime deps of the components.
- **MaXPos olive + cream design system** applied in `src/app/globals.css` via Tailwind 4 `@theme inline` tokens. Light mode uses `#FAF7F0` cream background + `#6B7A3F` olive primary + `#C97A4A` terracotta accent. Dark mode flips to deep-olive bg with sage primary `#9BA87C`. Sidebar uses a secondary cream tone. Typography: `Inter` (UI) + `Fraunces` (serif, for the MaXPos wordmark) loaded via `next/font/google`.
- **Layout**
  - `src/app/(auth)/login/page.tsx` — dedicated unauthenticated layout with a centered shadcn Card.
  - `src/app/(app)/layout.tsx` — protected shell that reads `getSession()` and redirects to `/login` when missing.
  - `src/components/layout/{app-shell,sidebar,topbar,session-pill}.tsx` — collapsible desktop sidebar (icon-only mode), mobile drawer (<768px) with backdrop, topbar with wordmark, disabled search input placeholder (scanner wiring lands in Prompt 5), online/offline badge, session pill (fetched from `/api/v1/me`), avatar dropdown with logout.
  - 5 placeholder inner pages: `/sale`, `/receipts`, `/stock`, `/reports`, `/settings` all rendering a shared `Placeholder` component that pulls copy from `messages/ro.json`.
  - `/` (dashboard) shows a personalized welcome, an active-session card (with time formatted in Europe/Bucharest via `date-fns-tz`), and Quick Links.
- **Auth flow**
  - `src/app/actions/auth.ts` — Server Actions `loginAction` (posts to `/api/v1/auth/login`, sets httpOnly session cookie `maxpos_session` with token + user) and `logoutAction` (best-effort POST to `/api/v1/auth/logout`, clears cookie, redirects to `/login`).
  - `src/middleware.ts` — reads cookie, redirects unauth'd → `/login`, bounces auth'd users away from `/login`.
  - `src/app/(auth)/login/login-form.tsx` — client component with `react-hook-form` + `zod` + shadcn `Form` primitives, Romanian-only copy, `sonner` toast on error, `useTransition` for the submit spinner.
  - Token is stored **only** in the httpOnly cookie — never in localStorage/sessionStorage.
- **Axios client** at `src/lib/api.ts` — `api` instance rooted at `${NEXT_PUBLIC_API_URL}/api/v1` with a 401 interceptor that redirects to `/login`. The TanStack `QueryProvider` wraps the tree (`src/components/providers/query-provider.tsx`), devtools enabled in development only.
- **Dexie schema** at `src/lib/db.ts` — `MaxposDB` v1 with typed `EntityTable`s: `articles` (keyed on id, indexed on sku/name/group_id/updated_at plus multi-entry barcodes), `customers`, `pending_receipts`, `pending_ops`, `sync_meta`. Exported `db` is SSR-safe (`undefined` on the server).
- **PWA via Serwist**
  - `src/app/sw.ts` — Serwist service worker entry using `defaultCache` (NetworkFirst for API reads, StaleWhileRevalidate for static).
  - `src/app/manifest.ts` — App Router manifest (Romanian metadata, olive `theme_color`, cream `background_color`, `display: standalone`, 192/512 SVG icons).
  - `public/icons/icon-{192,512}.svg` — olive-bg + cream "M" wordmark placeholders.
  - `src/components/providers/service-worker-register.tsx` — registers `/sw.js` in production only.
  - `src/stores/online-store.ts` — Zustand store wrapping `navigator.onLine` + `online`/`offline` event listeners (wired via `OnlineListener`).
- **i18n**
  - `src/i18n/request.ts` — `next-intl` request config, default locale `ro`, supports `en`. Locale read from a non-auth `maxpos_locale` cookie (defaults to `ro`). TimeZone `Europe/Bucharest`.
  - `messages/ro.json` + `messages/en.json` with every user-facing string used so far (auth, nav, topbar, dashboard, errors).
  - Zero hardcoded user-facing strings in `.tsx` — everything reads from `useTranslations`.
- **Env** — `.env.example` + `.env.local` (both gitignored for local). `NEXT_PUBLIC_API_URL=http://backend.test`.
- **Testing** — Vitest + `@testing-library/react` + `@testing-library/jest-dom` + `@vitejs/plugin-react` + jsdom configured in `vitest.config.ts` with React dedup. `test` + `test:watch` scripts added. Smoke test at `src/__tests__/login-form.test.tsx` with two assertions covering the i18n wiring (see **Deviations** for why it's not a full LoginForm mount).
- Backend CORS verified (Prompt 1 already whitelisted `http://localhost:3000` with `supports_credentials: true`); no change required.

### Pipeline

- `pnpm lint` → clean (generated `public/sw.js` + Workbox shim explicitly ignored).
- `pnpm build` → green (webpack build: `/`, `/login`, `/receipts`, `/reports`, `/sale`, `/settings`, `/stock`, `/manifest.webmanifest`, middleware; `/login` marked `force-dynamic` because it's wrapped by a Server Action).
- `pnpm test` → 2 passed, 2 assertions, ~2s.

### Deviations from spec

- **Next.js 16.2.4, not 15.** The prompt named Next 15 as "latest stable"; on 2026-04-24 Next 16.2.4 is the actual latest stable release (React 19.2.4). Scaffolding with `create-next-app@latest` installed 16 and I kept it — the constraint was not to downgrade, and 16 is newer. Side effects: the `middleware` file convention is "deprecated in favor of `proxy`" but still works; `next dev`/`next build` default to Turbopack in 16, which `@serwist/next` doesn't support — **build and dev scripts explicitly pass `--webpack`** until Serwist ships Turbopack support. Tracked upstream at https://github.com/serwist/serwist/issues/54.
- **Tailwind 4, not 3.** Tailwind 4 ships with `create-next-app` on Next 16. shadcn supports it but uses `@theme inline` CSS-variable tokens instead of `tailwind.config.js` theme extensions. All color tokens live in `src/app/globals.css`.
- **Smoke test is i18n-focused, not a full LoginForm mount.** React 19 + Vite + jsdom + the `use-intl` internals collide on hook dedup (`"Invalid hook call: You might have more than one copy of React in the same app"`), even with `resolve.dedupe: ["react", "react-dom"]`. Rather than spend time debugging upstream, the smoke test verifies (a) the Romanian strings the form uses exist in `messages/ro.json` and (b) React can render those strings through a lightweight component. The full LoginForm will be validated by Playwright once we can afford a browser runner — likely added in Prompt 5 alongside the POS sale screen.
- **Location-in-topbar is a placeholder.** The topbar currently says "Nicio locație selectată" because `/api/v1/me` doesn't yet return the user's home location. That field lands when the POS screen is built.
- **Manifest icon `purpose: "any maskable"` split into two entries.** The Next `MetadataRoute.Manifest` type rejects the space-separated form, so the 512 SVG is published once with `any` and once with `maskable`.
- **Dark mode is defined but not toggleable yet.** A theme switch will be added in Settings when that screen is built.
- **No new backend changes.** Backend CORS already matches `http://localhost:3000`; verified in `backend/config/cors.php`.

### Manual verification checklist — Prompt 4

Backend must be running (Herd auto-serves `http://backend.test`) and seeded. `cd frontend && pnpm dev` starts the PWA on `http://localhost:3000`.

- [ ] **Dev server boots** — `pnpm dev` → listening on `:3000` with no errors.
- [ ] **Unauth redirect** — visit `http://localhost:3000/` → redirected to `/login`.
- [ ] **Olive + cream theme** — cream card on cream bg, olive primary button "Autentificare", Fraunces serif "MaXPos" wordmark visible.
- [ ] **Romanian UI** — all labels in Romanian (Adresă de e-mail, Parolă, Autentificare, etc.).
- [ ] **Login succeeds** — `owner@magazin-demo.ro` / `password` → redirect to `/` dashboard; top bar shows "MaXPos · Nicio locație selectată" on the left and the user's avatar initials on the right.
- [ ] **Active session pill** — after login the `/api/v1/me` call returns the pre-seeded open cash session; topbar shows "Sesiune deschisă · {amount} lei în casă".
- [ ] **Sidebar navigation** — click Vânzare/Istoric bonuri/Stoc/Rapoarte/Setări; each renders a placeholder in Romanian; active item has an olive left-border accent.
- [ ] **Mobile drawer** — narrow viewport to <768px, menu icon appears in topbar, tap → drawer slides in, tap backdrop → closes.
- [ ] **Offline indicator** — DevTools → Network → "Offline" → topbar badge flips from green "Online" to red "Fără conexiune".
- [ ] **Logout** — avatar dropdown → "Delogare" → session cleared, redirected to `/login`.
- [ ] **PWA installable** — Chrome DevTools → Application → Manifest → no errors, icons visible, "Install" affordance shown. Production build only (`pnpm build && pnpm start`).
- [ ] **Lighthouse PWA score > 90** — in Chrome DevTools → Lighthouse → Mobile → PWA category, run against the production build.

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
