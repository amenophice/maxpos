/**
 * Whether a unit string represents a *measured* quantity (decimal-friendly)
 * rather than a counted one. Drives the receipt-line UI's choice between an
 * inline +/- integer stepper and a decimal quantity input + numpad.
 */
export function isWeightUnit(unit: string | null | undefined): boolean {
  if (!unit) return false;
  return ["kg", "kg.", "g", "l", "litru", "litre", "ml", "m", "metru"].includes(
    unit.toLowerCase().trim(),
  );
}

/**
 * Normalize a user-typed quantity string for parsing/storage:
 *   "0,85"  → "0.85"
 *   "1.250" → "1.250"
 *   " 3 "   → "3"
 *   ""      → ""
 */
export function normalizeQuantityInput(raw: string): string {
  return raw.trim().replace(",", ".");
}
