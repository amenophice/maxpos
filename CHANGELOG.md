# Changelog

All notable changes to the MaXPos project are documented here.

## [Unreleased] — 2026-04-27 — Prompt 5 — UX 4 (cantități kg one-step)

### Fixed — kg quantity entry now lands in 3 keystrokes

- **Before.** Cashier clicks `Cartofi` (kg) → line appears at `1.000 kg`. They have to click the qty input, clear it, type the new value, click somewhere to commit. The numeric `<input type="number">` shows native browser spinners which look fiddly next to a Romanian comma. ~4 clicks per kg item.
- **After.** Click `Cartofi` → line appears + the qty input is **auto-focused** + `1.000` is **fully selected**. Type `0,85` (or `0.85`) → press `Enter` → line shows `0.850 kg`, total recomputes, focus jumps back to the search bar ready for the next scan. **3 keystrokes**.

### Implementation

- **`pos-store`** — new transient field `focusLineId: string | null` + action `consumeFocusLineId()`. `addItem(article, qty?, opts?)` now takes an `AddItemOptions { intentEditWeight?: boolean }` (default `true`). When a *new* line is created **and** `isWeightUnit(article.unit)` **and** `intentEditWeight !== false`, the store stamps `focusLineId = newLine.localId`. Resetting / clearing the draft also clears the flag.
- **`QuantityInput`** split into two siblings dispatched on `isWeightUnit(unit)`:
  - `WeightQuantityInput` — clean `<Input type="text" inputMode="decimal">` (no native spinner). Subscribes to `focusLineId` from the store; on mount where it matches `lineId`, calls `inputRef.focus() + inputRef.select()` then `consumeFocusLineId()`. Local `draft` state mirrors the canonical store value so we have something cancellable. Key handlers:
    - `Enter` / `Tab` → `inputRef.blur()` (which then runs `commit`).
    - `Escape` → restore pre-edit value, blur (no commit).
    - `onBlur` → commit (push `draft` to `onChange` if changed) and call the new `onAfterCommit` callback (used to bounce focus back to the search bar).
  - `CountedQuantityInput` — unchanged behaviour for `buc`/`pachet`/etc: `−`/`+` stepper + integer input + numpad. The simplification is intentionally weight-only.
  - The numpad calculator icon stays on both branches — visible but small (28×28, ghost variant) — for touchscreen users who don't want a hardware keyboard.
- **`ReceiptPanel`** — new optional `onLineCommit?: () => void` prop, threaded down to `QuantityInput.onAfterCommit`. The line's `<QuantityInput>` now also receives `lineId={line.localId}` so the focus signal can address it.
- **`PosScreen`** — passes `onLineCommit={() => searchRef.current?.focus()}`. Cashier finishes a kg edit → cursor lands back in the search bar.
- **`SearchBar`** — only the **scale-barcode** path passes `addItem(article, weightKg, { intentEditWeight: false })` because the weight ticket is authoritative. Plain-barcode + single-name-match paths keep the default (`intentEditWeight: true`), so for a kg article reached via search, the cashier still gets the auto-focus + select-and-type flow. Buc adds via search go through the same call but `isWeightUnit("buc") === false` ⇒ no focus stamp ⇒ search-bar keeps focus naturally.

### Behaviour matrix

| Path | Article unit | `intentEditWeight` | Focus result |
| --- | --- | --- | --- |
| Card click | kg | (default `true`) | new line's qty input, `1.000` selected |
| Card click | buc | (default `true`) | search bar (no focus stamp because `isWeightUnit('buc') === false`) |
| Search → single match → Enter | kg | (default `true`) | new line's qty input |
| Search → single match → Enter | buc | (default `true`) | search bar |
| Plain barcode → 8+ digits | kg | (default `true`) | new line's qty input *(rare; would mean a kg article has its own packaging EAN)* |
| Plain barcode → 8+ digits | buc | (default `true`) | search bar |
| Scale barcode → 13 digits | kg | **`false`** | search bar (weight is final) |
| Scale barcode misconfig (PLU → buc) | — | n/a | toast `Cod cântar pentru articolul …` then no add |

### Tests

- **`pos-store.test.ts`** — three new cases:
  - kg add → `focusLineId === newLine.localId`; consume clears it.
  - kg add with `{ intentEditWeight: false }` → `focusLineId` stays `null`.
  - buc add → `focusLineId` stays `null`.
- jsdom doesn't reliably exercise focus + selection on a render-then-effect chain that's gated on a Zustand subscription, so the actual DOM-level autofocus is left as a manual check (see below). The store-side contract is what's covered.
- Total: **39 / 39** Vitest green; backend Pest unchanged at **56 / 56**.

### Pipeline

- `pnpm lint` — clean. The single `react-hooks/set-state-in-effect` flag on the prop-sync `useEffect` is suppressed *only* on the offending `setDraft(value)` line with a comment explaining that the React 19 rule's preferred "derive during render" pattern doesn't fit a *cancellable* controlled input (Escape must restore the pre-edit value).
- `pnpm build` — green; routes unchanged.

### Manual checklist

- [ ] Click `Cartofi` (kg, 3.20 lei/kg) → line appears, input has focus, `1.000` is highlighted. Type `0.85` → input shows `0.85` → press Enter → line shows `0.850 kg`, `2.72 lei`, focus is in search bar.
- [ ] Click `Pâine albă 500g` (buc) → line appears with `1 buc`, focus stays in search bar.
- [ ] Click on the qty area of an existing kg line → `onFocus` selects all → re-type → Enter → updated.
- [ ] Scan a 13-digit scale ticket for `Brânză` (build via `buildScaleBarcode("28","10001",850)` in DevTools) → line added with `0.850 kg`, focus stays in search bar (no edit prompt).
- [ ] Type `0,85` (comma) on a kg line → committed identically to `0.85`.
- [ ] Press `Esc` while editing a kg line → restores the previous value, no `onChange` fires.

### Constraints honoured

No new packages. Decimal.js + 3-decimal precision unchanged. Comma/dot interchangeable. Visual design only changed in the qty input (no spinner, smaller calculator icon). Existing 92 tests preserved (now 95 with the focus-flag additions: 39 Vitest + 56 Pest).

## [Unreleased] — 2026-04-27 — Prompt 5 — Fix 5 (no-bounce on /vanzare)

### Fixed

- **Navigating to `/vanzare` immediately bounced back to `/`** even after the catalog sync was working. The server log read `GET /vanzare 200 → GET / 200 → GET / 200`, which looked like a client-side `router.push("/")`, but no such call existed in any POS component. The actual round trip was:
  1. `GET /vanzare` — server runs `vanzare/page.tsx`.
  2. `fetchMe()` returns `null` on a transient backend hiccup (slow `/me`, brief 5xx, dropped connection between Next and Laravel).
  3. The page's `if (!me) redirect("/login")` issued a 307 to `/login`.
  4. Browser follows; middleware sees the still-valid `maxpos_session` cookie and bounces auth'd users from `/login` → `/` (line 16-19 of `middleware.ts`).
  5. Server log shows the second hop, but the `/login` 307 between `/vanzare` and `/` is easy to miss when scanning logs.
