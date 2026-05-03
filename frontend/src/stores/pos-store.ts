"use client";

import Decimal from "decimal.js";
import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { ArticleRow, CustomerRow } from "@/lib/db";
import { computeLineTotals, computeReceiptTotals } from "@/lib/money";
import { isWeightUnit } from "@/lib/units";

export interface DraftLine {
  localId: string;
  articleId: string;
  sku: string;
  name: string;
  unit: string;
  unitPrice: string;
  vatRate: string;
  quantity: string;
  discountAmount: string;
  gestiuneId: string | null;
}

export interface DraftCustomer {
  id: string;
  name: string;
  cui: string | null;
  is_company: boolean;
}

export type DraftStatus = "idle" | "drafting" | "submitting";

/**
 * Options for `addItem`.
 *
 * `intentEditWeight` defaults to `true`. The store sets `focusLineId` to a
 * newly created **weight-unit** line whenever this flag is true. Set it to
 * `false` from callers that already know the final quantity — notably the
 * scale-barcode path, where the EAN ticket carries the kg value and the
 * cashier shouldn't have to confirm anything.
 */
export interface AddItemOptions {
  intentEditWeight?: boolean;
}

interface PosState {
  cashSessionId: string | null;
  lines: DraftLine[];
  receiptDiscount: string;
  customer: DraftCustomer | null;
  status: DraftStatus;
  clientLocalId: string | null;

  /**
   * Transient: the local id of a kg/l line whose quantity input wants
   * keyboard focus the moment it mounts. Set by `addItem` for newly
   * created weight-unit lines (intent: edit weight); cleared by the
   * input via `consumeFocusLineId()` once it has focused.
   */
  focusLineId: string | null;
  consumeFocusLineId: () => void;

  setCashSessionId: (id: string | null) => void;
  openDraft: () => void;
  /**
   * Quantity is always stored as a string with up to 3 decimals to match the
   * backend's `decimal(15,3)` and avoid float precision drift (0.1 + 0.2
   * problem on weighed kg lines). Accepting `string | number` here is just
   * for ergonomics — we always coerce to a Decimal-flavoured string.
   */
  addItem: (article: ArticleRow, qty?: number | string, opts?: AddItemOptions) => void;
  updateItemQuantity: (localId: string, qty: number | string) => void;
  applyLineDiscount: (localId: string, amount: number) => void;
  removeItem: (localId: string) => void;
  applyReceiptDiscount: (amount: number) => void;
  setCustomer: (c: CustomerRow | null) => void;
  clearDraft: () => void;
  setStatus: (s: DraftStatus) => void;
}

const QTY_SCALE = 3;
const toQty = (v: Decimal.Value): string => new Decimal(v).toFixed(QTY_SCALE);

