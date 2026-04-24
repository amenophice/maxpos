import { Dashboard } from "@/components/dashboard/dashboard";
import { fetchMe } from "@/lib/me";
import { getSession } from "@/lib/session";

export default async function DashboardPage() {
  const session = await getSession();
  const me = await fetchMe();

  return (
    <Dashboard
      userName={session?.user.name ?? null}
      activeSession={me?.active_session ?? null}
    />
  );
}
