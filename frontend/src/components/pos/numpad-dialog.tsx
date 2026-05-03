"use client";

import { Delete } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { normalizeQuantityInput } from "@/lib/units";

interface NumpadDialogProps {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  initialValue?: string;
  unit?: string;
  title?: string;
  /** Maximum decimal places the user may type. 0 → integer-only. */
  decimals?: number;
  onConfirm: (value: string) => void;
}

export function NumpadDialog(props: NumpadDialogProps) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-w-xs">
        {props.open && (
          <NumpadBody
            {...props}
            onClose={() => props.onOpenChange(false)}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

function NumpadBody({
  initialValue = "",
  unit,
  title = "Cantitate",
  decimals = 3,
  onConfirm,
  onClose,
}: NumpadDialogProps & { onClose: () => void }) {
  const [value, setValue] = useState(normalizeQuantityInput(initialValue));

  const append = (ch: string) => {
    setValue((cur) => {
      const next = cur + ch;
      if (!isLegalPartial(next, decimals)) return cur;
      return next;
    });
  };

  const backspace = () => setValue((cur) => cur.slice(0, -1));

  const confirm = () => {
    const cleaned = value.replace(/^\.+/, "0.");
    if (!cleaned || cleaned === ".") {
      onClose();
      return;
    }
    onConfirm(cleaned);
    onClose();
  };

  const keys: Array<{ label: string; on: () => void } | null> = [
    { label: "1", on: () => append("1") },
    { label: "2", on: () => append("2") },
    { label: "3", on: () => append("3") },
    { label: "4", on: () => append("4") },
    { label: "5", on: () => append("5") },
    { label: "6", on: () => append("6") },
    { label: "7", on: () => append("7") },
    { label: "8", on: () => append("8") },
    { label: "9", on: () => append("9") },
    decimals > 0 ? { label: ",", on: () => append(".") } : null,
    { label: "0", on: () => append("0") },
    { label: "⌫", on: backspace },
  ];

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
      </DialogHeader>
      <div className="space-y-3">
        <div className="rounded-md border border-input px-3 py-2 text-2xl font-semibold tabular-nums text-right h-12 flex items-center justify-end gap-2">
          <span>{value || "0"}</span>
          {unit && <span className="text-sm text-muted-foreground">{unit}</span>}
        </div>
        <div className="grid grid-cols-3 gap-2">
          {keys.map((k, i) =>
            k ? (
              <Button
                key={i}
                variant="outline"
                className="h-12 text-lg"
                onClick={k.on}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    confirm();
                  }
                }}
              >
                {k.label === "⌫" ? <Delete className="h-4 w-4" /> : k.label}
              </Button>
            ) : (
              <span key={i} />
            ),
          )}
        </div>
        <div className="flex gap-2">
          <Button variant="ghost" className="flex-1" onClick={onClose}>
            Anulează
          </Button>
          <Button className="flex-1 h-11" onClick={confirm}>
            OK
          </Button>
        </div>
      </div>
    </>
  );
}

/** Reject mid-typing inputs that already exceed the allowed decimal places. */
function isLegalPartial(s: string, decimals: number): boolean {
  if (s.length > 12) return false;
  const dot = s.indexOf(".");
  if (dot === -1) return true;
  if (decimals === 0) return false;
  if (s.indexOf(".", dot + 1) !== -1) return false; // two dots
  return s.length - dot - 1 <= decimals;
}
