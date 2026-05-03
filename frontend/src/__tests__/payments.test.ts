import Decimal from "decimal.js";
import { describe, expect, it } from "vitest";
import {
  applyQuickPayment,
  remainingForReceipt,
  sumPayments,
  type PosPayment,
} from "@/lib/payments";

describe("applyQuickPayment", () => {
  it("credits an exact-amount click and leaves no change", () => {
    const result = applyQuickPayment({
      payments: [],
      total: "76.90",
      method: "cash",
      amount: "76.90",
    });

    expect(result.payments).toEqual([{ method: "cash", amount: "76.90" }]);
    expect(result.changeDelta.toFixed(2)).toBe("0.00");
    expect(remainingForReceipt("76.90", result.payments).toFixed(2)).toBe("0.00");
  });

  it("caps a 100-lei cash button at the 76.90 remaining and reports 23.10 change", () => {
    const result = applyQuickPayment({
      payments: [],
      total: "76.90",
      method: "cash",
      amount: 100,
    });

    // The receipt sees a clean 76.90 cash payment, never 100.
    expect(result.payments).toEqual([{ method: "cash", amount: "76.90" }]);
    expect(result.changeDelta.toFixed(2)).toBe("23.10");
    expect(sumPayments(result.payments).toFixed(2)).toBe("76.90");
  });

  it("caps card overpayment without producing change-due (cash-only behaviour)", () => {
    const result = applyQuickPayment({
      payments: [],
      total: "30.00",
      method: "card",
      amount: 50,
    });

    expect(result.payments).toEqual([{ method: "card", amount: "30.00" }]);
    expect(result.changeDelta.toFixed(2)).toBe("0.00");
  });

  it("noops once the receipt is fully paid", () => {
    const start: PosPayment[] = [{ method: "cash", amount: "76.90" }];
    const result = applyQuickPayment({
      payments: start,
      total: "76.90",
      method: "cash",
      amount: 50,
    });

    expect(result.payments).toBe(start); // same reference, no churn
    expect(result.changeDelta.isZero()).toBe(true);
  });

  it("supports a split-payment flow: 50 cash + 26.90 card on a 76.90 receipt", () => {
    const total = "76.90";
    const step1 = applyQuickPayment({ payments: [], total, method: "cash", amount: 50 });

    expect(step1.payments).toEqual([{ method: "cash", amount: "50.00" }]);
    expect(step1.changeDelta.toFixed(2)).toBe("0.00");
    expect(remainingForReceipt(total, step1.payments).toFixed(2)).toBe("26.90");

    const step2 = applyQuickPayment({
      payments: step1.payments,
      total,
      method: "card",
      amount: new Decimal("26.90"),
    });

    expect(step2.payments).toEqual([
      { method: "cash", amount: "50.00" },
      { method: "card", amount: "26.90" },
    ]);
    expect(remainingForReceipt(total, step2.payments).toFixed(2)).toBe("0.00");
    expect(sumPayments(step2.payments).toFixed(2)).toBe("76.90");
  });

  it("caps a custom 'Adaugă plată parțială' over-typed cash amount the same way", () => {
    const total = "20.00";
    const step1 = applyQuickPayment({
      payments: [],
      total,
      method: "cash",
      amount: 50, // user typed 50 by hand into the Sumă field
    });

    expect(step1.payments).toEqual([{ method: "cash", amount: "20.00" }]);
    expect(step1.changeDelta.toFixed(2)).toBe("30.00");
  });
});
