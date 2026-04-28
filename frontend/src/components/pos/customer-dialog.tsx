"use client";

import { useLiveQuery } from "dexie-react-hooks";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { db, getAllCustomers } from "@/lib/db";
import { usePosStore } from "@/stores/pos-store";

export function CustomerDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  const t = useTranslations("pos.customerDialog");
  const [q, setQ] = useState("");
  const setCustomer = usePosStore((s) => s.setCustomer);

  const customers = useLiveQuery(
    () => (db ? getAllCustomers() : Promise.resolve([])),
    [],
  );

  const filtered = (customers ?? []).filter((c) => {
    const needle = q.trim().toLowerCase();
    if (!needle) return true;
    return (
      c.name.toLowerCase().includes(needle) ||
      (c.cui ?? "").toLowerCase().includes(needle)
    );
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
        </DialogHeader>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            autoFocus
            className="pl-9"
            placeholder={t("searchPlaceholder")}
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </div>
        <div className="max-h-80 overflow-y-auto border rounded-md">
          <Button
            variant="ghost"
            className="w-full justify-start rounded-none"
            onClick={() => {
              setCustomer(null);
              onOpenChange(false);
            }}
          >
            {t("none")}
          </Button>
          {filtered.length === 0 ? (
            <div className="p-4 text-sm text-muted-foreground">{t("empty")}</div>
          ) : (
            filtered.map((c) => (
              <Button
                key={c.id}
                variant="ghost"
                className="w-full justify-between rounded-none"
                onClick={() => {
                  setCustomer(c);
                  onOpenChange(false);
                }}
              >
                <span className="font-medium truncate">{c.name}</span>
                <span className="text-xs text-muted-foreground truncate">
                  {c.cui ?? (c.is_company ? "firmă" : "persoană")}
                </span>
              </Button>
            ))
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
