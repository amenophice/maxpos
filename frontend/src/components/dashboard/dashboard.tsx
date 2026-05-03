"use client";

import { formatInTimeZone } from "date-fns-tz";
import { ArrowRight, ShoppingCart } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import type { ActiveSession } from "@/lib/me";

/**
 * Presentational — the active session is fetched server-side in
 * `(app)/page.tsx` via `fetchMe()` and handed in as a prop. See
 * `src/lib/api-server.ts` for why client-side `/me` calls can't authenticate.
 */
export function Dashboard({
  userName,
  activeSession,
}: {
  userName: string | null;
  activeSession: ActiveSession | null;
}) {
  const t = useTranslations("dashboard");
  const tCommon = useTranslations("common");
  const tNav = useTranslations("nav");

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-serif text-3xl font-bold tracking-tight">
          {userName ? t("welcome", { name: userName }) : t("welcomeNoName")}
        </h1>
        <p className="text-muted-foreground text-sm mt-1">{tCommon("tagline")}</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("sessionCardTitle")}</CardTitle>
            <CardDescription>
              {activeSession
                ? t("sessionOpenText", {
                    time: formatInTimeZone(
                      new Date(activeSession.opened_at),
                      "Europe/Bucharest",
                      "HH:mm",
                    ),
                  })
                : t("sessionNoneText")}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild>
              <Link href="/vanzare">
                <ShoppingCart className="mr-2 h-4 w-4" />
                {activeSession ? t("goToSale") : t("openSessionCta")}
                <ArrowRight className="ml-2 h-4 w-4" />
              </Link>
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("quickLinks")}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            <Button variant="outline" asChild>
              <Link href="/receipts">{tNav("items.receipts")}</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href="/stock">{tNav("items.stock")}</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href="/reports">{tNav("items.reports")}</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
