import { describe, expect, it } from "vitest";
import { buildScaleBarcode, parseScaleBarcode } from "@/lib/scale-barcode";

const PREFIXES = ["26", "27", "28", "29"];

describe("parseScaleBarcode", () => {
  it("decodes a valid 28-prefix code with PLU 12345 and 1.234 kg", () => {
    const code = buildScaleBarcode("28", "12345", 1234);
    const result = parseScaleBarcode(code, PREFIXES);
    expect(result).not.toBeNull();
    expect(result?.plu).toBe("12345");
    expect(result?.weightKg).toBe("1.234");
  });

  it("returns null for an invalid checksum", () => {
    const valid = buildScaleBarcode("28", "12345", 1234);
    // Flip the last digit.
    const broken = valid.slice(0, 12) + ((Number(valid.slice(12)) + 1) % 10);
    expect(parseScaleBarcode(broken, PREFIXES)).toBeNull();
  });

  it("returns null when the prefix is not in the allowed list", () => {
    const code = buildScaleBarcode("99", "12345", 1234);
    expect(parseScaleBarcode(code, PREFIXES)).toBeNull();
  });

  it("returns null for non-13-digit input", () => {
    expect(parseScaleBarcode("123456789012", PREFIXES)).toBeNull();
    expect(parseScaleBarcode("594000000017X", PREFIXES)).toBeNull();
    expect(parseScaleBarcode("", PREFIXES)).toBeNull();
  });

  it("encodes 0.850 kg correctly (850 g) — the demo Brânză case", () => {
    const code = buildScaleBarcode("28", "10001", 850);
    const result = parseScaleBarcode(code, PREFIXES);
    expect(result?.weightKg).toBe("0.850");
    expect(result?.plu).toBe("10001");
  });
});
