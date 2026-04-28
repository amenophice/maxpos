import Decimal from "decimal.js";

export type PaymentMethod = "cash" | "card" | "voucher" | "modern" | "transfer";

export interface PosPayment {
  method: PaymentMethod;
  amount: string; // 2-dp string, matches backend wire format
  reference?: string;
}

/**
 * Sum the `amount` fields of a list of payments. Returns a Decimal so callers
 * can compare without per-call `new Decimal(...)` ceremony.
 */
export function sumPayments(payments: PosPayment[]): Decimal {
  return payments.reduce((acc, p) => acc.add(new Decimal(p.amount || 0)), new Decimal(0));
}

export function remainingForReceipt(
  total: Decimal.Value,
  payments: PosPayment[],
): Decimal {
  return new Decimal(total).sub(sumPayments(payments));
}

export interface ApplyQuickPaymentResult {
  payments: PosPayment[];
  /**
   * Extra cash beyond what the receipt needed. Only ever non-zero for the
   * `cash` method — over-clicking a card/voucher/transfer button is treated
   * as user error and capped silently. The cashier owes this back as change.
   */
  changeDelta: Decimal;
}

/**
 * Pure helper: add one quick-amount click to the payment list.
 *
 * Caps the credited amount at `remaining` so the receipt is never overpaid in
 * the receipt's books. For cash overpayment (the customer handed a 100-lei
 * banknote against a 76.90 receipt), the overflow is returned as
 * `changeDelta` so the UI can display "Rest de dat: 23.10 lei". The receipt
 * still records a clean 76.90 cash payment.
 *
 * Returns the same `payments` array (no clone) when nothing changed — useful
 * to avoid spurious re-renders.
 */
export function applyQuickPayment(args: {
  payments: PosPayment[];
  total: Decimal.Value;
  method: PaymentMethod;
  amount: Decimal.Value;
  reference?: string;
}): ApplyQuickPaymentResult {
  const totalDec = new Decimal(args.total);
  const remaining = totalDec.sub(sumPayments(args.payments));
  const requested = new Decimal(args.amount);

  if (remaining.lte(0) || requested.lte(0)) {
    return { payments: args.payments, changeDelta: new Decimal(0) };
  }

  const credited = Decimal.min(requested, remaining);
  const overpay = requested.sub(credited);
  const changeDelta = args.method === "cash" ? overpay : new Decimal(0);

  return {
    payments: [
      ...args.payments,
      {
        method: args.method,
        amount: credited.toFixed(2),
        ...(args.reference ? { reference: args.reference } : {}),
      },
    ],
    changeDelta,
  };
}