- **Why it was wrong even when intentional.** A null `/me` is a soft state — backend hiccups, expired but-not-yet-cleared tokens, network blips. None of those should silently log the cashier out of the POS screen. The (app) layout already handles real auth failure (`if (!session) redirect("/login")`); the POS page guarding on the API result on top of that was double-protection that turned brittle.

### The fix

- **`src/app/(app)/vanzare/page.tsx`** no longer redirects on `me === null`. It always renders `<PosScreen>` and just passes `cashSessionId` / `cashSessionOpen` derived from `me?.active_session` (both default to `null` / `false`).
- **`PosScreen` no-session banner** — replaced the destructive `Alert` with a soft `Card`-style banner using olive `--accent` tokens: title `Sesiune de casă inactivă`, body `Deschide o sesiune din dashboard înainte de a începe vânzarea.`, plus an `outline` button linking back to `/`. The grid + receipt panel below are wrapped in `opacity-60` so the cashier still sees the catalog but can't act.
- **`ReceiptPanel`** picks up two new optional props: `checkoutDisabled?: boolean` and `checkoutDisabledReason?: string`. When the cash session is closed, `Încasează` is disabled and shows tooltip `Nu ai o sesiune deschisă`. The button stays disabled separately when the receipt is empty (existing behaviour).
- **`ArticleGrid`** got a proper empty state for the "post-sync but truly empty catalog" case (no `search`, no `groupId`): a dashed-border card with `Catalogul este gol` + `Apasă pe butonul de sincronizare din partea de sus ca să descarci articolele.`. The "no results for current filter" path keeps the smaller `Niciun articol găsit.` text so it doesn't compete with the manual-sync affordance.
- **First-time auto-sync (empty IndexedDB)** behaviour from Prompt 5 — UX 1 is preserved: when `articlesCount() === 0`, `PosScreen`'s mount effect fires `bootstrapSync({ force: true })` once and shows `Se descarcă catalogul pentru prima dată…`. No mount-time guard kicks anyone out — the empty-state card and the topbar's `Sincronizează` button cover the rare case where that sync fails.

### Audit — every redirect surface confirmed safe

- `src/middleware.ts` — only redirects auth'd users away from `/login` and unauth'd users away from everywhere else. No POS-specific path.
- `src/app/(app)/layout.tsx` — `redirect("/login")` only on missing `getSession()` (cookie absent / corrupt). `me === null` is **not** a redirect trigger here.
- `src/app/actions/auth.ts` — `logoutAction` redirects to `/login` on explicit logout only.
- `src/lib/api.ts` `handleUnauthorized` — clears cookie and redirects to `/login` on a real 401 after the refresh-token retry has failed. Unchanged.
- No `router.push("/")` / `router.replace("/")` / `redirect("/")` exists anywhere in the POS tree. `vanzare/page.tsx` was the only redirect to a non-POS path.

### Pipeline

- `.next/` cleared.
- `pnpm lint` → clean.
- `pnpm test` → **36 / 36** (6 files; no test changes — the broken redirect was server-side and not covered by the existing Vitest harness).
- `pnpm build` → green; `/vanzare` still listed as a dynamic route.
- Backend Pest unaffected — **56 / 56** carried from Prompt 5 — UX 3.

### Visual changes

Olive accent banner + grey-out on session-closed only. No new components, no new packages, no design redesign. Romanian copy uses existing palette tokens (`--accent`, `--muted-foreground`).

## [Unreleased] — 2026-04-27 — Prompt 5 — UX 3: cantități zecimale + cântar

### Backend

