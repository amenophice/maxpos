import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { ArticleRow } from "@/lib/db";
import { selectTotals, usePosStore } from "@/stores/pos-store";

// Mock localStorage-backed persist so each test starts clean.
beforeEach(() => {
  localStorage.clear();
  // Reset store state by issuing clearDraft + wiping persisted value.
  usePosStore.getState().clearDraft();
});

afterEach(() => {
  vi.restoreAllMocks();
});

function makeArticle(id: string, price: string, vat = "19.00"): ArticleRow {
  return {
    id,
    sku: `SKU-${id}`,
    name: `Article ${id}`,
    description: null,
    price,
    vat_rate: vat,
    group_id: null,
    default_gestiune_id: "gest-1",
    barcodes: [],
    stock: { "gest-1": "100.000" },
    unit: "buc",
    plu: null,
    is_active: true,
    updated_at: new Date().toISOString(),
  };
}

describe("pos-store — totals and item mutations", () => {
  it("adds new article lines and bumps quantity on re-add", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();
    addItem(makeArticle("a", "10.00"), 1);
    addItem(makeArticle("a", "10.00"), 2);

    const state = usePosStore.getState();
    expect(state.lines).toHaveLength(1);
    // Quantities are stored at 3 decimals to match backend's decimal(15,3).
    expect(state.lines[0].quantity).toBe("3.000");
  });

  it("sums kg quantities when re-adding the same article (0.500 + 0.350 = 0.850)", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();
    const article = makeArticle("brz", "11.40");
    article.unit = "kg";
    addItem(article, "0.500");
    addItem(article, "0.350");

    const lines = usePosStore.getState().lines;
    expect(lines).toHaveLength(1);
    expect(lines[0].quantity).toBe("0.850");
    expect(lines[0].unit).toBe("kg");
  });

  it("sets focusLineId for a freshly added kg line so the input can auto-focus", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();

    const cheese = makeArticle("brz", "11.40");
    cheese.unit = "kg";
    addItem(cheese);

    const state = usePosStore.getState();
    expect(state.lines).toHaveLength(1);
    expect(state.focusLineId).toBe(state.lines[0].localId);

    state.consumeFocusLineId();
    expect(usePosStore.getState().focusLineId).toBeNull();
  });

  it("does NOT set focusLineId when intentEditWeight is false (scale-barcode path)", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();

    const cheese = makeArticle("brz", "11.40");
    cheese.unit = "kg";
    addItem(cheese, "0.850", { intentEditWeight: false });

    expect(usePosStore.getState().focusLineId).toBeNull();
  });

  it("does NOT set focusLineId for counted-unit (buc) lines", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();

    const bread = makeArticle("pn", "4.20");
    bread.unit = "buc";
    addItem(bread);

    expect(usePosStore.getState().focusLineId).toBeNull();
  });

  it("mixes kg and buc lines without polluting each other", () => {
    const { openDraft, addItem } = usePosStore.getState();
    openDraft();
    const cheese = makeArticle("brz", "11.40");
    cheese.unit = "kg";
    const bread = makeArticle("pn", "4.20");
    bread.unit = "buc";
    addItem(cheese, "0.850");
    addItem(bread, 1);

    const lines = usePosStore.getState().lines;
    expect(lines.map((l) => [l.unit, l.quantity])).toEqual([
      ["kg", "0.850"],
      ["buc", "1.000"],
    ]);
  });

  it("computes running totals across mixed VAT rates", () => {
    const { openDraft, addItem, applyReceiptDiscount } = usePosStore.getState();
    openDraft();
    addItem(makeArticle("a", "10.00", "19.00"), 2); // 20.00
    addItem(makeArticle("b", "5.50", "9.00"), 3); // 16.50
    addItem(makeArticle("c", "100.00", "19.00"), 1); // 100.00
    applyReceiptDiscount(5);

    // selectTotals now returns strings (shallow-stable for useShallow);
    // reconstruct Decimals only when we need math. Values are already rounded
    // to MONEY_SCALE on the way out.
    const totals = selectTotals(usePosStore.getState());
    expect(totals.subtotal).toBe("136.50");
    expect(totals.vatTotal).toBe("20.52");
    expect(totals.total).toBe("131.50");
  });

  it("removes a line when quantity set to 0 via updateItemQuantity", () => {
    const { openDraft, addItem, updateItemQuantity } = usePosStore.getState();
    openDraft();
    addItem(makeArticle("a", "10.00"), 2);
    const id = usePosStore.getState().lines[0].localId;
    updateItemQuantity(id, 0);

    expect(usePosStore.getState().lines).toHaveLength(0);
  });

  it("assigns a client_local_id on openDraft and clears on clearDraft", () => {
    const { openDraft, clearDraft } = usePosStore.getState();
    openDraft();
    expect(usePosStore.getState().clientLocalId).toMatch(/^pos-/);

    clearDraft();
    expect(usePosStore.getState().clientLocalId).toBeNull();
    expect(usePosStore.getState().lines).toHaveLength(0);
  });
});
