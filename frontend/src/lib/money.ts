import Decimal from "decimal.js";

/*
 * Money math — must stay identical to backend's `App\Services\ReceiptService`
 * (PHP bcmath, scale 2 for money / 3 for qty, inclusive VAT).
 *
 * Formula (per line, inclusive VAT):
 *   gross          = unit_price * quantity
 *   line_subtotal  = gross - discount          (gross-incl-VAT after discount)
 *   line_vat       = round_half_away_from_zero( line_subtotal * vat_rate / (100 + vat_rate), 2 )
 *   line_net       = line_subtotal - line_vat
 *   line_total     = line_subtotal             (displayed gross for the line)
 *
 * Receipt totals:
 *   receipt.subtotal = Σ line_net   (stored as "subtotal" in backend, but is net)
 *   receipt.vat      = Σ line_vat
 *   receipt.total    = receipt.subtotal + receipt.vat - receipt.discount_total
 *
 * Rounding: half-away-from-zero at the cent. Implemented via Decimal.ROUND_HALF_UP
 * for non-negative values; negative values (return lines) flip the sign, round,
 * flip back — matching PHP bcadd(abs, 0.005) then truncate.
 */

Decimal.set({ rounding: Decimal.ROUND_HALF_UP });

export const MONEY_SCALE = 2;
export const QTY_SCALE = 3;

export function money(input: Decimal.Value): Decimal {
  return new Decimal(input);
}

export function roundMoney(value: Decimal.Value): Decimal {
  const d = new Decimal(value);
  // Half-away-from-zero at 2dp.
  const sign = d.isNegative() ? -1 : 1;
  const abs = d.abs().toDecimalPlaces(MONEY_SCALE, Decimal.ROUND_HALF_UP);
  return abs.mul(sign);
}

export interface LineInput {
  unitPrice: Decimal.Value;
  quantity: Decimal.Value;
  vatRate: Decimal.Value;
  discountAmount?: Decimal.Value;
}

export interface LineTotals {
  lineSubtotal: Decimal; // net after VAT extraction
  lineVat: Decimal;
  lineTotal: Decimal; // gross-incl-VAT (what the customer pays for the line)
}

export function computeLineTotals({
  unitPrice,
  quantity,
  vatRate,
  discountAmount = 0,
}: LineInput): LineTotals {
  const up = new Decimal(unitPrice);
  const qty = new Decimal(quantity);
  const rate = new Decimal(vatRate);
  const disc = new Decimal(discountAmount);

  const gross = up.mul(qty);
  const lineTotal = gross.sub(disc);
  const lineVat = roundMoney(lineTotal.mul(rate).div(new Decimal(100).add(rate)));
  const lineSubtotal = lineTotal.sub(lineVat);

  return {
    lineSubtotal: roundMoney(lineSubtotal),
    lineVat,
    lineTotal: roundMoney(lineTotal),
  };
}

export interface ReceiptTotals {
  subtotal: Decimal;
  vatTotal: Decimal;
  discountTotal: Decimal;
  total: Decimal;
}

export function computeReceiptTotals(
  lines: LineTotals[],
  receiptDiscount: Decimal.Value = 0,
): ReceiptTotals {
  const subtotal = lines.reduce((acc, l) => acc.add(l.lineTotal), new Decimal(0));
  const vatTotal = lines.reduce((acc, l) => acc.add(l.lineVat), new Decimal(0));
  const discountTotal = new Decimal(receiptDiscount);
  const total = subtotal.sub(discountTotal);

  return {
    subtotal: roundMoney(subtotal),
    vatTotal: roundMoney(vatTotal),
    discountTotal: roundMoney(discountTotal),
    total: roundMoney(total),
  };
}

/** Format a Decimal or numeric string as "12.34 lei" (Romanian locale). */
export function formatLei(value: Decimal.Value): string {
  return `${new Decimal(value).toFixed(MONEY_SCALE)} lei`;
}
