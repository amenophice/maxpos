"use client";

import {
  BarChart3,
  ChevronLeft,
  ChevronRight,
  PackageSearch,
  Receipt,
  Settings,
  ShoppingCart,
  X,
} from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const NAV_ITEMS = [
  { href: "/vanzare", icon: ShoppingCart, key: "sale" },
  { href: "/receipts", icon: Receipt, key: "receipts" },
  { href: "/stock", icon: PackageSearch, key: "stock" },
  { href: "/reports", icon: BarChart3, key: "reports" },
  { href: "/settings", icon: Settings, key: "settings" },
] as const;

interface SidebarProps {
  collapsed: boolean;
  mobileOpen: boolean;
  onToggleCollapsed: () => void;
  onCloseMobile: () => void;
}

export function Sidebar({
  collapsed,
  mobileOpen,
  onToggleCollapsed,
  onCloseMobile,
}: SidebarProps) {
  const t = useTranslations("nav");
  const pathname = usePathname();

  const content = (
    <>
      <div className="flex items-center justify-between h-16 px-4 border-b border-sidebar-border">
        <Link href="/" className={cn("flex items-center gap-2", collapsed && "justify-center w-full")}>
          <span className="font-serif text-2xl font-bold text-sidebar-primary">M</span>
          {!collapsed && <span className="font-serif text-xl font-semibold">MaXPos</span>}
        </Link>
        <Button
          variant="ghost"
          size="icon"
          onClick={onToggleCollapsed}
          aria-label={t("toggleSidebar")}
          className="hidden md:inline-flex"
        >
          {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
        </Button>
        <Button
          variant="ghost"
          size="icon"
          onClick={onCloseMobile}
          aria-label={t("toggleSidebar")}
          className="md:hidden"
        >
          <X className="h-4 w-4" />
        </Button>
      </div>
      <nav className="flex-1 px-2 py-4 space-y-1">
        {NAV_ITEMS.map((item) => {
          const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`);
          const Icon = item.icon;
          return (
            <Link
              key={item.key}
              href={item.href}
              onClick={onCloseMobile}
              className={cn(
                "group flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors",
                "border-l-2 border-transparent",
                isActive
                  ? "bg-sidebar-accent text-sidebar-accent-foreground border-l-sidebar-primary"
                  : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                collapsed && "justify-center px-2",
              )}
            >
              <Icon className={cn("h-4 w-4 shrink-0", isActive && "text-sidebar-primary")} />
              {!collapsed && <span>{t(`items.${item.key}`)}</span>}
            </Link>
          );
        })}
      </nav>
    </>
  );

  return (
    <>
      <aside
        className={cn(
          "hidden md:flex flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-[width] duration-200",
          collapsed ? "w-16" : "w-60",
        )}
      >
        {content}
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div
            className="absolute inset-0 bg-foreground/40"
            onClick={onCloseMobile}
            aria-hidden
          />
          <aside className="absolute inset-y-0 left-0 w-64 flex flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground shadow-lg">
            {content}
          </aside>
        </div>
      )}
    </>
  );
}
