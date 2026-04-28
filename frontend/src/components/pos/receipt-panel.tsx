"use client";

import Decimal from "decimal.js";
import { Percent, Trash2, UserPlus, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useShallow } from "zustand/react/shallow";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { formatLei } from "@/lib/money";
import { selectTotals, usePosStore } from "@/stores/pos-store";
import { QuantityInput } from "./quantity-input";

export function ReceiptPanel({
  onOpenCheckout,
  onOpenCustomer,
  onOpenDiscount,
  onLineCommit,
  checkoutDisabled = false,
  checkoutDisabledReason,
}: {
  onOpenCheckout: () => void;
  onOpenCustomer: () => void;
  onOpenDiscount: () => void;
  /** Fired after a quantity edit on a line is committed (Enter/Tab/blur). */
  onLineCommit?: () => void;
  checkoutDisabled?: boolean;
  checkoutDisabledReason?: string;
}) {
  const t = useTranslations("pos");
  const lines = usePosStore((s) => s.lines);
  const customer = usePosStore((s) => s.customer);
  const updateQty = usePosStore((s) => s.updateItemQuantity);
  const removeItem = usePosStore((s) => s.removeItem);
  const setCustomer = usePosStore((s) => s.setCustomer);
  // useShallow required — selectTotals returns a fresh object per call.
  // Values are strings, so shallow compare can bail out cleanly between renders.
  const totals = usePosStore(useShallow(selectTotals));

  const empty = lines.length === 0;

  return (
    <Card className="flex flex-col max-h-[calc(100vh-8rem)]">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">{t("receiptTitle")}</CardTitle>
          {customer ? (
            <div className="flex items-center gap-1 text-xs">
              <span className="text-muted-foreground truncate max-w-[10rem]">
                {t("customerAttached", { name: customer.name })}
              </span>
              <Button
                variant="ghost"
                size="icon"
                className="h-6 w-6"
                aria-label={t("removeCustomer")}
                onClick={() => setCustomer(null)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>
          ) : (
            <Button variant="ghost" size="sm" className="h-7 gap-1" onClick={onOpenCustomer}>
              <UserPlus className="h-3 w-3" />
              {t("attachCustomer")}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-3 flex-1 min-h-0">
        {empty ? (
          <div className="flex-1 flex items-center justify-center text-center text-sm text-muted-foreground p-4">
            <div>
              <div className="font-medium mb-1">{t("emptyReceiptTitle")}</div>
              <div>{t("emptyReceiptBody")}</div>
            </div>
          </div>
        ) : (
          <ul className="flex-1 overflow-y-auto divide-y divide-border -mx-2">
            {lines.map((line) => {
              // Compute via Decimal so weighed lines (0.850 kg × 11.40) match
              // the backend's bcmath rounding instead of dropping trailing
              // ULPs through Number coercion.
              const lineTotal = new Decimal(line.unitPrice)
                .mul(line.quantity)
                .sub(line.discountAmount)
                .toFixed(2);
              return (
                <li key={line.localId} className="px-2 py-2">
                  <div className="flex justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium truncate">{line.name}</div>
                      <div className="text-xs text-muted-foreground">
                        {formatLei(line.unitPrice)} / {line.unit}
                      </div>
                    </div>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      aria-label={t("lineRemoveAria")}
                      onClick={() => removeItem(line.localId)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                  <div className="flex items-center justify-between mt-2 gap-2">
                    <QuantityInput
                      lineId={line.localId}
                      value={line.quantity}
                      unit={line.unit}
                      ariaLabel={t("lineQtyAria")}
                      onChange={(next) => updateQty(line.localId, next)}
                      onAfterCommit={onLineCommit}
                    />
                    <div className="text-sm font-semibold tabular-nums">
                      {formatLei(lineTotal)}
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}

        <Separator />

        <div className="space-y-1.5 text-sm">
          <Row
            label={t("subtotal")}
            value={formatLei(new Decimal(totals.subtotal).sub(totals.vatTotal))}
          />
          <Row label={t("vat")} value={formatLei(totals.vatTotal)} />
          <div className="flex justify-between items-center">
            <Button variant="ghost" size="sm" className="-ml-2 h-7" onClick={onOpenDiscount}>
              <Percent className="h-3 w-3 mr-1" />
              {t("discount")}
            </Button>
            <span className="tabular-nums">{formatLei(totals.discountTotal)}</span>
          </div>
          <Separator />
          <div className="flex justify-between items-baseline">
            <span className="text-sm font-medium">{t("total")}</span>
            <span className="font-serif text-2xl font-bold text-primary tabular-nums">
              {formatLei(totals.total)}
            </span>
          </div>
        </div>

        <Button
          size="lg"
          className="h-12 text-base mt-2"
          disabled={empty || checkoutDisabled}
          onClick={onOpenCheckout}
          title={checkoutDisabled ? checkoutDisabledReason : undefined}
        >
          {t("checkout")}
        </Button>
      </CardContent>
    </Card>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="tabular-nums">{value}</span>
    </div>
  );
}
