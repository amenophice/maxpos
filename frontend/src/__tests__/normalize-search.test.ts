import { describe, expect, it } from "vitest";
import {
  matchArticleQuery,
  normalizeRoText,
  type ArticleRow,
} from "@/lib/db";

describe("normalizeRoText — Romanian diacritic-insensitive folding", () => {
  it.each([
    ["Pâine", "paine"],
    ["Mere roșii", "mere rosii"],
    ["Cașcaval", "cascaval"],
    ["ÎMPĂRAT", "imparat"],
    ["Țară", "tara"],
    ["", ""],
    // Both T- and S-cedilla (legacy) and T- and S-comma (correct) should fold.
    ["Țeapă cu ş și ț", "teapa cu s si t"],
    // Pure ASCII passes through lowercase.
    ["Coca-Cola 2L", "coca-cola 2l"],
  ])("normalizes %s → %s", (input, expected) => {
    expect(normalizeRoText(input)).toBe(expected);
  });
});

const article = (overrides: Partial<ArticleRow>): ArticleRow => ({
  id: overrides.id ?? "x",
  sku: overrides.sku ?? "SKU-X",
  name: overrides.name ?? "Article",
  name_normalized: normalizeRoText(overrides.name ?? "Article"),
  description: null,
  price: "1.00",
  vat_rate: "19.00",
  group_id: null,
  default_gestiune_id: null,
  barcodes: [],
  stock: {},
  unit: "buc",
  plu: null,
  is_active: true,
  updated_at: new Date().toISOString(),
});

describe("matchArticleQuery", () => {
  const rows: ArticleRow[] = [
    article({ id: "1", sku: "PAN-001", name: "Pâine albă 500g" }),
    article({ id: "2", sku: "LF-003", name: "Mere roșii 1kg" }),
    article({ id: "3", sku: "LAC-006", name: "Cașcaval afumat 300g" }),
    article({ id: "4", sku: "BAU-001", name: "Coca-Cola 2L" }),
  ];

  it('matches "paine" against "Pâine albă 500g"', () => {
    expect(matchArticleQuery(rows, "paine").map((r) => r.id)).toEqual(["1"]);
  });

  it('matches "rosii" against "Mere roșii 1kg"', () => {
    expect(matchArticleQuery(rows, "rosii").map((r) => r.id)).toEqual(["2"]);
  });

  it("matches by lowercase SKU substring", () => {
    expect(matchArticleQuery(rows, "lac").map((r) => r.id)).toEqual(["3"]);
  });

  it("returns all rows on empty query", () => {
    expect(matchArticleQuery(rows, "").map((r) => r.id)).toEqual(["1", "2", "3", "4"]);
  });

  it("returns empty list when nothing matches", () => {
    expect(matchArticleQuery(rows, "zzz")).toEqual([]);
  });
});
