import Dexie, { type EntityTable } from "dexie";

export interface ArticleRow {
  id: string;
  sku: string;
  name: string;
  price: string;
  vat_rate: string;
  group_id: string | null;
  default_gestiune_id: string | null;
  barcodes: string[];
  unit: string;
  plu: string | null;
  is_active: boolean;
  updated_at: string;
}

export interface CustomerRow {
  id: string;
  name: string;
  cui: string | null;
  is_company: boolean;
  updated_at: string;
}

export interface PendingReceiptRow {
  id?: number;
  local_id: string;
  payload: unknown;
  created_at: string;
}

export interface PendingOpRow {
  id?: number;
  type: string;
  payload: unknown;
  created_at: string;
  retry_count: number;
}

export interface SyncMetaRow {
  key: string;
  value: unknown;
}

export class MaxposDB extends Dexie {
  articles!: EntityTable<ArticleRow, "id">;
  customers!: EntityTable<CustomerRow, "id">;
  pending_receipts!: EntityTable<PendingReceiptRow, "id">;
  pending_ops!: EntityTable<PendingOpRow, "id">;
  sync_meta!: EntityTable<SyncMetaRow, "key">;

  constructor() {
    super("maxpos");
    this.version(1).stores({
      articles: "id, sku, name, group_id, updated_at, *barcodes",
      customers: "id, name, cui, updated_at",
      pending_receipts: "++id, local_id, created_at",
      pending_ops: "++id, type, created_at, retry_count",
      sync_meta: "key",
    });
  }
}

export const db = typeof window !== "undefined" ? new MaxposDB() : (undefined as unknown as MaxposDB);
