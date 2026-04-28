"use client";

import { useLiveQuery } from "dexie-react-hooks";
import { CloudUpload } from "lucide-react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/badge";
import { useHasMounted } from "@/hooks/use-has-mounted";
import { db } from "@/lib/db";

export function PendingQueuePill() {
  const t = useTranslations("pos.queue");
  const mounted = useHasMounted();

  const count = useLiveQuery(
    () => (db ? db.pending_receipts.count() : Promise.resolve(0)),
    [],
  );

  if (!mounted || !count || count === 0) return null;

  return (
    <Badge
      variant="outline"
      className="hidden md:inline-flex gap-1 border-accent/40 text-accent"
      title={t("drawerTitle")}
    >
      <CloudUpload className="h-3 w-3" />
      <span>{t("badge", { count })}</span>
    </Badge>
  );
}