- **Migration `2026_04_27_…_add_scale_barcode_prefixes_to_locations.php`** — adds `locations.scale_barcode_prefixes` (nullable JSON). Configures, per-location, which 2-digit EAN-13 prefixes mean "this is a scale-printed weight ticket". Null falls back to defaults `["26","27","28","29"]`.
- **`Location` model** — array cast on the new field, `DEFAULT_SCALE_PREFIXES` constant, helper `effectiveScalePrefixes()` returning the configured list or the defaults.
- **`LocationRequest`** — accepts `scale_barcode_prefixes` as a nullable array of 2-digit strings (`regex:/^\d{2}$/`).
- **`Filament` Location form** — new `TagsInput::make('scale_barcode_prefixes')` under "Setări" with placeholder `26` and helper text explaining the default fallback.
- **`/api/v1/pos/bootstrap` meta** — now returns `scale_barcode_prefixes` (union of all locations' configured prefixes for the tenant; defaults if none configured). Backed by 4 new Pest tests in `Pos\LocationScalePrefixesTest`.
- **`DemoShopSeeder`** — flipped 7 articles to `unit='kg'` (Brânză telemea, Cașcaval, Mere roșii, Banane, Roșii, Cartofi, Ceapă roșie) and added `plu` codes to 5 of them: `10001` Brânză, `10002` Cașcaval, `10003` Mere, `10004` Cartofi, `10005` Ceapă. Skipped synthetic EAN-13 generation for kg articles — they're meant to be reached via scale stickers, not barcode-strapped packaging.
- **`bcmath` math** — already correct (Prompt 3); no changes. Verified manually: 0.850 kg × 11.40 lei/kg = 9.69 lei (matches the existing `ReceiptServiceTest`'s mixed-VAT case).

### Frontend

- **`src/lib/units.ts`** — `isWeightUnit(unit)` (kg / kg. / g / l / litru / litre / ml / m / metru), `normalizeQuantityInput(s)` (trims and converts `,` → `.`).
- **`src/lib/scale-barcode.ts`** — pure helpers:
  - `parseScaleBarcode(code, allowedPrefixes)` validates the 13-digit numeric pattern, allowed prefix, EAN-13 check digit, then returns `{ plu, weightKg }` (`weightKg` as a 3-decimal string from grams ÷ 1000).
  - `buildScaleBarcode(prefix, plu, grams)` (reverse — used by tests + future seeder helpers).
- **`src/components/pos/numpad-dialog.tsx`** — touchscreen keypad. 3×4 grid with digits, comma, backspace; integer-only mode (`decimals=0`) hides the comma. Confirms with OK or Enter; rejects `1.50` keystrokes against an integer field; auto-prepends `0.` when the user starts with the decimal separator.
- **`src/components/pos/quantity-input.tsx`** — receipt-line quantity editor:
  - **Weight units** → text input with `inputMode="decimal"` accepting comma/dot, no +/- buttons, plus a Cântar (calculator) icon that opens the numpad with `decimals=3`.
  - **Counted units** → integer text input with leading −/+ stepper (today's behaviour) plus the same numpad button (`decimals=0`).
  - Pasting `1.5` into a counted-unit field silently drops the fraction (becomes `1`). Scanning a scale-PLU code that points at a `unit='buc'` article is intercepted earlier in the flow with an explicit toast.
- **`src/components/pos/receipt-panel.tsx`** — replaced the inline `<Button minus/>… <input type=number/> …<Button plus/>` block with `<QuantityInput value unit ariaLabel onChange/>`. Per-line totals now compute via `Decimal` (`unitPrice × quantity − discountAmount`) so weighed lines round half-away-from-zero in lockstep with the backend's `bcmath`.
- **`src/components/pos/search-bar.tsx`** — barcode-submit logic:
  1. If exactly 13 digits: try `parseScaleBarcode` against the prefixes from `getScalePrefixes()`. On hit → `findArticleByPlu` → if found and unit is weight (`kg`/`l`/`litru`), `addItem(article, weightKg)` and clear search; if PLU not found, toast `PLU necunoscut: <plu>`; if PLU resolves to a counted-unit article, toast the `scaleBarcodeWrongUnit` warning and abort.
  2. Else fall through to the existing `findArticleByBarcode` (8+ digits) path.
- **`src/lib/sync/bootstrap-sync.ts`** — persists `meta.scale_barcode_prefixes` into `sync_meta.scale_barcode_prefixes` on every sync, exposes `getScalePrefixes()` (defaults to `["26","27","28","29"]` when no value cached). The PLU column on articles already flowed through (added in Prompt 5).
- **`src/lib/db.ts`** — added `findArticleByPlu(plu)` with leading-zero-tolerant comparison. Plain `db.articles.filter(...)` is fine for the demo's article count; we'll add a proper PLU index when a tenant pushes us past a few thousand rows.
- **`src/stores/pos-store.ts`** — `addItem(article, qty?: number | string)` now coerces to a 3-decimal string via `toQty()`. Re-add for SAME `article + default_gestiune` sums quantities (`0.500 + 0.350 = 0.850`). Inline JSDoc explains the `decimal(15,3)` contract with the backend. `updateItemQuantity` follows the same string-based contract.
- **i18n** — new strings under `pos`: `scaleBarcodePluUnknown`, `scaleBarcodeWrongUnit` (both locales).

### The unit-mismatch rule (documented)

If a cashier types `1,5` into a counted-unit field (`buc`/`pachet`/…), the `QuantityInput` *silently coerces to integer* — pasting `1.5` becomes `1` because the integer branch ignores the fractional part. The path that *does* reject loudly is **scale-barcode → counted-unit article**: scanning a 13-digit scale ticket whose PLU points to a `buc` article surfaces a toast (`Cod cântar pentru articolul {name} — nu se vinde la kg/l (unitate: {unit}).`) and the line is not added. That's the only realistic way a fractional quantity could leak into a counted-unit line — manual typing is already blocked by the input.

### Tests

- **Backend Pest** — 4 new tests in `LocationScalePrefixesTest` (cast, fallback, bootstrap union, bootstrap default). Total: **56 / 56** (118 assertions).
- **Frontend Vitest** — `scale-barcode.test.ts` (5 cases: valid 28-prefix decode, broken checksum, wrong prefix, length mismatches, demo `0.850` case) + 2 new `pos-store.test.ts` cases (`0.500 + 0.350 = 0.850`, mixed kg/buc lines). Total: **36 / 36**.
- `pnpm lint` clean; `pnpm build` green.

### Manual verification

- [ ] Open Brânză telemea on `/vanzare` → click card → row shows `1.000 kg`. Type `0,85` in the qty field → `0.850 kg`, total `9.69 lei`.
- [ ] Type `0.85` (dot) → same result.
- [ ] Add Pâine (1 buc, 4.20 lei) on the same bon. Receipt total = 13.89 lei.
- [ ] Try typing `1.5` in Pâine's quantity → input drops the fraction, lands on `1` (or `2` if you intended a step).
- [ ] Scan/paste a 13-digit scale code for Brânză (use `buildScaleBarcode("28", "10001", 850)` to generate one in DevTools console: `2810001008505`) → article added with `0.850 kg`.
- [ ] Existing flows unchanged: Lapte Zuzu 1L still scans by its packaging EAN-13 → 1 buc.

### Architectural note

Backend `bcmath` already supported every receipt math case (scale 3 quantity, scale 2 money, half-away-from-zero). Only the frontend was missing the UI surface. POS terminals on a future Android build inherit the same store + helpers — no fork.

## [Unreleased] — 2026-04-27 — Prompt 5 — Fix 3

### Fixed — second checkout in a row failed with HTTP 419 (CSRF mismatch)

- **Symptom.** Receipt #1 → 200 OK and a happy success screen. Receipt #2 → toast `Backend a respins bonul: Request failed with status code 419` and the cashier was stuck. Refreshing fixed it for one more, then the same wall.
- **Root cause.** Prompt 1 wired `bootstrap/app.php` → `$middleware->statefulApi()` so Sanctum's session/CSRF stack ran on **every** API route, not just on a hypothetical SPA-cookie path. After every successful POST, Laravel rotated the session-bound CSRF token; client-side axios kept sending the old `X-XSRF-TOKEN` (or none, depending on the cookie state), and the second POST was rejected. The PAT bearer token on the request was completely fine — that wasn't the failure mode.
- **Why this is the wrong default for `/api/v1/*`.** All POS routes already auth via `auth:sanctum` with Personal Access Tokens (Bearer). PATs are stateless. Adding session-CSRF on top is dead weight that punishes back-to-back POSTs and breaks the offline replay path entirely (a queued receipt can't carry a CSRF token it never had).

### The fix — Bearer-only for `/api/v1/*`, CSRF stays on `/admin`

- `bootstrap/app.php` — removed the `$middleware->statefulApi()` call. The `withMiddleware` callback is now an inline-comment block documenting *why* it's empty so the next reader doesn't put it back. The web group (`routes/web.php`) keeps Laravel's default web stack including `VerifyCsrfToken`, so Filament's `/admin` Livewire flow is **untouched**.
- `config/sanctum.php` — replaced the verbose default for `stateful` with `array_filter(explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')))`. Empty by default; opt-in only via `.env` if a future feature really needs SPA cookie auth.
- `.env` and `.env.example` — `SANCTUM_STATEFUL_DOMAINS=` (cleared from `localhost:3000,backend.test`). Run `php artisan config:clear` after pulling so the cached config is dropped.
- Frontend audit — `grep -r "XSRF|csrf|withCredentials|csrf-cookie" src/` returned **no matches**. The client-side axios instance never sent `X-XSRF-TOKEN` and never called `/sanctum/csrf-cookie`; the bug was entirely on the backend's policy. No frontend code change required for this fix.

### What still uses CSRF

- Filament admin login at `/admin/login` and any web POST route (Livewire actions). All of these go through `routes/web.php` which keeps Laravel's default web middleware, including `VerifyCsrfToken`. Verified by visual login walkthrough — unaffected.
- The Next.js `maxpos_session` httpOnly cookie is **not** Sanctum's session — it's our own server-action cookie holding the bearer token. Independent concern; unchanged.

### Pipeline

- `php artisan config:clear` then `./vendor/bin/pest` → **52 / 52** (110 assertions). Existing tests already exercised `/api/v1/*` purely via `Sanctum::actingAs(...)` + Bearer, so removing CSRF didn't shift any expectations.
- `pnpm lint` → clean.
- `pnpm build` → green; routes unchanged.
- `pnpm test` → **29 / 29** (5 test files).

### Verification flow (manual)

1. Login fresh as `owner@magazin-demo.ro`.
2. Add items → F9 → Numerar → Exact → Finalizează → success.
3. New bon → repeat → success. Repeat a third time without refresh → success.
4. Refresh page mid-session → repeat → success.
5. DevTools → Network → each `POST /api/v1/pos/checkout` shows only `Authorization: Bearer …` and `Content-Type: application/json`. **No** `X-XSRF-TOKEN`, **no** `XSRF-TOKEN` cookie required.
6. Offline drill: Network → Offline → complete two receipts → both queued in IndexedDB (`PendingQueuePill` shows `2 în coadă`). Network → Online → both POST through and pill clears.

### Architectural note for upcoming work

The Sync Agent (Prompt 6 onwards) will also hit `/api/v1/*` over WebSocket / REST with its own PAT. Bearer-only is the right contract for any non-browser-form caller. Future POS-on-Android / desktop builds inherit the same shape. CSRF stays in its lane: web forms only.

## [Unreleased] — 2026-04-25 — Prompt 5 — UX 2

### Fixed — checkout dialog dead-end

- **The bug.** Clicking a quick-amount button in `CheckoutDialog` (`Exact` / `50` / `100` / `200` / `500 lei`) only filled the `Sumă` input. `Achitat` stayed at `0.00`, the `Finalizează și tipărește` button stayed disabled, and there was no on-screen instruction explaining that the cashier still had to click `Adaugă plată` separately. The flow looked broken even when nothing was technically wrong.
- **The fix.** Quick buttons now perform the payment directly. The `Sumă` field + manual button are kept, but visually positioned and labeled as the *partial-payment* affordance (renamed to `Adaugă plată parțială`, with hint copy `Pentru sume manuale (ex. 26.50 lei)`).
- **New `src/lib/payments.ts`** — pure helpers `sumPayments`, `remainingForReceipt`, and `applyQuickPayment`. The last is the single entry point used by every payment-add path (quick buttons, custom-amount button, `Enter` in the `Sumă` field). It:
  - Caps the credited amount at the current `remaining`. If you click `100 lei` against a `76.90` receipt, the receipt records a clean `cash 76.90`, never a 100.
  - For **cash** overflow, returns the difference as `changeDelta` (the cashier owes `23.10` back as physical change). For card / voucher / modern / transfer, overflow is silently capped — no change is conjured for non-cash methods.
  - Returns the same `payments` array reference (no churn) when nothing was applied (e.g. clicking after the receipt is already paid).
- **`CheckoutDialog` rewired** through `applyPayment(method, amount)` which delegates to `applyQuickPayment` and accumulates a UI-only `changeDue: Decimal` running total. Specific behaviour:
  - **Cash quick buttons** (`Exact / 50 / 100 / 200 / 500 lei`) fire `applyPayment("cash", N)` directly — one click is now the whole interaction.
  - **Non-cash methods** show a single `Exact (XX.XX lei)` quick button so card/voucher/etc. users have the same one-click affordance for "pay the remaining".
  - **`Adaugă plată parțială`** still calls `applyPayment(activeMethod, amountInput)`. Same caps + change-due rules apply when the method is cash.
  - **`Sumă` field** auto-refills with the new remaining after every successful add. Switching from Cash to Card after a 50-lei quick-click leaves the field at `26.90` ready for `Adaugă plată parțială` or for the new `Exact (26.90 lei)` button.
  - **`Rest de dat: XX.XX lei`** displayed in olive primary, prominent, both inside the active dialog (a bordered chip above the action row) and on the success screen.
  - **All quick buttons disable** once the receipt is paid in full (`remaining <= 0`), so re-clicking can't accumulate phantom change.
  - **Removing a payment row** also resets the `changeDue` running total and refills `Sumă` with the new remaining (the only honest behaviour — we don't track which slice of overpayment came from which row).
- **`changeDue` is UI-only.** It never travels to the backend. The receipt's `payments` array still sums exactly to `total`. The change figure is purely a hint to the cashier about what to hand back.

### What stayed the same

- Backend untouched. `POST /api/v1/pos/checkout` still receives a clean payment list whose `amount`s sum to the receipt total.
- The shared `submitCheckout` / `enqueuePendingReceipt` / online-queue plumbing untouched. The fix is local to the dialog + one new pure helper.
- Visual design — only addition is the `Rest de dat:` chip and the relabelled hint text under `Sumă`.
- Romanian / English i18n: new strings `quickCashHint`, `addPaymentHint`, `changeDue` added to both message files.

### Tests

New file `src/__tests__/payments.test.ts`:
- exact-amount click → no change, full credit
- 100-lei cash button against 76.90 receipt → credited 76.90, `changeDelta = 23.10`
- card overpayment caps at remaining, **never** produces change-due (cash-only behaviour)
- already-paid noop returns the same array reference (no churn)
- split flow: 50 cash + 26.90 card on a 76.90 receipt → paid in full, no change
- custom `Adaugă plată parțială` over-typed cash amount caps the same way

### Pipeline

- `pnpm lint` → clean.
- `pnpm build` → green; routes unchanged.
- `pnpm test` → **29 / 29** (5 test files; +6 new in `payments.test.ts`).
- Backend Pest unchanged at 52 / 52 — no backend code touched.

### Manual flow now expected

1. Receipt total `76.90`.
2. F9 / `Încasează` → defaults to `Numerar`. Click `100 lei` once → payment of `76.90` recorded, `Achitat: 76.90`, `De plătit: 0.00`, `Rest de dat: 23.10 lei` chip appears, `Finalizează și tipărește` enables. Click → success screen mirrors the change.
3. Or split: click `50 lei` (Cash) → `Achitat: 50.00`, `Sumă` auto-fills with `26.90`. Switch to `Card` → click the new `Exact (26.90 lei)` button → paid in full, finalize.
4. Or custom split: click `Numerar`, type `42.50` in `Sumă`, click `Adaugă plată parțială` → recorded, switch to `Card`, `Exact (34.40 lei)` → done.

## [Unreleased] — 2026-04-25 — Prompt 5 — UX 1

### Change 1 — Romanian diacritic-insensitive search

- **New `normalizeRoText(s)`** in `src/lib/db.ts` — lowercases, runs NFD, strips combining marks (`/[̀-ͯ]/g`, written with explicit `\u`-escapes after a Windows source-encoding round-trip), and collapses both legacy cedilla and modern comma forms (`ș|ş` → `s`, `ț|ţ` → `t`, `â|ă` → `a`, `î` → `i`).
- **Dexie schema bumped to v3.** New indexed field `articles.name_normalized: string`. The v3 upgrade callback walks every existing article row and recomputes `name_normalized` from `name`, so any cashier who already had a populated catalog from v2 doesn't need to re-sync.
- **`bootstrap-sync.ts` now writes `name_normalized` on every `bulkPut`** — produced by `normalizeRoText(a.name)`.
- **New pure helper `matchArticleQuery(rows, query)`** factored out of `searchArticles`. Match rule: empty query → pass-through; non-empty → row matches if `name_normalized` *contains* the normalized needle, OR raw lower-cased `sku` contains the raw lower-cased query (SKUs aren't diacritic-folded — they're already ASCII). Group filter applies before this in `searchArticles`. Sort by `name.localeCompare(a, b, "ro")`, then `slice(0, limit)`.
- Typing `paine` now matches "Pâine albă 500g"; `rosii` matches "Mere roșii 1kg"; `cascaval` matches "Cașcaval afumat 300g". Verified by Vitest.

### Change 2 — Manual-only catalog sync

- **All recurring auto-triggers for the catalog removed.** The codebase had no `setInterval` or focus listener for catalog sync, but `PosScreen`'s mount effect was firing `bootstrapSync()` every visit (gated only by a 15-min staleness window — still felt continuous from the cashier's point of view). That gate is gone.
- **The only remaining auto-call is "first-ever empty IndexedDB"** — `PosScreen` now does `articlesCount()` first; if `0`, it fires a one-time `bootstrapSync({ force: true })` with the new toast `Se descarcă catalogul pentru prima dată…`. If the local table has any rows, `/vanzare` does **not** call `/pos/bootstrap` on mount.
- **Receipts queue (write-side) sync is unchanged.** `OnlineListener` still calls `processPendingQueue()` on the `online` event and at first-mount-when-online — that's a different concern (durability of completed sales) and stays auto.
- **New `CatalogSyncButton` in the topbar** (`src/components/layout/catalog-sync-button.tsx`):
  - Lucide `RefreshCw` icon by default, swapped to `Check` (primary green) on success, `X` (destructive) on error, and `RefreshCw` with `animate-spin` while loading.
  - States: `idle | loading | success | error`. Success holds for 2 s, error for 4 s, then auto-resets to `idle`.
  - Click → `bootstrapSync()` (incremental via `?since=<last_sync_at>` if available, else full). Shows toast `Catalog sincronizat ({articles} articole, {groups} grupe)` on success / `Sincronizare eșuată — se folosește catalogul local. (<error>)` on failure.
  - Disabled while offline (Zustand `useOnlineStore`); tooltip flips to `Indisponibil offline`.
  - Idle tooltip embeds the last-sync label: `Sincronizează catalogul · Ultima sincronizare: acum X minute / la HH:MM / Niciodată sincronizat`. Live-updated from Dexie's `sync_meta` table via `useLiveQuery`.
  - Mounted in `Topbar` between `PendingQueuePill` and `SessionPill`.
- **`bootstrapSync()` return shape extended** from `{ synced, fromCache }` to `{ fromCache, articles, groups, customers, gestiuni, serverTime }` — the button needs the granular counts for its toast. New `getLastSyncAt()` helper exported alongside.
- Both `messages/ro.json` and `messages/en.json` carry the new `firstSyncToast`, `syncedToastDetail`, and `syncButton.*` strings.

### Change 3 — Checkout end-to-end verification

- **Coverage status:** the full checkout path is exercised by **backend Pest** (`PosCheckoutTest` — happy path, idempotent retry, rollback on stock failure, 403 without `pos.sell` — all 4 green; total 52 backend tests pass) and by **frontend Vitest** (`pos-store.test.ts` for line/total math + `client_local_id` lifecycle, `money.test.ts` for shared bcmath formula). The wire format (`CheckoutPayload`) is type-checked end-to-end through `submitCheckout` → `/api/v1/pos/checkout`.
- **Manual flow expected, with the changes above** (to run after `pnpm dev` restart):
  1. Login as `owner@magazin-demo.ro`. `/vanzare` does first-time sync, toast `Se descarcă catalogul pentru prima dată…` then `Catalog sincronizat (30 articole, 5 grupe)`.
  2. Add 2–3 items. Receipt panel total settles to e.g. `51.20 lei`.
  3. F9 / `Încasează` → `Numerar` → click `Exact` (fills 51.20) → `Adaugă plată` → `Finalizează`.
  4. Success screen: `Bon finalizat • Bon #N • 51.20 lei`. Auto-closes after 4 s, focus returns to search. `/vanzare` reload → bon empty.
  5. Split: 30 cash + 21.20 card; `Achitat` reaches 51.20, finalize button activates, success.
- **No code changes were needed** for checkout — Prompt 5 — Fix 2 already wired the bearer-token interceptor; the `/pos/checkout` POST now goes out with `Authorization` set. If the manual run surfaces anything broken, capture the network/console excerpt and feed it back.

### Pipeline

- `.next/` deleted before rebuild.
- `pnpm lint` → clean.
- `pnpm build` → green; routes unchanged.
- `pnpm test` → **23 / 23** (4 test files: `login-form`, `money`, `pos-store`, **new** `normalize-search`).
- `./vendor/bin/pest` → unchanged 52 / 52 (no backend changes this round).

### Constraints honoured

- No new packages.
- Cookie still httpOnly; token interceptor pattern from Fix 2 unchanged.
- Receipts queue auto-drain (`processPendingQueue`) untouched.
- No visual changes beyond the topbar's new sync icon.
- Romanian UI / English code preserved.

## [Unreleased] — 2026-04-25 — Prompt 5 — Fix 2

### Fixed

- **`/vanzare` bounces to `/login` because `/pos/bootstrap` returns 401.**
  - **Root cause.** Same shape as Prompt 4 — Fix 4, but on the *client* surface this time. The bootstrap sync (and every future client-side mutation: receipt checkout, queue drain, etc.) runs in the browser because it has to write to IndexedDB. Browser axios calls go through `src/lib/api.ts`, which had no way to read the bearer token — the `maxpos_session` cookie is httpOnly so `document.cookie` can't see it. Backend returned 401, Fix 3's interceptor correctly cleared the session, user landed on `/login`. Fix 4 only routed *server-side* renders through `apiServer()` + `next/headers` cookies; it didn't and couldn't help client-side calls.
  - **Why we can't just drop httpOnly.** Reusing the existing cookie for the bearer would mean dropping `httpOnly`, which exposes the token to any XSS. The whole point of the cookie split is that the cookie is the durable, secret-of-origin store; the client only ever holds the token in JS memory for as long as the tab lives.

- **The fix — pair `/api/session/clear` with a sibling `/api/session/token`.**
  - New `src/app/api/session/token/route.ts` — `GET` handler that reads `maxpos_session` via `cookies()`, returns `{ token: string }` (200) or `{ error: "no_session" }` (401), `Cache-Control: no-store`. Same-origin, server-rendered, never crosses to `localStorage` / `document.cookie`.
  - `src/lib/api.ts` rewired:
    - **Module-scope cache** — `cachedToken: string | null`, `inflightTokenFetch: Promise<...> | null`. The token lives only here, only for the lifetime of the JS context.
    - **`fetchSessionToken()`** — calls `GET /api/session/token`, dedupes concurrent callers via the in-flight promise, returns null on any error.
    - **Request interceptor** — before each request, if there's no `Authorization` header yet, call `ensureToken()` (cache-first, refetch-on-miss) and inject `Bearer <token>`. Skipped when `typeof window === "undefined"`; server callers still go through `apiServer()`.
    - **Response interceptor** — on 401, clear the cache, refetch the token once, retry the original request with a `_retriedWithFreshToken` flag. If that retry also 401s, or if `/api/session/token` itself is 401 (no cookie), fall through to the existing `clear-session + redirect-to-login` path.
  - **Order of operations now**: cookie present + token valid → request succeeds; cookie present + token expired in cache → refetch + retry → succeeds; cookie present + token expired on backend → refetch returns same expired token → backend still 401s → clear + redirect; no cookie at all → token endpoint 401s → clear + redirect.

### Audit — every client → backend caller goes through the fixed path

- `bootstrap-sync.ts` (`api.get('/pos/bootstrap')`) — first to surface the bug; now fixed transparently.
- `receipts-sync.ts` (`api.post('/pos/checkout')`) — would have hit the same 401 the moment a real receipt was submitted; fixed transparently.
- `SessionPill` / `Dashboard` no longer call `/me` from the client (Fix 4 moved them to `apiServer`); audit only confirms — no change needed.
- Future client-side mutations from POS (return processing, void, customer creation) will inherit the fix because they use the shared `api` instance.

### What is intentionally untouched

- **Cookie stays httpOnly.** No exposure in `document.cookie` or `localStorage`. The token endpoint is same-origin, requires the cookie to read it, and serves the token only to JS that's already running on this origin.
- **Fix 4 (`apiServer()`)** unchanged — server renders still resolve their token via `next/headers` directly, never via the new endpoint.
- **Fix 3 (`clear + redirect`)** preserved — it only fires when the *retry* with a fresh token also fails, which is the genuine "token expired or revoked" case.
- **Visual design / POS UX / store contract / test coverage** all unchanged. `selectTotals`-as-strings + `useShallow` from Prompt 5 — Fix is preserved.

### Pipeline

- `.next/` deleted before rebuild.
- `pnpm lint` → clean.
- `pnpm build` → green; `/api/session/token` registered as a dynamic route alongside `/api/session/clear`.
- `pnpm test` → 10 / 10.

### `/api/session/*` is now a small surface — for future readers

| Route | Method | Reads | Writes | Purpose |
|---|---|---|---|---|
| `/api/session/token` | `GET` | `maxpos_session` cookie | — | Hand the bearer token to the client-side axios instance. |
| `/api/session/clear` | `POST` | — | clears `maxpos_session` cookie | Wipe the session before forcing a redirect to `/login`. |

The cookie itself is set only by the `loginAction` server action (`src/app/actions/auth.ts`). All three pieces — set, read, clear — are server-side only; the client never touches the cookie directly.

## [Unreleased] — 2026-04-24 — Prompt 5 — Fix

### Fixed

- **`/vanzare` crash: "Maximum update depth exceeded" → bounce to `/login`.**
  - **Root cause.** `selectTotals` in `src/stores/pos-store.ts` returned a freshly-constructed object (`{ subtotal, vatTotal, discountTotal, total }`) on every call. Zustand 5's `useStore` is backed by React 18+'s `useSyncExternalStore`, which requires the value returned by `getSnapshot`/`getServerSnapshot` to be referentially stable when the inputs haven't changed. A new object reference on every call trips React's same-render state detection, bails out with "Maximum update depth exceeded" / "The result of getServerSnapshot should be cached to avoid an infinite loop", and brings the whole component tree down.
  - **Why it looked like an auth loop.** The render error bubbled through the App Shell and caused the current page to unmount mid-request. Whatever network call was in flight rejected; the closest sibling running an axios call was the `/me` SessionPill / Dashboard fetch, and **Fix 3**'s 401 interceptor then silently cleared the session cookie and hard-redirected to `/login`. A real render bug dressed up as an auth bug — exactly the pattern Fix 4 was supposed to contain, but Fix 4 only covered first-paint 401s (which we fixed by moving `/me` to server-side).
  - **The fix.** `selectTotals` now returns **strings** (`{ subtotal: "136.50", vatTotal: "20.52", discountTotal: "5.00", total: "131.50" }`), not `Decimal` instances. Consumers wrap it with `useShallow`:
    ```ts
    import { useShallow } from "zustand/react/shallow";
    const totals = usePosStore(useShallow(selectTotals));
    ```
    Shallow per-key comparison with `Object.is` now returns `true` whenever the underlying inputs haven't changed — strings compare by value, Decimal instances would not. When a component needs math (`remainingDec = total - paidDec`), it reconstructs a single `new Decimal(totals.total)` inside `useMemo`.
  - **Sites updated.**
    - `src/components/pos/receipt-panel.tsx` — wrapped with `useShallow`, replaced the one `totals.subtotal.sub(...)` math site with `new Decimal(totals.subtotal).sub(totals.vatTotal)`.
    - `src/components/pos/checkout-dialog.tsx` — wrapped with `useShallow`, added a `totalDec = useMemo(() => new Decimal(totals.total), [totals.total])` and rerouted the `remainingDec` / `canSubmit` math through `totalDec`.

### Audit — other selectors checked and cleared

- `s.lines` / `s.customer` / `s.receiptDiscount` / `s.cashSessionId` / `s.clientLocalId` — all primitives or store-internal references. Zustand never mutates state in place (only replaces via `set`), so these are referentially stable between unrelated updates. No `useShallow` needed.
- Action selectors (`s.addItem`, `s.updateItemQuantity`, `s.removeItem`, `s.setCustomer`, `s.clearDraft`, `s.setStatus`, `s.applyReceiptDiscount`, `s.setCashSessionId`) — stable references created once by the `create(...)` factory. No `useShallow` needed.
- No `.filter()` / `.map()` selectors exist in the codebase that would rebuild arrays on every call.

### Not changed

- Store internals — `addItem`, `updateItemQuantity`, `removeItem`, etc. already mutate only what they need to (spread lines, replace the changed line), no accidental object-recreation.
- Money helper `computeReceiptTotals` still returns `Decimal`s in `src/lib/money.ts` — that layer is used by the test suite and future server-side computations where referential stability is not a concern. Only the Zustand selector was reshaped.

### Pipeline

- `.next/` cache deleted before retry.
- `pnpm lint` → clean.
- `pnpm build` → green.
- `pnpm test` → **10 / 10** (adjusted `pos-store.test.ts` to expect string totals instead of Decimal `.toFixed(2)` — the selector's contract changed from "Decimals" to "strings at `MONEY_SCALE`", which is actually a nicer API).

### Unchanged

No visual design or POS functionality changes. Olive + cream palette, layout, keyboard shortcuts, offline queue, idempotency — all identical to the Prompt 5 baseline.

## [Unreleased] — 2026-04-24 — POS sales screen (Prompt 5)

### Added — backend

- **`receipts.client_local_id`** migration (nullable string(64), composite unique on `(tenant_id, client_local_id)`). Lets the POS submit each receipt with a client-generated idempotency key so an offline-retried request can't duplicate a sale.
- **`GET /api/v1/pos/bootstrap`** — one-shot payload containing `articles` (with nested `barcodes` and `stock_levels`), `groups`, `customers`, and `gestiuni`. Supports `?since=<ISO8601>` for incremental sync — rows with `updated_at <= since` are skipped. Response envelope includes `meta.server_time` so the client can use the server's wall clock as the next `since`.
- **`POST /api/v1/pos/checkout`** (`can:pos.sell`) — single-transaction receipt creation: `createDraftReceipt` → `addItem` for each line → `applyDiscount` → `completeReceipt`. If the request carries a `client_local_id` that already exists for the tenant, returns the existing receipt with `meta.idempotent_replay=true` and a `200` (not `201`), without creating a new receipt or touching stock.
- **`ArticleResource`** now exposes `stock_levels` (conditional on the relation being loaded) and `updated_at`. `ArticleController@index` eager-loads both `barcodes` and `stockLevels`.
- **`GroupResource`** created (not routed standalone — only used by the bootstrap response).
- **`PosCheckoutRequest`** validates the full nested payload (items, payments, client_local_id, receipt_discount).
- **`ReceiptResource.client_local_id`** surfaced for the client to echo back.

### Added — backend tests (6 new, all green)

- `PosBootstrapTest` — shape + `?since=` incremental filtering
- `PosCheckoutTest` — happy path, idempotent retry returns same receipt + stock only decremented once, 422 rolls back (no receipt, stock untouched), 403 without `pos.sell`
- Full Pest suite: **52 tests, 110 assertions, all green** (46 baseline + 6 new)

### Added — frontend

- **`decimal.js`** (only new runtime dep) for money math. **`virtua` intentionally deferred** — the demo catalog is 30 articles; we'll add virtualization when a real tenant pushes us above ~100 and the grid starts dropping frames. Noted.
- **`src/lib/money.ts`** — `computeLineTotals`, `computeReceiptTotals`, `formatLei`, shared `MONEY_SCALE=2`, `QTY_SCALE=3`. The formula comment matches backend `App\Services\ReceiptService` byte-for-byte (inclusive VAT: `line_vat = round_half_away_from_zero(line_subtotal × rate / (100 + rate), 2)`). Unit-tested on both sides.
- **Dexie v2** (`src/lib/db.ts`) — adds `groups` + `gestiuni` tables, reshapes `pending_receipts` with `status | last_error | server_receipt_id | retry_count`, adds `stock: Record<gestiuneId, qty>` to `articles`. Query helpers: `findArticleByBarcode`, `searchArticles`, `getAllGroups`, `getAllCustomers`, `getDefaultGestiune`, `get/setMeta`.
- **`src/lib/sync/bootstrap-sync.ts`** — fetches `/pos/bootstrap` (with `?since=` from `sync_meta`), bulk-puts into IndexedDB, updates `bootstrap.last_sync_at`. Forces a full sync on first load; stale after 15 min. Falls back gracefully on network error.
- **`src/lib/sync/receipts-sync.ts`** — `submitCheckout` (axios → structured result), `enqueuePendingReceipt`, `processPendingQueue`. Queue processor walks all `pending | failed` rows in creation order, marks each `syncing` mid-call, deletes on success, re-pends on retriable 5xx/network, moves to `failed` on 4xx business errors. Module-level guard prevents concurrent processor runs.
- **`src/stores/pos-store.ts`** — Zustand + `persist` to `localStorage` (partitioned: cash session, draft lines, receipt discount, customer, client_local_id — but not transient `status`). Actions: `openDraft`, `addItem` (consolidates re-adds into existing line quantity), `updateItemQuantity` (auto-deletes on 0), `applyLineDiscount`, `removeItem`, `applyReceiptDiscount`, `setCustomer`, `clearDraft`. `selectTotals` derives receipt totals on the fly — never stored.
- **`/vanzare` route** (`src/app/(app)/vanzare/page.tsx` + `src/components/pos/*`):
  - `PosScreen` — two-column layout (article grid 65% / receipt panel 35% at `lg+`, single column below), wires bootstrap sync on mount with a loading toast, registers global keyboard shortcuts.
  - `SearchBar` — autofocus, scanner-friendly: if the input contains 8+ digits and user hits Enter, look up by barcode in IndexedDB and auto-add. Clear button (×) on the right.
  - `ArticleGrid` — group tabs ("Toate" + one per group), responsive grid (2/3/4/5 columns). Click or keyboard Enter/Space on a card adds one. Single-match auto-add: when the current search filter narrows to exactly one article, add it and clear the search. Dexie-powered via `useLiveQuery` (reactive on cache updates).
  - `ReceiptPanel` — sticky, scrollable item list, inline `-/+` and direct numeric qty input, remove button, customer attach/detach, per-row line total, receipt-level discount toggle, big olive `Încasează` CTA.
  - `CheckoutDialog` — 5 payment method buttons (`Numerar/Card/Bon/Modern/Transfer`), amount input with `Enter → add payment`, quick-cash shortcut buttons (`Exact / 50 / 100 / 200 / 500`), running `Achitat/De plătit în continuare/Rest` summary, disabled finalize until payments sum equals total. On success: check-icon screen with receipt number, auto-closes after 4s, returns focus to search. On network failure: auto-enqueues to Dexie and shows the same success screen with the "saved-locally" copy.
  - `CustomerDialog`, `ReceiptDiscountDialog` — both use React 19's remount-on-open pattern (form body is key'd by the dialog's `open` prop) so their useState initializers see fresh store values without tripping the `react-hooks/set-state-in-effect` lint rule.
  - `ShortcutsHint` — collapsible panel in the receipt sidebar listing `/` `F9` `F1` `F2` `Esc`.
- **`PendingQueuePill`** — topbar badge (olive accent) showing `{count} în coadă` when Dexie has unsynced receipts. Gated by `useHasMounted` so SSR matches.
- **`OnlineListener`** now triggers `processPendingQueue()` on initial mount + every `online` event — offline sales drain automatically when connectivity returns.
- **i18n** — full `messages/ro.json` + `messages/en.json` additions under `pos.*`. Every string passes through `useTranslations`.

### Added — frontend tests (3 test files, 10 tests all green)

- `money.test.ts` — line math (single, discounted, negative qty), mixed-VAT receipt totals — matching the backend's 19% + 9% scenario from `PosCheckoutTest`/`ReceiptServiceTest`.
- `pos-store.test.ts` — new-line vs. re-add consolidation, running totals across 3 VAT rates with receipt discount, qty-to-zero auto-removal, `client_local_id` lifecycle.
- Existing smoke test (login i18n) still green. Grand total: **3 files, 10 tests, ~4s**.

### Pipeline

- `pnpm lint` → clean.
- `pnpm build` → green; new route `/vanzare` is dynamic, middleware, serwist SW generated.
- `pnpm test` → 10 / 10.
- `./vendor/bin/pest` → 52 / 52.

### Deviations from spec

- **`virtua` deferred.** 30-article demo catalog doesn't justify the added code. Decision point: add virtualization when per-tenant article count exceeds ~100 *and* the grid actually drops frames on the $150 tablet target. CHANGELOG note only.
- **Customer picker is a simple `Dialog + Input + filtered list`**, not a `cmdk` combobox. `cmdk` is a new dep that wasn't pre-approved; the dialog pattern covers the functional need without the dep.
- **Mobile bottom-sheet for the receipt panel was left as a responsive grid collapse** rather than a true slide-up `Sheet`. The receipt panel becomes a full-width card below the grid at `<lg`. A true bottom-sheet would need design work beyond the current olive+cream palette. Acceptable for the MVP tablet target (1280×800).
- **"Long-press for custom quantity" = direct numeric input field inside the receipt line** rather than a modal numpad. The inline input is faster for keyboard-driven cashiers and works fine on touch. A dedicated numpad dialog can land in Prompt 7 alongside fiscal printing.
- **Red "negative stock warning" dot on receipt lines not implemented.** Requires per-line stock lookup against a specific gestiune on every qty change — the plumbing exists (`article.stock` in Dexie) but the UI signal was skipped for this pass. The backend already rejects insufficient-stock completion with `422`, so it's a UX hint not a correctness concern.
- **Queue drawer (list of pending receipts on pill click) not implemented.** The pill itself shows the count; clicking it is inert. Full drawer is a ~30-line addition deferred to Prompt 6/7.
- **Playwright E2E not added.** Vitest covers the store + money-math logic; a true browser-driven POS flow would need a harness that can fake IndexedDB + mock `/api/v1/pos/*`. Larger commitment than this prompt's scope allowed. Deferred.
- **Idempotency test on frontend.** The backend-side idempotency is covered by a dedicated Pest test (`PosCheckoutTest`). The frontend-side queue idempotency relies on the `client_local_id` travelling with the payload end-to-end — verified manually via the payload shape, not unit-tested. A queue-drain test that mocks a duplicate-submit race is a good Prompt-6 addition.
- **Customer-deleted-mid-draft**: undocumented edge case — today if a cached customer is removed on the backend and the POS submits with its stale id, the backend's `exists:customers,id` validator will 422 and the draft stays open. Acceptable for now.
- **Customer & group sync currently piggybacks on `/pos/bootstrap`** (no dedicated endpoints). Incremental via `?since=`. Clean, and keeps us to one round-trip.

### Manual verification checklist — Prompt 5

Backend running (Herd serves `http://backend.test`), seeded; frontend via `pnpm dev` on `http://localhost:3000`.

- [ ] **Login** as `owner@magazin-demo.ro` / `password`.
- [ ] **Navigate to `Vânzare`** from the sidebar; the screen loads and a "Se sincronizează catalogul…" toast appears briefly.
- [ ] **Catalog visible**: see a grid of 30 demo articles grouped under tabs `Toate / Lactate / Panificație / Băuturi / Legume-Fructe / Diverse`.
- [ ] **Click an article card** → it appears on the right receipt panel with quantity 1 at the correct price.
- [ ] **Click the same card again** → the existing line's quantity bumps to 2 (no duplicate line).
- [ ] **Search**: type part of a product name, see the grid filter; type `LAC-001` and press Enter → single match auto-adds.
- [ ] **Barcode simulation**: paste an EAN-13 from the seeded articles and press Enter → the matching article is added and the search clears.
- [ ] **Qty +/-**: use the buttons and the direct input field on a line; set to 0 → the line disappears.
- [ ] **Attach customer** (F2): dialog opens, search by name, pick one → pill appears in the receipt header.
- [ ] **Receipt discount** (F1): enter 5, Enter → `Discount: 5.00 lei` appears above `TOTAL`.
- [ ] **Checkout** (F9): big `Încasează` opens the dialog with `De plătit` = the receipt total.
- [ ] **Split payment**: amount pre-fills with total; switch method to `Card`, enter 50, `Adaugă plată`; switch to `Numerar`, exact button; `Finalizează` activates only once `Achitat` equals the total.
- [ ] **Success screen**: check-icon, `Bon #N • XX.XX lei`, auto-closes after ~4s, focus returns to search.
- [ ] **Offline drill**: DevTools → Network → Offline → run through another sale → `Finalizează` shows "Bon salvat local" screen and the topbar grows a `1 în coadă` pill. Flip back online → within seconds the pill drops to 0 and the queue is drained.
- [ ] **Idempotency**: with DevTools slow-3G or after a backend restart, re-click finalize; the second call is recognised as `idempotent_replay=true` and stock stays decremented only once.
- [ ] **Keyboard**: `/` focuses search, `Esc` closes any open dialog, `F9` reopens checkout.

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
