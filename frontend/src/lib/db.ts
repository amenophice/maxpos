import Dexie, { type EntityTable } from "dexie";

/**
 * Romanian-aware lower-cased + diacritic-stripped form. Used both for the
 * `articles.name_normalized` index (so prefix/contains queries don't depend
 * on whether the cashier types `pâine` or `paine`) and for ad-hoc search.
 */
export function normalizeRoText(s: string): string {
  return s
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/ș|ş/g, "s")
    .replace(/ț|ţ/g, "t")
    .replace(/â|ă/g, "a")
    .replace(/î/g, "i")
    .trim();
}

export interface ArticleRow {
  id: string;
  sku: string;
  name: string;
  /** Lowercased + diacritic-stripped form of `name`. Indexed in v3+. */
  name_normalized: string;
  description: string | null;
  price: string;
  vat_rate: string;
  group_id: string | null;
  default_gestiune_id: string | null;
  barcodes: string[];
  stock: Record<string, string>; // gestiune_id -> quantity
  unit: string;
  plu: string | null;
  is_active: boolean;
  updated_at: string;
}

export interface GroupRow {
  id: string;
  name: string;
  parent_id: string | null;
  display_order: number;
  updated_at: string;
}

export interface CustomerRow {
  id: string;
  name: string;
  cui: string | null;
  registration_number: string | null;
  city: string | null;
  county: string | null;
  is_company: boolean;
  email: string | null;
  phone: string | null;
  updated_at: string;
}

export interface GestiuneRow {
  id: string;
  location_id: string;
  name: string;
  type: "global-valoric" | "cantitativ-valoric";
}

export type PendingReceiptStatus = "pending" | "syncing" | "failed";

export interface PendingReceiptRow {
  id?: number;
  local_id: string;
  payload: unknown;
  status: PendingReceiptStatus;
  last_error: string | null;
  server_receipt_id: string | null;
  created_at: string;
  updated_at: string;
  retry_count: number;
}

export interface SyncMetaRow {
  key: string;
  value: unknown;
}

export class MaxposDB extends Dexie {
  articles!: EntityTable<ArticleRow, "id">;
  groups!: EntityTable<GroupRow, "id">;
  customers!: EntityTable<CustomerRow, "id">;
  gestiuni!: EntityTable<GestiuneRow, "id">;
  pending_receipts!: EntityTable<PendingReceiptRow, "id">;
  sync_meta!: EntityTable<SyncMetaRow, "key">;

  constructor() {
    super("maxpos");
    // v1: initial prompt-4 schema.
    // v2: groups+gestiuni, reshape pending_receipts, stock map on articles.
    // v3: index articles.name_normalized for diacritic-insensitive search.
    this.version(2).stores({
      articles: "id, sku, name, group_id, updated_at, *barcodes",
      groups: "id, parent_id, display_order, updated_at",
      customers: "id, name, cui, updated_at",
      gestiuni: "id, location_id",
      pending_receipts: "++id, local_id, status, created_at",
      sync_meta: "key",
    });

    this.version(3)
      .stores({
        articles:
          "id, sku, name, name_normalized, group_id, updated_at, *barcodes",
        groups: "id, parent_id, display_order, updated_at",
        customers: "id, name, cui, updated_at",
        gestiuni: "id, location_id",
        pending_receipts: "++id, local_id, status, created_at",
        sync_meta: "key",
      })
      .upgrade(async (tx) => {
        await tx
          .table<ArticleRow>("articles")
          .toCollection()
          .modify((row) => {
            row.name_normalized = normalizeRoText(row.name ?? "");
          });
      });
  }
}

export const db =
  typeof window !== "undefined" ? new MaxposDB() : (undefined as unknown as MaxposDB);

export async function findArticleByBarcode(barcode: string): Promise<ArticleRow | null> {
  if (!db) return null;
  const match = await db.articles.where("barcodes").equals(barcode).first();
  return match ?? null;
}

export async function findArticleByPlu(plu: string): Promise<ArticleRow | null> {
  if (!db) return null;
  // Both stored and incoming PLUs may be zero-padded inconsistently — the
  // scale prints exactly 5 digits, but the catalog might keep "12345" or
  // "012345". Strip leading zeros on both sides for the comparison.
  const needle = plu.replace(/^0+/, "") || "0";
  const match = await db.articles
    .filter((a) => {
      const stored = (a.plu ?? "").replace(/^0+/, "") || "0";
      return stored === needle;
    })
    .first();
  return match ?? null;
}

/**
 * Pure filter — testable without IndexedDB. `searchArticles` defers to this
 * after pulling rows from Dexie.
 *
 * Match semantics (keep in sync with the search bar UX):
 *   - empty query → all rows pass (caller still applies group filter)
 *   - non-empty   → row matches if its `name_normalized` *contains* the
 *                   normalized needle, OR its lower-cased `sku` contains
 *                   the raw lower-cased query (SKUs are not diacritic'd).
 */
export function matchArticleQuery(
  rows: ArticleRow[],
  query: string,
): ArticleRow[] {
  const needle = normalizeRoText(query);
  if (!needle) return rows;
  const rawLower = query.trim().toLowerCase();
  return rows.filter(
    (a) =>
      a.name_normalized.includes(needle) ||
      a.sku.toLowerCase().includes(rawLower),
  );
}

export async function searchArticles(
  query: string,
  groupId?: string | null,
  limit = 500,
): Promise<ArticleRow[]> {
  if (!db) return [];
  let coll = db.articles.toCollection();
  if (groupId) coll = coll.filter((a) => a.group_id === groupId);
  const rows = await coll.toArray();
  return matchArticleQuery(rows, query)
    .sort((a, b) => a.name.localeCompare(b.name, "ro"))
    .slice(0, limit);
}

export async function getAllGroups(): Promise<GroupRow[]> {
  if (!db) return [];
  return db.groups.orderBy("display_order").toArray();
}

export async function getAllCustomers(limit = 500): Promise<CustomerRow[]> {
  if (!db) return [];
  return db.customers.orderBy("name").limit(limit).toArray();
}

export async function getDefaultGestiune(): Promise<GestiuneRow | null> {
  if (!db) return null;
  const first = await db.gestiuni.toCollection().first();
  return first ?? null;
}

export async function articlesCount(): Promise<number> {
  if (!db) return 0;
  return db.articles.count();
}

export async function getMeta<T>(key: string): Promise<T | null> {
  if (!db) return null;
  const row = await db.sync_meta.get(key);
  return (row?.value as T) ?? null;
}

export async function setMeta<T>(key: string, value: T): Promise<void> {
  if (!db) return;
  await db.sync_meta.put({ key, value });
}
