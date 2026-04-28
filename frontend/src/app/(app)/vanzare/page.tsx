import { PosScreen } from "@/components/pos/pos-screen";
import { fetchMe } from "@/lib/me";

export const dynamic = "force-dynamic";

/**
 * /vanzare must ALWAYS render once the user is authenticated. Don't
 * server-redirect on transient `fetchMe()` failures or on a missing cash
 * session — the (app) layout already redirects to /login on missing auth,
 * and the screen surfaces "no session" / "empty catalog" as in-page soft
 * banners. The previous `if (!me) redirect("/login")` here was the
 * "/vanzare → /login → middleware bounce → /" round trip users were seeing.
 */
export default async function SalePage() {
  const me = await fetchMe();
  return (
    <PosScreen
      cashSessionId={me?.active_session?.id ?? null}
      cashSessionOpen={me?.active_session?.status === "open"}
    />
  );
}
