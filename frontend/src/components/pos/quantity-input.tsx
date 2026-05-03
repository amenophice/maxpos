"use client";

import { Calculator, Minus, Plus } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { isWeightUnit, normalizeQuantityInput } from "@/lib/units";
import { usePosStore } from "@/stores/pos-store";
import { NumpadDialog } from "./numpad-dialog";

interface QuantityInputProps {
  /** Receipt line id — used to receive the auto-focus signal from the store. */
  lineId: string;
  value: string;
  unit: string;
  ariaLabel?: string;
  /** Notified with a normalized string ("0.85", "1.250", "5"). */
  onChange: (next: string) => void;
  /** Called after a confirm (Enter / Tab / Blur). Used to bounce focus back to search. */
  onAfterCommit?: () => void;
}

/**
 * Receipt-line quantity editor.
 *
 *   - **Weight units** (`kg`, `l`, …): clean text input (no spinner) with
 *     `inputMode="decimal"`. Auto-focuses + selects when the line is freshly
 *     added (driven by `posStore.focusLineId`). Enter / Tab / Blur commit;
 *     Escape restores. Comma and dot are interchangeable. A small calculator
 *     icon to the right opens the on-screen numpad for touchscreen users.
 *
 *   - **Counted units** (`buc`, `pachet`, …): integer-only with the −/+
 *     stepper preserved (cashier still wants to bump 3 of these in two
 *     clicks). Numpad button stays as a secondary affordance.
 */
export function QuantityInput(props: QuantityInputProps) {
  return isWeightUnit(props.unit) ? (
    <WeightQuantityInput {...props} />
  ) : (
    <CountedQuantityInput {...props} />
  );
}

function WeightQuantityInput({
  lineId,
  value,
  unit,
  ariaLabel,
  onChange,
  onAfterCommit,
}: QuantityInputProps) {
  const focusLineId = usePosStore((s) => s.focusLineId);
  const consumeFocusLineId = usePosStore((s) => s.consumeFocusLineId);

  const inputRef = useRef<HTMLInputElement>(null);
  const initialRef = useRef(value);
  const cancelledRef = useRef(false);
  const [draft, setDraft] = useState(value);
  const [numpadOpen, setNumpadOpen] = useState(false);

  // Keep local draft in sync with the canonical store value when the line
  // is re-rendered for any reason other than the user typing (commit, scale
  // barcode, programmatic update). The React 19 rule
  // `react-hooks/set-state-in-effect` would prefer derived state, but here
  // we genuinely need a *cancellable* controlled input — Escape must restore
  // the pre-edit value, which means the draft has to live in state and the
  // prop is its authoritative source.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setDraft(value);
    initialRef.current = value;
  }, [value]);

  // Pull the auto-focus signal from the store on mount: if this line was the
  // one freshly added with intent-to-edit-weight, focus + select all so the
  // first keystroke replaces "1.000".
  useEffect(() => {
    if (focusLineId === lineId && inputRef.current) {
      inputRef.current.focus();
      inputRef.current.select();
      consumeFocusLineId();
    }
  }, [focusLineId, lineId, consumeFocusLineId]);

  const commit = () => {
    if (cancelledRef.current) {
      cancelledRef.current = false;
      return;
    }
    const cleaned = normalizeQuantityInput(draft);
    if (cleaned !== "" && cleaned !== ".") {
      if (cleaned !== initialRef.current) onChange(cleaned);
    } else {
      setDraft(initialRef.current);
    }
    onAfterCommit?.();
  };

  const cancel = () => {
    cancelledRef.current = true;
    setDraft(initialRef.current);
    inputRef.current?.blur();
  };

  return (
    <div className="flex items-center gap-1">
      <Input
        ref={inputRef}
        type="text"
        inputMode="decimal"
        aria-label={ariaLabel ?? "Cantitate"}
        className="h-7 w-24 text-sm text-center tabular-nums"
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        onFocus={(e) => e.currentTarget.select()}
        onKeyDown={(e) => {
          if (e.key === "Enter" || e.key === "Tab") {
            e.preventDefault();
            inputRef.current?.blur();
          } else if (e.key === "Escape") {
            e.preventDefault();
            cancel();
          }
        }}
        onBlur={commit}
      />
      <span className="text-xs text-muted-foreground min-w-[2ch]">{unit}</span>
      <Button
        variant="ghost"
        size="icon"
        className="h-7 w-7"
        aria-label="Tastatură numerică"
        onClick={() => setNumpadOpen(true)}
      >
        <Calculator className="h-3.5 w-3.5" />
      </Button>

      <NumpadDialog
        open={numpadOpen}
        onOpenChange={setNumpadOpen}
        initialValue={value}
        unit={unit}
        decimals={3}
        title={`Cantitate (${unit})`}
        onConfirm={(v) => {
          onChange(v);
          onAfterCommit?.();
        }}
      />
    </div>
  );
}

function CountedQuantityInput({
  value,
  unit,
  ariaLabel,
  onChange,
}: QuantityInputProps) {
  const numericValue = Number(value) || 0;
  const [numpadOpen, setNumpadOpen] = useState(false);

  const apply = (raw: string) => {
    const cleaned = normalizeQuantityInput(raw);
    if (cleaned === "") {
      onChange("0");
      return;
    }
    if (cleaned.includes(".")) {
      onChange(String(parseInt(cleaned, 10)));
      return;
    }
    onChange(cleaned);
  };

  return (
    <div className="flex items-center gap-1">
      <Button
        variant="outline"
        size="icon"
        className="h-7 w-7"
        aria-label="−"
        onClick={() => onChange(String(Math.max(0, numericValue - 1)))}
      >
        <Minus className="h-3 w-3" />
      </Button>
      <Input
        type="text"
        inputMode="numeric"
        aria-label={ariaLabel ?? "Cantitate"}
        className="h-7 w-20 text-sm text-center tabular-nums"
        value={value}
        onChange={(e) => apply(e.target.value)}
      />
      <span className="text-xs text-muted-foreground min-w-[2ch]">{unit}</span>
      <Button
        variant="outline"
        size="icon"
        className="h-7 w-7"
        aria-label="+"
        onClick={() => onChange(String(numericValue + 1))}
      >
        <Plus className="h-3 w-3" />
      </Button>
      <Button
        variant="ghost"
        size="icon"
        className="h-7 w-7"
        aria-label="Tastatură numerică"
        onClick={() => setNumpadOpen(true)}
      >
        <Calculator className="h-3.5 w-3.5" />
      </Button>

      <NumpadDialog
        open={numpadOpen}
        onOpenChange={setNumpadOpen}
        initialValue={value}
        unit={unit}
        decimals={0}
        title={`Cantitate (${unit})`}
        onConfirm={(v) => onChange(v)}
      />
    </div>
  );
}
