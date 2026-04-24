import "server-only";
import { apiServer } from "@/lib/api-server";

export interface ActiveSession {
  id: string;
  opened_at: string;
  initial_cash: string;
  status: "open" | "closed";
}

export interface MeData {
  user: {
    id: number | string;
    name: string;
    email: string;
    tenant_id: string | null;
    roles: string[];
    permissions: string[];
  };
  active_session: ActiveSession | null;
}

/**
 * Server-side GET /api/v1/me. Returns `null` if the backend rejects the
 * token — the caller decides whether to redirect to /login or render a shell.
 * We never clear the session cookie here; that's the explicit Server Action
 * `logoutAction`'s job or the client-side axios interceptor's.
 */
export async function fetchMe(): Promise<MeData | null> {
  try {
    const client = await apiServer();
    const response = await client.get<{ data: MeData }>("/me");
    return response.data.data;
  } catch {
    return null;
  }
}
