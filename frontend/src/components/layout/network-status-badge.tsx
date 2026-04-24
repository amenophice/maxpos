"use client";

import { Wifi, WifiOff } from "lucide-react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/badge";
import { useHasMounted } from "@/hooks/use-has-mounted";
import { useOnlineStore } from "@/stores/online-store";

/**
 * Network status badge.
 *
 * The Zustand store reads `navigator.onLine` which is only meaningful on the
 * client — the server always defaults to `true`. Rendering the real value
 * during SSR risks a hydration mismatch whenever the user loads the app
 * offline (server says Online, client hydrates with Offline).
 *
 * Option A (useHasMounted pattern): during the first render we emit a stable
 * neutral skeleton-shaped placeholder that matches on server and client; once
 * the component has committed on the client we swap in the real state. This
 * keeps the badge SSR-able and prevents the hydration warning + the infinite
 * refresh loop that follows it.
 */
export function NetworkStatusBadge() {
  const t = useTranslations("common");
  const hasMounted = useHasMounted();
  const isOnline = useOnlineStore((s) => s.isOnline);

  if (!hasMounted) {
    return (
      <Badge
        variant="outline"
        className="hidden sm:inline-flex gap-1 invisible"
        aria-hidden
      >
        <Wifi className="h-3 w-3" />
        <span>{t("online")}</span>
      </Badge>
    );
  }

  return (
    <Badge
      variant={isOnline ? "secondary" : "destructive"}
      className="hidden sm:inline-flex gap-1"
      aria-live="polite"
    >
      {isOnline ? <Wifi className="h-3 w-3" /> : <WifiOff className="h-3 w-3" />}
      <span>{isOnline ? t("online") : t("offline")}</span>
    </Badge>
  );
}
