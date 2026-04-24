"use client";

import { useState, type ReactNode } from "react";
import type { ActiveSession } from "@/lib/me";
import { Sidebar } from "./sidebar";
import { Topbar } from "./topbar";

export interface AppShellUser {
  id: number | string;
  name: string;
  email: string;
  tenant_id: string | null;
  roles: string[];
  permissions: string[];
}

export function AppShell({
  user,
  activeSession,
  children,
}: {
  user: AppShellUser;
  activeSession: ActiveSession | null;
  children: ReactNode;
}) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  return (
    <div className="min-h-screen flex bg-background text-foreground">
      <Sidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onToggleCollapsed={() => setCollapsed((c) => !c)}
        onCloseMobile={() => setMobileOpen(false)}
      />
      <div className="flex flex-col flex-1 min-w-0">
        <Topbar
          user={user}
          activeSession={activeSession}
          onOpenMobileSidebar={() => setMobileOpen(true)}
        />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <div className="mx-auto max-w-6xl w-full">{children}</div>
        </main>
      </div>
    </div>
  );
}
