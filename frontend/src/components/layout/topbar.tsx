"use client";

import { LogOut, Menu, Search, User as UserIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useTransition } from "react";
import { logoutAction } from "@/app/actions/auth";
import { CatalogSyncButton } from "@/components/layout/catalog-sync-button";
import { NetworkStatusBadge } from "@/components/layout/network-status-badge";
import { PendingQueuePill } from "@/components/layout/pending-queue-pill";
import { SessionPill } from "@/components/layout/session-pill";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import type { ActiveSession } from "@/lib/me";
import type { AppShellUser } from "./app-shell";

export function Topbar({
  user,
  activeSession,
  onOpenMobileSidebar,
}: {
  user: AppShellUser;
  activeSession: ActiveSession | null;
  onOpenMobileSidebar: () => void;
}) {
  const t = useTranslations("topbar");
  const tNav = useTranslations("nav");
  const tAuth = useTranslations("auth");
  const [isLoggingOut, startLogout] = useTransition();

  const initials = user.name
    .split(/\s+/)
    .slice(0, 2)
    .map((s) => s.charAt(0).toUpperCase())
    .join("");

  return (
    <header className="h-16 flex items-center gap-3 px-3 sm:px-5 border-b border-border bg-background">
      <Button
        variant="ghost"
        size="icon"
        className="md:hidden"
        onClick={onOpenMobileSidebar}
        aria-label={tNav("toggleSidebar")}
      >
        <Menu className="h-5 w-5" />
      </Button>

      <div className="hidden sm:flex items-center gap-2 min-w-0">
        <span className="font-serif text-lg font-semibold text-primary">MaXPos</span>
        <span className="text-muted-foreground text-sm truncate">
          · {t("locationFallback")}
        </span>
      </div>

      <div className="flex-1 max-w-xl mx-auto w-full">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="search"
            placeholder={t("searchPlaceholder")}
            className="pl-9"
            disabled
          />
        </div>
      </div>

      <div className="flex items-center gap-2">
        <NetworkStatusBadge />

        <PendingQueuePill />

        <CatalogSyncButton />

        <SessionPill activeSession={activeSession} />

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={tNav("profile")}>
              <Avatar className="h-8 w-8">
                <AvatarFallback className="bg-primary text-primary-foreground text-xs">
                  {initials || "M"}
                </AvatarFallback>
              </Avatar>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel>
              <div className="flex flex-col">
                <span className="text-sm font-medium">{user.name}</span>
                <span className="text-xs text-muted-foreground">{user.email}</span>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem disabled>
              <UserIcon className="mr-2 h-4 w-4" />
              {tNav("profile")}
            </DropdownMenuItem>
            <DropdownMenuItem
              onSelect={(e) => {
                e.preventDefault();
                startLogout(async () => {
                  await logoutAction();
                });
              }}
              disabled={isLoggingOut}
            >
              <LogOut className="mr-2 h-4 w-4" />
              {tAuth("logout.label")}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
