import Decimal from "decimal.js";

export interface ParsedScaleBarcode {
  plu: string;
  /** Weight in kg as a string, up to 3 decimal places (gram precision). */
  weightKg: string;
}

/**
 * Romanian-store scale-printed EAN-13 layout:
 *
 *   PP PPPPP WWWWW C
 *
 * positions 1-2  → 2-digit prefix (configurable, default 26-29)
 * positions 3-7  → 5-digit PLU (matches `articles.plu`)
 * positions 8-12 → 5-digit weight in grams (00000-99999, so 0.000-99.999 kg)
 * position 13    → standard EAN-13 check digit
 *
 * Returns `null` for any non-conforming input (wrong length, non-numeric,
 * unrecognised prefix, or invalid checksum) so the caller can fall through
 * to a normal full-barcode lookup.
 */
export function parseScaleBarcode(
  barcode: string,
  allowedPrefixes: readonly string[],
): ParsedScaleBarcode | null {
  const code = barcode.trim();
  if (!/^\d{13}$/.test(code)) return null;
  if (!allowedPrefixes.includes(code.slice(0, 2))) return null;
  if (!eanCheckDigitOk(code)) return null;

  const plu = code.slice(2, 7);
  const grams = parseInt(code.slice(7, 12), 10);
  const weightKg = new Decimal(grams).div(1000).toFixed(3);

  return { plu, weightKg };
}

function eanCheckDigitOk(code13: string): boolean {
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    const d = code13.charCodeAt(i) - 48;
    sum += i % 2 === 0 ? d : d * 3;
  }
  const expected = (10 - (sum % 10)) % 10;
  return expected === code13.charCodeAt(12) - 48;
}

/**
 * Build a valid scale EAN-13 (used by tests + the seeder helper). The 12
 * leading digits come from prefix + plu + grams; we compute the check digit.
 */
export function buildScaleBarcode(prefix: string, plu: string, grams: number): string {
  const body = `${prefix.padStart(2, "0")}${plu.padStart(5, "0")}${String(grams).padStart(5, "0")}`;
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    const d = body.charCodeAt(i) - 48;
    sum += i % 2 === 0 ? d : d * 3;
  }
  const check = (10 - (sum % 10)) % 10;
  return body + String(check);
}
