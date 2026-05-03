import { describe, expect, it } from "vitest";
import { computeLineTotals, computeReceiptTotals } from "@/lib/money";

describe("money — inclusive-VAT line math (must match backend bcmath)", () => {
  it("computes VAT by extracting from gross-inclusive price", () => {
    // 10.00 @ 19% x 2 = gross 20.00, VAT = 20 * 19/119 ≈ 3.19
    const line = computeLineTotals({ unitPrice: "10.00", quantity: 2, vatRate: "19.00" });
    expect(line.lineTotal.toFixed(2)).toBe("20.00");
    expect(line.lineVat.toFixed(2)).toBe("3.19");
    expect(line.lineSubtotal.toFixed(2)).toBe("16.81");
  });

  it("applies per-line discount before VAT extraction", () => {
    const line = computeLineTotals({
      unitPrice: "50.00",
      quantity: 1,
      vatRate: "19.00",
      discountAmount: "10.00",
    });
    expect(line.lineTotal.toFixed(2)).toBe("40.00");
    expect(line.lineVat.toFixed(2)).toBe("6.39");
  });

  it("handles return lines (negative quantity) with flipped totals", () => {
    const line = computeLineTotals({ unitPrice: "10.00", quantity: -2, vatRate: "19.00" });
    expect(line.lineTotal.toFixed(2)).toBe("-20.00");
  });
});

describe("money — receipt totals across mixed VAT rates", () => {
  it("sums line totals/VAT and subtracts receipt-level discount", () => {
    const lines = [
      // 10.00 @ 19% x 2 = 20.00 (VAT 3.19)
      computeLineTotals({ unitPrice: "10.00", quantity: 2, vatRate: "19.00" }),
      // 5.50 @ 9% x 3  = 16.50 (VAT 1.36)
      computeLineTotals({ unitPrice: "5.50", quantity: 3, vatRate: "9.00" }),
      // 100.00 @ 19% x 1 = 100.00 (VAT 15.97)
      computeLineTotals({ unitPrice: "100.00", quantity: 1, vatRate: "19.00" }),
    ];
    const totals = computeReceiptTotals(lines, "5.00");

    expect(totals.subtotal.toFixed(2)).toBe("136.50");
    expect(totals.vatTotal.toFixed(2)).toBe("20.52");
    expect(totals.discountTotal.toFixed(2)).toBe("5.00");
    expect(totals.total.toFixed(2)).toBe("131.50");
  });
});
