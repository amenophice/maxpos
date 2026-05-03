import axios, { type AxiosInstance } from "axios";
import "server-only";
import { getSession } from "@/lib/session";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://backend.test";

/**
 * Server-side Axios client.
 *
 * Why this exists (see CHANGELOG "Prompt 4 — Fix 4"):
 * the session token lives in the httpOnly `maxpos_session` cookie so client JS
 * cannot read it. When a Server Component or Server Action talks to the Laravel
 * API it must forward the token manually. This helper reads the cookie via
 * `next/headers` and attaches `Authorization: Bearer <token>` to every outgoing
 * request.
 *
 * It is `"server-only"` — importing it from a Client Component will hard-fail
 * at build time, which is exactly what we want: Client Components must use
 * `@/lib/api` instead (eventually backed by a same-origin proxy / SWR hydration
 * on the client). The two clients intentionally have separate 401 behaviour —
 * this server client throws, so the caller (usually a layout or page) can
 * decide between `redirect("/login")`, rendering a stale shell, or surfacing an
 * error. It must NOT silently clear the session the way the client interceptor
 * does, otherwise a transient backend hiccup would log the whole app out.
 */
export async function apiServer(): Promise<AxiosInstance> {
  const session = await getSession();

  const client = axios.create({
    baseURL: `${BASE_URL}/api/v1`,
    timeout: 15000,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(session ? { Authorization: `Bearer ${session.token}` } : {}),
    },
  });

  return client;
}
