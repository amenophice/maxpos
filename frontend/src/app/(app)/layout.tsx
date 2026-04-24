import { redirect } from "next/navigation";
import type { ReactNode } from "react";
import { AppShell } from "@/components/layout/app-shell";
import { fetchMe } from "@/lib/me";
import { getSession } from "@/lib/session";

export default async function AppLayout({ children }: { children: ReactNode }) {
  const session = await getSession();
  if (!session) {
    redirect("/login");
  }

  // Server-side /me: forwards the bearer token from the httpOnly cookie.
  // If the backend rejects the token, `me` is null and we keep rendering the
  // shell with the cookie's user data — a subsequent explicit action (logout,
  // or an expired 401 from a client-side call) is what actually logs out.
  const me = await fetchMe();

  return (
    <AppShell user={session.user} activeSession={me?.active_session ?? null}>
      {children}
    </AppShell>
  );
}
