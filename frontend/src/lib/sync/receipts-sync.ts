import { api } from "@/lib/api";
import { db, type PendingReceiptRow } from "@/lib/db";

export interface CheckoutPayload {
  cash_session_id: string;
  customer_id?: string | null;
  client_local_id: string;
  receipt_discount?: number;
  items: Array<{
    article_id: string;
    quantity: number;
    gestiune_id?: string | null;
    discount_amount?: number;
  }>;
  payments: Array<{ method: string; amount: number; reference?: string | null }>;
}

export type SubmitResult =
  | { ok: true; receiptId: string; number: number; total: string; idempotentReplay: boolean }
  | { ok: false; retriable: true; error: string }
  | { ok: false; retriable: false; error: string; status: number };

export async function submitCheckout(payload: CheckoutPayload): Promise<SubmitResult> {
  try {
    const response = await api.post<{
      data: { id: string; number: number; total: string };
      meta: { idempotent_replay: boolean };
    }>("/pos/checkout", payload);
    return {
      ok: true,
      receiptId: response.data.data.id,
      number: response.data.data.number,
      total: response.data.data.total,
      idempotentReplay: response.data.meta.idempotent_replay,
    };
  } catch (e: unknown) {
    // Narrow axios error shape without importing types everywhere.
    const err = e as { response?: { status?: number; data?: { meta?: { error?: string } } }; message?: string };
    const status = err?.response?.status;
    if (status === undefined) {
      return { ok: false, retriable: true, error: err?.message ?? "network" };
    }
    const msg = err.response?.data?.meta?.error ?? err.message ?? "unknown";
    // 5xx is retriable; 4xx is business error (do not retry blindly).
    if (status >= 500) return { ok: false, retriable: true, error: msg };
    return { ok: false, retriable: false, error: msg, status };
  }
}

/**
 * Enqueue a receipt payload into Dexie — used when the live POST failed with
 * a retriable network error, or when we want to guarantee durability before
 * hitting the wire.
 */
export async function enqueuePendingReceipt(payload: CheckoutPayload): Promise<void> {
  if (!db) return;
  const now = new Date().toISOString();
  await db.pending_receipts.put({
    local_id: payload.client_local_id,
    payload,
    status: "pending",
    last_error: null,
    server_receipt_id: null,
    created_at: now,
    updated_at: now,
    retry_count: 0,
  } as PendingReceiptRow);
}

export async function listPendingReceipts(): Promise<PendingReceiptRow[]> {
  if (!db) return [];
  return db.pending_receipts.orderBy("created_at").toArray();
}

export async function pendingReceiptsCount(): Promise<number> {
  if (!db) return 0;
  return db.pending_receipts.where("status").notEqual("syncing").count();
}

let processing = false;

/**
 * Walk the queue once. Each entry is POSTed to /pos/checkout with its local id
 * for idempotency. Successes are removed; retriable failures stay pending;
 * non-retriable failures move to `failed`.
 */
export async function processPendingQueue(): Promise<{ sent: number; failed: number }> {
  if (!db || processing) return { sent: 0, failed: 0 };
  processing = true;

  let sent = 0;
  let failed = 0;

  try {
    const pending = await db.pending_receipts
      .where("status")
      .anyOf("pending", "failed")
      .sortBy("created_at");

    for (const row of pending) {
      if (row.id === undefined) continue;
      await db.pending_receipts.update(row.id, { status: "syncing" });

      const result = await submitCheckout(row.payload as CheckoutPayload);
      const now = new Date().toISOString();

      if (result.ok) {
        await db.pending_receipts.delete(row.id);
        sent++;
      } else if (result.retriable) {
        await db.pending_receipts.update(row.id, {
          status: "pending",
          last_error: result.error,
          retry_count: row.retry_count + 1,
          updated_at: now,
        });
        // Stop early on retriable error — network is probably still flaky.
        break;
      } else {
        await db.pending_receipts.update(row.id, {
          status: "failed",
          last_error: result.error,
          retry_count: row.retry_count + 1,
          updated_at: now,
        });
        failed++;
      }
    }
  } finally {
    processing = false;
  }

  return { sent, failed };
}
