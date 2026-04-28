"use client";

import { ChevronDown } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Button } from "@/components/ui/button";

export function ShortcutsHint() {
  const t = useTranslations("pos");
  const [open, setOpen] = useState(false);
  const rows: Array<[string, string]> = [
    ["/", t("shortcutsSearch")],
    ["F9", t("shortcutsCheckout")],
    ["F1", t("shortcutsDiscount")],
    ["F2", t("shortcutsCustomer")],
    ["Esc", t("shortcutsEsc")],
  ];

  return (
    <div className="mt-3">
      <Button
        variant="ghost"
        size="sm"
        className="text-xs text-muted-foreground w-full justify-between"
        onClick={() => setOpen((v) => !v)}
      >
        {t("shortcutsTitle")}
        <ChevronDown className={`h-3 w-3 transition-transform ${open ? "rotate-180" : ""}`} />
      </Button>
      {open && (
        <ul className="mt-1 px-3 py-2 text-xs space-y-1 text-muted-foreground">
          {rows.map(([k, label]) => (
            <li key={k} className="flex justify-between gap-2">
              <kbd className="font-mono px-1 py-0.5 rounded bg-muted text-foreground">{k}</kbd>
              <span>{label}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
