# MaXPos API v1 — Endpoint reference

Base URL (dev): `http://backend.test/api/v1`

All protected routes require a Sanctum bearer token: `Authorization: Bearer <token>`. All JSON responses follow the envelope:

```json
{ "data": ..., "meta": { ... } }
```

Error responses set a non-2xx status and use `meta.error` (for `PosException`) or the standard Laravel validation envelope (`errors: { field: [...] }`) for 422.

Money values are returned as decimal **strings** (e.g. `"100.00"`), never floats. Quantities use 3-decimal strings (e.g. `"2.500"`).

Tenancy: resolved from the authenticated user's `tenant_id`. Super admin (`tenant_id = null`) sees every tenant — normal users are scoped to their own.

---

## Auth

### POST `/auth/login`
Public. Returns a Sanctum token.

Request
```json
{ "email": "owner@magazin-demo.ro", "password": "password", "device_name": "pos-tablet-01" }
```
Response `200`
```json
{
  "data": {
    "token": "1|abcdef...",
    "user": { "id": 1, "name": "...", "email": "...", "tenant_id": "uuid", "roles": ["tenant-owner"], "permissions": ["pos.sell", ...] }
  },
  "meta": {}
}
```

### POST `/auth/logout`
Revokes the current token. `200`.

### GET `/me`
Returns the authenticated user and, if present, their currently-open cash session.
```json
{ "data": { "user": {...}, "active_session": { "id": "...", "status": "open", ... } | null }, "meta": {} }
```

---

## Catalog

### GET `/articles?search=&group_id=&only_active=true&per_page=50`
Paginated. Search matches name/sku. Returns `meta.current_page, per_page, total, last_page`.

### GET `/articles/by-barcode/{barcode}`
Returns the matching article (including its barcodes) or `404`.

### GET `/customers?search=&per_page=50`
Paginated. Search matches name/cui.

---

## Cash sessions

### POST `/cash-sessions/open`  — `can:pos.open-session`
```json
{ "location_id": "uuid", "initial_cash": 100.00, "notes": "optional" }
```
`201` with the session. `409` if the user already has an open session at that location.

### POST `/cash-sessions/{id}/close`  — `can:pos.close-session`
```json
{ "final_cash": 350.00, "notes": "optional" }
```
`200`. Backend computes `expected_cash = initial_cash + Σ(cash payments on completed receipts)`. Rejects (`409`) if any draft receipts remain.

### GET `/cash-sessions/current`
Returns `data: null` or the user's single `open` session.

---

## Receipts

### POST `/receipts`  — `can:pos.sell`
```json
{ "cash_session_id": "uuid", "customer_id": "uuid (optional)" }
```
`201`. Receipt is created with a gapless per-location `number` (atomic counter row locked via `SELECT ... FOR UPDATE`).

### GET `/receipts/{id}`
Returns the receipt with nested `items` and `payments`.

### POST `/receipts/{id}/items`  — `can:pos.sell`
```json
{ "article_id": "uuid", "quantity": 2, "gestiune_id": "uuid (optional — defaults to article.default_gestiune)", "discount_amount": 0 }
```
`201`. Only on **draft** receipts. `quantity` may be negative for return lines. Totals recomputed.

### PATCH `/receipts/{id}/items/{itemId}`  — `can:pos.sell`
```json
{ "quantity": 3 }
```
`200`. Only on draft. Recomputes totals.

### DELETE `/receipts/{id}/items/{itemId}`  — `can:pos.sell`
`200`. Only on draft.

### POST `/receipts/{id}/discount`  — `can:pos.discount`
```json
{ "amount": 5.00 }
```
Header-level discount. Only on draft.

### POST `/receipts/{id}/complete`  — `can:pos.sell`
```json
{ "payments": [
    { "method": "cash", "amount": 50.00 },
    { "method": "card", "amount": 50.00, "reference": "POS-TXN-12345" }
] }
```
`200`. Methods: `cash | card | voucher | modern | transfer`. `Σ(amounts)` must equal `receipt.total` to the cent — otherwise `422`. Stock levels are decremented for each line's gestiune. For negative-qty lines stock is *incremented* (return).

### POST `/receipts/{id}/void`  — `can:pos.void`
```json
{ "reason": "Cerere client" }
```
`200`. If the receipt was already `completed`, stock decrements are reversed. `409` if `fiscal_printed_at` is set (must use `stornare` flow instead — later prompt).

---

## Error shapes

Validation (422)
```json
{ "message": "...", "errors": { "field": ["Mesaj în română."] } }
```

Domain error (PosException — 409 / 422 depending)
```json
{ "data": null, "meta": { "error": "Mesaj descriptiv în română." } }
```

Authorization (403) — Laravel default; user lacks the required `pos.*` permission.

---

## Numbering guarantee

Per-location receipt numbering is atomic and gapless:
1. The service opens a DB transaction.
2. `SELECT ... FOR UPDATE` locks the `receipt_number_counters` row for `(tenant_id, location_id)`.
3. `last_number + 1` is assigned to the new receipt.
4. Counter row is updated and the receipt inserted in the same transaction.

On MySQL 8 this is true row-level locking; on SQLite (dev) the whole write is serialized. Either way, no two concurrent requests can ever observe the same next number.
