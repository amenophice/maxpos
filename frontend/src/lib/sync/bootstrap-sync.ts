import { api } from "@/lib/api";
import {
  db,
  getMeta,
  normalizeRoText,
  setMeta,
  type ArticleRow,
  type CustomerRow,
  type GestiuneRow,
  type GroupRow,
} from "@/lib/db";

interface BootstrapResponse {
  data: {
    articles: Array<{
      id: string;
      sku: string;
      name: string;
      description: string | null;
      price: string;
      vat_rate: string;
      group_id: string | null;
      default_gestiune_id: string | null;
      unit: string;
      plu: string | null;
      is_active: boolean;
      updated_at: string;
      barcodes?: Array<{ barcode: string; type: string }>;
      stock_levels?: Array<{ gestiune_id: string; quantity: string }>;
    }>;
    groups: Array<{
      id: string;
      name: string;
      parent_id: string | null;
      display_order: number;
      updated_at: string;
    }>;
    customers: Array<CustomerRow>;
    gestiuni: Array<GestiuneRow>;
  };
  meta: {
    server_time: string;
    since: string | null;
    scale_barcode_prefixes?: string[];
  };
}

const LAST_SYNC_KEY = "bootstrap.last_sync_at";
export const SCALE_PREFIXES_KEY = "scale_barcode_prefixes";
export const DEFAULT_SCALE_PREFIXES = ["26", "27", "28", "29"];

export interface BootstrapSyncResult {
  fromCache: boolean;
  articles: number;
  groups: number;
  customers: number;
  gestiuni: number;
  serverTime: string | null;
}

/**
 * Pulls the catalog from `/pos/bootstrap` into IndexedDB.
 *
 * The catalog sync is **manual** — call this from a user-initiated action
 * (sync button) or from `/vanzare`'s first-load probe (when the local
 * articles table is empty). There are no internal interval/focus/online
 * triggers. By default it sends `?since=<last_sync_at>` for incremental
 * fetches; pass `{ force: true }` to do a full sync.
 *
 * The pending-receipts queue (write-side) syncs on its own schedule
 * (`processPendingQueue`) — keep that path independent.
 */
export async function bootstrapSync(
  options: { force?: boolean } = {},
): Promise<BootstrapSyncResult> {
  const empty: BootstrapSyncResult = {
    fromCache: true,
    articles: 0,
    groups: 0,
    customers: 0,
    gestiuni: 0,
    serverTime: null,
  };
  if (!db) return empty;

  const lastSync = await getMeta<string>(LAST_SYNC_KEY);
  const since = options.force ? null : lastSync;
  const url = since ? `/pos/bootstrap?since=${encodeURIComponent(since)}` : "/pos/bootstrap";

  const response = await api.get<BootstrapResponse>(url);
  const { articles, groups, customers, gestiuni } = response.data.data;

  const articleRows: ArticleRow[] = articles.map((a) => ({
    id: a.id,
    sku: a.sku,
    name: a.name,
    name_normalized: normalizeRoText(a.name),
    description: a.description,
    price: a.price,
    vat_rate: a.vat_rate,
    group_id: a.group_id,
    default_gestiune_id: a.default_gestiune_id,
    barcodes: (a.barcodes ?? []).map((b) => b.barcode),
    stock: Object.fromEntries((a.stock_levels ?? []).map((s) => [s.gestiune_id, s.quantity])),
    unit: a.unit,
    plu: a.plu,
    is_active: a.is_active,
    updated_at: a.updated_at,
  }));

  const groupRows: GroupRow[] = groups.map((g) => ({
    id: g.id,
    name: g.name,
    parent_id: g.parent_id,
    display_order: g.display_order,
    updated_at: g.updated_at,
  }));

  await db.transaction("rw", [db.articles, db.groups, db.customers, db.gestiuni], async () => {
    if (articleRows.length) await db.articles.bulkPut(articleRows);
    if (groupRows.length) await db.groups.bulkPut(groupRows);
    if (customers.length) await db.customers.bulkPut(customers);
    // gestiuni are small and ungated by ?since; rehydrate whole list on every sync
    if (gestiuni.length) {
      await db.gestiuni.clear();
      await db.gestiuni.bulkPut(gestiuni);
    }
  });

  await setMeta(LAST_SYNC_KEY, response.data.meta.server_time);
  if (response.data.meta.scale_barcode_prefixes) {
    await setMeta(SCALE_PREFIXES_KEY, response.data.meta.scale_barcode_prefixes);
  }

  return {
    fromCache: false,
    articles: articleRows.length,
    groups: groupRows.length,
    customers: customers.length,
    gestiuni: gestiuni.length,
    serverTime: response.data.meta.server_time,
  };
}

export async function getLastSyncAt(): Promise<string | null> {
  return getMeta<string>(LAST_SYNC_KEY);
}

export async function getScalePrefixes(): Promise<string[]> {
  const stored = await getMeta<string[]>(SCALE_PREFIXES_KEY);
  return stored && stored.length ? stored : DEFAULT_SCALE_PREFIXES;
}