const newClientLocalId = () =>
  `pos-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;

export const usePosStore = create<PosState>()(
  persist(
    (set) => ({
      cashSessionId: null,
      lines: [],
      receiptDiscount: "0",
      customer: null,
      status: "idle",
      clientLocalId: null,
      focusLineId: null,

      consumeFocusLineId: () => set({ focusLineId: null }),

      setCashSessionId: (id) => set({ cashSessionId: id }),

      openDraft: () =>
        set({
          lines: [],
          receiptDiscount: "0",
          customer: null,
          status: "drafting",
          clientLocalId: newClientLocalId(),
          focusLineId: null,
        }),

      addItem: (article, qty = 1, opts) =>
        set((state) => {
          // Re-add for SAME article AND SAME default_gestiune sums quantities
          // — 0.500 kg + 0.350 kg becomes a single 0.850 kg line, not two
          // rows. If a future flow needs to force a new line (e.g. price
          // override) we'll add an explicit `forceNewLine` flag.
          const existing = state.lines.find(
            (l) =>
              l.articleId === article.id && l.gestiuneId === article.default_gestiune_id,
          );
          if (existing) {
            return {
              lines: state.lines.map((l) =>
                l.localId === existing.localId
                  ? { ...l, quantity: toQty(new Decimal(l.quantity).add(new Decimal(qty))) }
                  : l,
              ),
              status: "drafting",
              clientLocalId: state.clientLocalId ?? newClientLocalId(),
            };
          }
          const line: DraftLine = {
            localId: crypto.randomUUID(),
            articleId: article.id,
            sku: article.sku,
            name: article.name,
            unit: article.unit,
            unitPrice: article.price,
            vatRate: article.vat_rate,
            quantity: toQty(qty),
            discountAmount: "0",
            gestiuneId: article.default_gestiune_id,
          };
          // Auto-focus the new line's quantity input only for weight-unit
          // lines where the caller hasn't already supplied the final weight
          // (scale-barcode path passes intentEditWeight: false).
          const wantsFocus =
            (opts?.intentEditWeight ?? true) && isWeightUnit(article.unit);
          return {
            lines: [...state.lines, line],
            status: "drafting",
            clientLocalId: state.clientLocalId ?? newClientLocalId(),
            focusLineId: wantsFocus ? line.localId : state.focusLineId,
          };
        }),

      updateItemQuantity: (localId, qty) =>
        set((state) => ({
          lines: state.lines
            .map((l) =>
              l.localId === localId ? { ...l, quantity: toQty(qty) } : l,
            )
            .filter((l) => !new Decimal(l.quantity).equals(0)),
        })),

      applyLineDiscount: (localId, amount) =>
        set((state) => ({
          lines: state.lines.map((l) =>
            l.localId === localId ? { ...l, discountAmount: new Decimal(amount).toString() } : l,
          ),
        })),

      removeItem: (localId) =>
        set((state) => ({ lines: state.lines.filter((l) => l.localId !== localId) })),

      applyReceiptDiscount: (amount) => set({ receiptDiscount: new Decimal(amount).toString() }),

      setCustomer: (c) =>
        set({
          customer: c
            ? { id: c.id, name: c.name, cui: c.cui, is_company: c.is_company }
            : null,
        }),

      clearDraft: () =>
        set({
          lines: [],
          receiptDiscount: "0",
          customer: null,
          status: "idle",
          clientLocalId: null,
          focusLineId: null,
        }),

      setStatus: (s) => set({ status: s }),
    }),
    {
      name: "maxpos-draft-v1",
      storage: createJSONStorage(() => localStorage),
      partialize: (s) => ({
        cashSessionId: s.cashSessionId,
        lines: s.lines,
        receiptDiscount: s.receiptDiscount,
        customer: s.customer,
        clientLocalId: s.clientLocalId,
      }),
    },
  ),
);

export interface ReceiptTotalsStrings {
  subtotal: string;
  vatTotal: string;
  discountTotal: string;
  total: string;
}

/**
 * Derived selector — returns **strings** (not Decimal instances) so consumers
 * can pair it with Zustand's `useShallow`. Under React 19 +
 * `useSyncExternalStore`, an object-returning selector with fresh reference
 * values on every call triggers "Maximum update depth exceeded" / "The result
 * of getServerSnapshot should be cached". Strings compare by value under
 * `Object.is`, so `useShallow(selectTotals)` is now stable across renders
 * whenever the inputs haven't changed. Callers that need Decimal math
 * reconstruct `new Decimal(totals.total)` locally inside a `useMemo`.
 */
export function selectTotals(state: PosState): ReceiptTotalsStrings {
  const lineTotals = state.lines.map((l) =>
    computeLineTotals({
      unitPrice: l.unitPrice,
      quantity: l.quantity,
      vatRate: l.vatRate,
      discountAmount: l.discountAmount,
    }),
  );
  const totals = computeReceiptTotals(lineTotals, state.receiptDiscount);
  return {
    subtotal: totals.subtotal.toFixed(2),
    vatTotal: totals.vatTotal.toFixed(2),
    discountTotal: totals.discountTotal.toFixed(2),
    total: totals.total.toFixed(2),
  };
}

// Re-exported so consumers don't have to reach for decimal.js directly.
export { Decimal };
