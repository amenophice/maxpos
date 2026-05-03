"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { usePosStore } from "@/stores/pos-store";

export function ReceiptDiscountDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        {open && <DiscountForm onClose={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  );
}

// Remounted each time the dialog opens (Dialog unmounts children on close),
// so useState initializers see the latest store value without an effect.
function DiscountForm({ onClose }: { onClose: () => void }) {
  const t = useTranslations("pos.discountDialog");
  const current = usePosStore((s) => s.receiptDiscount);
  const apply = usePosStore((s) => s.applyReceiptDiscount);
  const [value, setValue] = useState(current);

  const submit = () => {
    apply(Number(value || 0));
    onClose();
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t("title")}</DialogTitle>
        <DialogDescription>{t("body")}</DialogDescription>
      </DialogHeader>
      <div className="space-y-2">
        <Label htmlFor="discount-amount">{t("field")}</Label>
        <Input
          id="discount-amount"
          type="number"
          step="0.01"
          min="0"
          autoFocus
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") submit();
          }}
        />
      </div>
      <DialogFooter>
        <Button onClick={submit}>{t("apply")}</Button>
      </DialogFooter>
    </>
  );
}
