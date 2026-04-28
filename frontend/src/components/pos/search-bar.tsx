"use client";

import { Search, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { forwardRef, useCallback } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { findArticleByBarcode, findArticleByPlu } from "@/lib/db";
import { parseScaleBarcode } from "@/lib/scale-barcode";
import { getScalePrefixes } from "@/lib/sync/bootstrap-sync";
import { usePosStore } from "@/stores/pos-store";

interface Props {
  value: string;
  onChange: (v: string) => void;
  onOpenCheckout: () => void;
}

export const SearchBar = forwardRef<HTMLInputElement, Props>(function SearchBar(
  { value, onChange, onOpenCheckout },
  ref,
) {
  const t = useTranslations("pos");
  const addItem = usePosStore((s) => s.addItem);

  const onKeyDown = useCallback(
    async (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key !== "Enter") return;
      const q = value.trim();
      if (!q) {
        onOpenCheckout();
        return;
      }

      // 1) 13-digit numeric: try the scale-barcode parser first. Falls
      //    through to a normal barcode lookup if prefix or checksum reject.
      if (/^\d{13}$/.test(q)) {
        const prefixes = await getScalePrefixes();
        const parsed = parseScaleBarcode(q, prefixes);
        if (parsed) {
          const article = await findArticleByPlu(parsed.plu);
          if (!article) {
            toast.error(t("scaleBarcodePluUnknown", { plu: parsed.plu }));
          } else if (article.unit !== "kg" && article.unit !== "l" && article.unit !== "litru") {
            // Misconfiguration safety net: PLU points to a counted-unit
            // article. Don't pretend the kg weight is a buc count.
            toast.error(t("scaleBarcodeWrongUnit", { name: article.name, unit: article.unit }));
          } else {
            // Scale ticket carries the final weight — don't ask the cashier
            // to confirm via the auto-focused input.
            addItem(article, parsed.weightKg, { intentEditWeight: false });
            onChange("");
          }
          return;
        }
      }

      // 2) Plain barcode (8+ digits): existing path.
      if (/^\d{8,}$/.test(q)) {
        const article = await findArticleByBarcode(q);
        if (article) {
          addItem(article, 1);
          onChange("");
        }
      }
    },
    [value, addItem, onChange, onOpenCheckout, t],
  );

  return (
    <div className="relative">
      <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
      <Input
        ref={ref}
        autoFocus
        type="search"
        placeholder={t("searchPlaceholder")}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={onKeyDown}
        className="pl-9 pr-10 h-11 text-base"
      />
      {value && (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="absolute right-1 top-1/2 -translate-y-1/2 h-8 w-8"
          aria-label={t("searchClearAria")}
          onClick={() => onChange("")}
        >
          <X className="h-4 w-4" />
        </Button>
      )}
    </div>
  );
});
