"use client";

import { useLiveQuery } from "dexie-react-hooks";
import { Check, RefreshCw, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { useHasMounted } from "@/hooks/use-has-mounted";
import { db } from "@/lib/db";
import { bootstrapSync } from "@/lib/sync/bootstrap-sync";
import { useOnlineStore } from "@/stores/online-store";

type Status = "idle" | "loading" | "success" | "error";

/**
 * Manual catalog-sync trigger. Lives in the topbar so the cashier can refresh
 * the IndexedDB catalog on demand. Auto-sync is intentionally disabled — the
 * only automatic call is a first-time download when the local table is empty
 * (handled by `PosScreen`'s mount effect). Receipts queue (write-side) is
 * unrelated and continues to auto-drain on `online` events.
 */
export function CatalogSyncButton() {
  const t = useTranslations("pos.syncButton");
  const tCommon = useTranslations("pos");
  const mounted = useHasMounted();
  const isOnline = useOnlineStore((s) => s.isOnline);
  const [status, setStatus] = useState<Status>("idle");

  const lastSyncAt = useLiveQuery(
    async () =>
      db ? ((await db.sync_meta.get("bootstrap.last_sync_at"))?.value as string | undefined) : undefined,
    [],
  );

  const lastSyncLabel = formatLastSyncLabel(lastSyncAt ?? null, t);

  const onClick = async () => {
    if (status === "loading") return;
    setStatus("loading");
    try {
      const result = await bootstrapSync();
      setStatus("success");
      toast.success(
        tCommon("syncedToastDetail", {
          articles: result.articles,
          groups: result.groups,
        }),
      );
      window.setTimeout(() => setStatus("idle"), 2000);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "unknown";
      setStatus("error");
      toast.error(tCommon("syncFailedToast") + " (" + msg + ")");
      window.setTimeout(() => setStatus("idle"), 4000);
    }
  };

  if (!mounted) {
    // Stable invisible placeholder for SSR — same as the network badge pattern.
    return (
      <Button
        variant="ghost"
        size="icon"
        className="hidden md:inline-flex h-9 w-9 invisible"
        aria-hidden
      >
        <RefreshCw className="h-4 w-4" />
      </Button>
    );
  }

  const tooltip =
    !isOnline
      ? t("tooltipOffline")
      : status === "loading"
        ? t("tooltipLoading")
        : status === "success"
          ? t("tooltipSuccess")
          : status === "error"
            ? t("tooltipError")
            : `${t("tooltipIdle")} · ${lastSyncLabel}`;

  return (
    <Button
      variant="ghost"
      size="icon"
      className="hidden md:inline-flex h-9 w-9"
      onClick={onClick}
      disabled={!isOnline || status === "loading"}
      title={tooltip}
      aria-label={tooltip}
    >
      {status === "success" ? (
        <Check className="h-4 w-4 text-primary" />
      ) : status === "error" ? (
        <X className="h-4 w-4 text-destructive" />
      ) : (
        <RefreshCw className={status === "loading" ? "h-4 w-4 animate-spin" : "h-4 w-4"} />
      )}
    </Button>
  );
}

function formatLastSyncLabel(
  iso: string | null,
  t: (k: string, v?: Record<string, string | number>) => string,
): string {
  if (!iso) return t("lastSyncNever");
  const then = new Date(iso).getTime();
  const minutes = Math.round((Date.now() - then) / 60000);
  if (minutes < 1) return t("lastSyncJustNow");
  if (minutes < 60) return t("lastSyncMinutes", { minutes });
  const d = new Date(iso);
  const hh = String(d.getHours()).padStart(2, "0");
  const mm = String(d.getMinutes()).padStart(2, "0");
  return t("lastSyncAt", { time: `${hh}:${mm}` });
}
