"use client";

import { ArrowRight } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { toast } from "sonner";
import { ArticleGrid } from "./article-grid";
import { ReceiptPanel } from "./receipt-panel";
import { SearchBar } from "./search-bar";
import { CheckoutDialog } from "./checkout-dialog";
import { CustomerDialog } from "./customer-dialog";
import { ReceiptDiscountDialog } from "./receipt-discount-dialog";
import { ShortcutsHint } from "./shortcuts-hint";
import { articlesCount } from "@/lib/db";
import { bootstrapSync } from "@/lib/sync/bootstrap-sync";
import { cn } from "@/lib/utils";
import { usePosStore } from "@/stores/pos-store";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function PosScreen({
  cashSessionId,
  cashSessionOpen,
}: {
  cashSessionId: string | null;
  cashSessionOpen: boolean;
}) {
  const t = useTranslations("pos");
  const [syncing, setSyncing] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [groupId, setGroupId] = useState<string | null>(null);
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [customerOpen, setCustomerOpen] = useState(false);
  const [discountOpen, setDiscountOpen] = useState(false);
  const searchRef = useRef<HTMLInputElement | null>(null);

  const setCashSessionId = usePosStore((s) => s.setCashSessionId);

  // Auto-sync ONLY on a truly empty IndexedDB (first ever visit). Every other
  // catalog refresh is now manual via the topbar's "Sincronizează" button —
  // we no longer hit /pos/bootstrap on every mount, focus, or interval.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const count = await articlesCount();
      if (count > 0) return;
      setSyncing(true);
      const loadingId = toast.loading(t("firstSyncToast"));
      try {
        const result = await bootstrapSync({ force: true });
        if (cancelled) return;
        toast.dismiss(loadingId);
        if (!result.fromCache && result.articles > 0) {
          toast.success(
            t("syncedToast", { count: result.articles + result.groups }),
          );
        }
      } catch {
        if (cancelled) return;
        toast.dismiss(loadingId);
        toast.error(t("syncFailedToast"));
      } finally {
        if (!cancelled) setSyncing(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [t]);

  useEffect(() => {
    setCashSessionId(cashSessionId);
  }, [cashSessionId, setCashSessionId]);

  // Global shortcuts.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const active = document.activeElement as HTMLElement | null;
      const inField = active?.tagName === "INPUT" || active?.tagName === "TEXTAREA";

      if (e.key === "/" && !inField) {
        e.preventDefault();
        searchRef.current?.focus();
        return;
      }
      if (e.key === "F3") {
        e.preventDefault();
        searchRef.current?.focus();
        return;
      }
      if (e.key === "F9" && !checkoutOpen) {
        e.preventDefault();
        setCheckoutOpen(true);
        return;
      }
      if (e.key === "F1") {
        e.preventDefault();
        setDiscountOpen(true);
        return;
      }
      if (e.key === "F2") {
        e.preventDefault();
        setCustomerOpen(true);
        return;
      }
      if (e.key === "Escape") {
        setCheckoutOpen(false);
        setCustomerOpen(false);
        setDiscountOpen(false);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [checkoutOpen]);

  return (
    <div className="flex flex-col h-full gap-4">
      {!cashSessionOpen && (
        // Soft banner — replaces a previous server-side redirect that bounced
        // users back to / when the cash session was closed. /vanzare always
        // renders now; the rest of the page below is greyed and the
        // Încasează button is disabled while the session is closed.
        <Card className="border-accent/40 bg-accent/5">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{t("noSessionBannerTitle")}</CardTitle>
            <CardDescription>{t("noSessionBannerBody")}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button variant="outline" size="sm" asChild>
              <Link href="/">
                {t("noSessionBannerCta")}
                <ArrowRight className="ml-2 h-3 w-3" />
              </Link>
            </Button>
          </CardContent>
        </Card>
      )}

      <div
        className={cn(
          "grid gap-4 lg:grid-cols-[1fr_22rem] xl:grid-cols-[1fr_26rem] h-full",
          !cashSessionOpen && "opacity-60",
        )}
      >
        <div className="flex flex-col gap-3 min-h-0">
          <SearchBar
            ref={searchRef}
            value={searchQuery}
            onChange={setSearchQuery}
            onOpenCheckout={() => setCheckoutOpen(true)}
          />
          <ArticleGrid
            search={searchQuery}
            groupId={groupId}
            onGroupChange={setGroupId}
            onAfterAdd={() => {
              setSearchQuery("");
              searchRef.current?.focus();
            }}
            syncing={syncing}
          />
        </div>

        <div className="lg:sticky lg:top-4 self-start w-full">
          <ReceiptPanel
            onOpenCheckout={() => setCheckoutOpen(true)}
            onOpenCustomer={() => setCustomerOpen(true)}
            onOpenDiscount={() => setDiscountOpen(true)}
            onLineCommit={() => searchRef.current?.focus()}
            checkoutDisabled={!cashSessionOpen}
            checkoutDisabledReason={t("noSessionTooltip")}
          />
          <ShortcutsHint />
        </div>
      </div>

      <CheckoutDialog
        open={checkoutOpen}
        onOpenChange={setCheckoutOpen}
        onAfterSuccess={() => {
          setCheckoutOpen(false);
          setSearchQuery("");
          searchRef.current?.focus();
        }}
      />
      <CustomerDialog open={customerOpen} onOpenChange={setCustomerOpen} />
      <ReceiptDiscountDialog open={discountOpen} onOpenChange={setDiscountOpen} />
    </div>
  );
}
