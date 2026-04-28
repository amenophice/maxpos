"use client";

import Decimal from "decimal.js";
import { Check, CreditCard, Landmark, Smartphone, Ticket, Wallet } from "lucide-react";
import { useTranslations } from "next-intl";
import { useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { useShallow } from "zustand/react/shallow";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { formatLei } from "@/lib/money";
import {
  applyQuickPayment,
  type PaymentMethod,
  type PosPayment,
} from "@/lib/payments";
import {
  enqueuePendingReceipt,
  submitCheckout,
  type CheckoutPayload,
} from "@/lib/sync/receipts-sync";
import { selectTotals, usePosStore } from "@/stores/pos-store";

const METHODS: Array<{ key: PaymentMethod; icon: React.ComponentType<{ className?: string }> }> = [
  { key: "cash", icon: Wallet },
  { key: "card", icon: CreditCard },
  { key: "voucher", icon: Ticket },
  { key: "modern", icon: Smartphone },
  { key: "transfer", icon: Landmark },
];

export function CheckoutDialog({
  open,
  onOpenChange,
  onAfterSuccess,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  onAfterSuccess: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        {/* Remount on every open → fresh state without an effect-based reset. */}
        {open && (
          <CheckoutForm
            onAfterSuccess={onAfterSuccess}
            onCancel={() => onOpenChange(false)}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

function CheckoutForm({
  onAfterSuccess,
  onCancel,
}: {
  onAfterSuccess: () => void;
  onCancel: () => void;
}) {
  const t = useTranslations("pos.checkoutDialog");
  const lines = usePosStore((s) => s.lines);
  const customer = usePosStore((s) => s.customer);
  const receiptDiscount = usePosStore((s) => s.receiptDiscount);
  const cashSessionId = usePosStore((s) => s.cashSessionId);
  const clientLocalId = usePosStore((s) => s.clientLocalId);
  const clearDraft = usePosStore((s) => s.clearDraft);
  const setStatus = usePosStore((s) => s.setStatus);
  // useShallow: selectTotals returns a fresh { subtotal, vatTotal, ... } each
  // call, so raw `usePosStore(selectTotals)` triggers "Maximum update depth".
  const totals = usePosStore(useShallow(selectTotals));
  const totalStr = totals.total;

  const [payments, setPayments] = useState<PosPayment[]>([]);
  const [activeMethod, setActiveMethod] = useState<PaymentMethod>("cash");
  const [amountInput, setAmountInput] = useState<string>(totalStr);
  /**
   * Running total of cash overpayment across the whole checkout. UI-only —
   * never sent to the backend (the receipt itself sees clean cash payments
   * capped at the remaining). Reset together with payments by parent dialog
   * remount.
   */
  const [changeDue, setChangeDue] = useState<Decimal>(() => new Decimal(0));
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<{
    number?: number;
    total: string;
    queued: boolean;
    changeDue: string;
  } | null>(null);
  const successTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const totalDec = useMemo(() => new Decimal(totalStr), [totalStr]);
  const paidDec = useMemo(
    () => payments.reduce((acc, p) => acc.add(new Decimal(p.amount || 0)), new Decimal(0)),
    [payments],
  );
  const remainingDec = totalDec.sub(paidDec);

  /**
   * Single entry point for every quick-button click and for the "Adaugă
   * plată parțială" custom-amount button. Caps the credited amount at the
   * remaining balance so we never overpay the receipt; if the user clicks a
   * 100-lei button against a 76.90 receipt, we credit 76.90 and accumulate
   * 23.10 onto `changeDue`.
   *
   * After applying, we autopopulate `amountInput` with the new remaining so
   * a subsequent method switch (Cash → Card) is one click + Enter away.
   */
  const applyPayment = (method: PaymentMethod, rawAmount: Decimal.Value) => {
    const result = applyQuickPayment({
      payments,
      total: totalStr,
      method,
      amount: rawAmount,
    });
    if (result.payments === payments) return;
    setPayments(result.payments);
    if (!result.changeDelta.isZero()) {
      setChangeDue((c) => c.add(result.changeDelta));
    }
    const nextRemaining = new Decimal(totalStr).sub(
      result.payments.reduce((a, p) => a.add(new Decimal(p.amount)), new Decimal(0)),
    );
    setAmountInput(nextRemaining.gt(0) ? nextRemaining.toFixed(2) : "");
  };

  const removePaymentAt = (index: number) => {
    // Removing a payment also clears the change-due running total: we can't
    // tell which payment contributed which slice of overpayment without
    // tracking it per-row, and "remove a payment then re-add" is exactly the
    // moment a stale change number would mislead the cashier.
    setPayments((arr) => arr.filter((_, idx) => idx !== index));
    setChangeDue(new Decimal(0));
    const nextRemaining = totalDec.sub(
      payments
        .filter((_, idx) => idx !== index)
        .reduce((a, p) => a.add(new Decimal(p.amount)), new Decimal(0)),
    );
    setAmountInput(nextRemaining.gt(0) ? nextRemaining.toFixed(2) : "");
  };

  const canSubmit = paidDec.eq(totalDec) && lines.length > 0 && !submitting;

  const finalize = async () => {
    if (!cashSessionId || !clientLocalId) return;
    setSubmitting(true);
    setStatus("submitting");

    const payload: CheckoutPayload = {
      cash_session_id: cashSessionId,
      customer_id: customer?.id ?? null,
      client_local_id: clientLocalId,
      receipt_discount: Number(receiptDiscount || 0),
      items: lines.map((l) => ({
        article_id: l.articleId,
        quantity: Number(l.quantity),
        gestiune_id: l.gestiuneId,
        discount_amount: Number(l.discountAmount || 0),
      })),
      payments: payments.map((p) => ({
        method: p.method,
        amount: Number(p.amount),
        reference: p.reference ?? null,
      })),
    };

    const result = await submitCheckout(payload);

    if (result.ok) {
      clearDraft();
      setSuccess({
        number: result.number,
        total: result.total,
        queued: false,
        changeDue: changeDue.toFixed(2),
      });
      successTimer.current = setTimeout(onAfterSuccess, 4000);
    } else if (result.retriable) {
      await enqueuePendingReceipt(payload);
      clearDraft();
      setSuccess({
        total: totalStr,
        queued: true,
        changeDue: changeDue.toFixed(2),
      });
      toast.success(t("queuedTitle"));
      successTimer.current = setTimeout(onAfterSuccess, 4000);
    } else {
      setStatus("drafting");
      toast.error(t("errorBusiness", { message: result.error }));
    }
    setSubmitting(false);
  };

  if (success) {
    const showChange = new Decimal(success.changeDue).gt(0);
    return (
      <div className="flex flex-col items-center text-center gap-3 py-4">
        <div className="h-14 w-14 rounded-full bg-primary/10 text-primary flex items-center justify-center">
          <Check className="h-8 w-8" />
        </div>
        <h2 className="font-serif text-xl font-bold">
          {success.queued ? t("queuedTitle") : t("successTitle")}
        </h2>
        <p className="text-sm text-muted-foreground">
          {success.queued
            ? t("queuedSubtitle")
            : t("successSubtitle", {
                number: success.number ?? "—",
                total: formatLei(success.total),
              })}
        </p>
        {showChange && (
          <p className="text-base font-semibold text-primary">
            {t("changeDue", { amount: formatLei(success.changeDue) })}
          </p>
        )}
        {!success.queued && (
          <p className="text-xs text-muted-foreground italic">{t("successPrint")}</p>
        )}
        <Button
          className="mt-2"
          onClick={() => {
            if (successTimer.current) clearTimeout(successTimer.current);
            onAfterSuccess();
          }}
        >
          {t("successNewReceipt")}
        </Button>
      </div>
    );
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-baseline justify-between gap-4">
          <span>{t("title")}</span>
          <span className="font-serif text-2xl font-bold text-primary tabular-nums">
            {formatLei(totalStr)}
          </span>
        </DialogTitle>
      </DialogHeader>

      <div className="space-y-4">
        <div>
          <div className="text-xs font-medium mb-2 text-muted-foreground">
            {t("chooseMethod")}
          </div>
          <div className="grid grid-cols-5 gap-2">
            {METHODS.map(({ key, icon: Icon }) => (
              <Button
                key={key}
                type="button"
                variant={activeMethod === key ? "default" : "outline"}
                className="h-auto flex-col py-2 px-1"
                onClick={() => setActiveMethod(key)}
              >
                <Icon className="h-5 w-5 mb-1" />
                <span className="text-[10px] leading-tight">{t(`method.${key}`)}</span>
              </Button>
            ))}
          </div>
        </div>

        {activeMethod === "cash" && (
          <div>
            <div className="text-xs text-muted-foreground mb-1 flex items-baseline justify-between">
              <span>{t("quickCash")}</span>
              <span className="text-[10px] italic">{t("quickCashHint")}</span>
            </div>
            <div className="flex gap-2 flex-wrap">
              <Button
                variant="secondary"
                size="sm"
                onClick={() => applyPayment("cash", remainingDec)}
                disabled={remainingDec.lte(0)}
              >
                Exact
              </Button>
              {[50, 100, 200, 500].map((n) => (
                <Button
                  key={n}
                  variant="secondary"
                  size="sm"
                  onClick={() => applyPayment("cash", n)}
                  disabled={remainingDec.lte(0)}
                >
                  {n} lei
                </Button>
              ))}
            </div>
          </div>
        )}

        {activeMethod !== "cash" && (
          <div>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => applyPayment(activeMethod, remainingDec)}
              disabled={remainingDec.lte(0)}
            >
              Exact ({formatLei(remainingDec.toFixed(2))})
            </Button>
          </div>
        )}

        <div className="grid grid-cols-[1fr_auto] gap-2 items-end">
          <div>
            <label className="text-xs font-medium block mb-1">
              {t("amount")}{" "}
              <span className="text-muted-foreground text-[10px] italic">
                — {t("addPaymentHint")}
              </span>
            </label>
            <Input
              type="number"
              step="0.01"
              min="0"
              value={amountInput}
              onChange={(e) => setAmountInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  applyPayment(activeMethod, amountInput || 0);
                }
              }}
            />
          </div>
          <Button
            variant="outline"
            onClick={() => applyPayment(activeMethod, amountInput || 0)}
            disabled={remainingDec.lte(0)}
          >
            {t("addPayment")}
          </Button>
        </div>

        {payments.length > 0 && (
          <ul className="border rounded-md divide-y divide-border text-sm">
            {payments.map((p, i) => (
              <li key={i} className="flex justify-between px-3 py-2">
                <span>{t(`method.${p.method}`)}</span>
                <div className="flex items-center gap-2">
                  <span className="tabular-nums">{formatLei(p.amount)}</span>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={() => removePaymentAt(i)}
                  >
                    ×
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}

        <div className="grid grid-cols-2 gap-2 text-sm">
          <SummaryCell label={t("paid")} value={formatLei(paidDec.toFixed(2))} />
          {remainingDec.gt(0) ? (
            <SummaryCell
              label={t("remaining")}
              value={formatLei(remainingDec.toFixed(2))}
              accent
            />
          ) : (
            <SummaryCell
              label={t("change")}
              value={formatLei(remainingDec.abs().toFixed(2))}
            />
          )}
        </div>

        {changeDue.gt(0) && (
          <div className="rounded-md border border-primary/40 bg-primary/10 px-3 py-2 text-primary font-semibold tabular-nums">
            {t("changeDue", { amount: formatLei(changeDue.toFixed(2)) })}
          </div>
        )}

        <div className="flex gap-2">
          <Button variant="ghost" onClick={onCancel} className="flex-1">
            Anulează
          </Button>
          <Button className="flex-1 h-12 text-base" disabled={!canSubmit} onClick={finalize}>
            {submitting ? t("submitting") : t("finalize")}
          </Button>
        </div>
      </div>
    </>
  );
}

function SummaryCell({
  label,
  value,
  accent,
}: {
  label: string;
  value: string;
  accent?: boolean;
}) {
  return (
    <div
      className={`rounded-md border p-2 ${accent ? "border-accent bg-accent/10" : "border-border"}`}
    >
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="font-semibold tabular-nums">{value}</div>
    </div>
  );
}
