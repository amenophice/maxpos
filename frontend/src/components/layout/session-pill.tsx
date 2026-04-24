"use client";

import { Banknote } from "lucide-react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/badge";
import type { ActiveSession } from "@/lib/me";

/**
 * Presentational — no data fetching on the client. The active cash session
 * is resolved server-side in `(app)/layout.tsx` (via `fetchMe()`) and passed
 * down the tree. That's the only way to talk to the Laravel API with the
 * httpOnly session token attached; a client-side `/me` call here would ship
 * with no `Authorization` header and get a 401.
 */
export function SessionPill({ activeSession }: { activeSession: ActiveSession | null }) {
  const t = useTranslations("topbar");

  if (!activeSession) {
    return (
      <Badge variant="outline" className="hidden md:inline-flex gap-1">
        <Banknote className="h-3 w-3" />
        <span>{t("sessionClosed")}</span>
      </Badge>
    );
  }

  return (
    <Badge className="hidden md:inline-flex gap-1 bg-primary/10 text-primary border-primary/30">
      <Banknote className="h-3 w-3" />
      <span>
        {t("sessionOpen")} · {t("sessionAmount", { amount: activeSession.initial_cash })}
      </span>
    </Badge>
  );
}
