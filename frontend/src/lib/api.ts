import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from "axios";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://backend.test";

let isClearingSession = false;
let cachedToken: string | null = null;
let inflightTokenFetch: Promise<string | null> | null = null;

/**
 * On a 401 we must clear our own httpOnly `maxpos_session` cookie BEFORE
 * navigating to /login — otherwise the Next middleware sees the still-valid
 * session cookie, bounces the user back to `/`, the dashboard re-fires the
 * failing call, and we loop. `/api/session/clear` is the same-origin Next
 * route handler that wipes the cookie server-side (we can't delete it from
 * the client because it's httpOnly).
 */
async function handleUnauthorized(): Promise<void> {
  if (typeof window === "undefined") return;
  if (window.location.pathname.startsWith("/login")) return;
  if (isClearingSession) return;
  isClearingSession = true;

  cachedToken = null;
  try {
    await fetch("/api/session/clear", { method: "POST", cache: "no-store" });
  } catch {
    // ignore — fall through to hard redirect regardless
  }
  window.location.href = "/login";
}

/**
 * Pulls the bearer token from the same-origin `/api/session/token` route
 * handler, which reads the httpOnly cookie via `cookies()` and hands the
 * token to the browser. The token never lives in `document.cookie` or
 * `localStorage` — only in this module's memory for the lifetime of the
 * page. Concurrent requests share a single in-flight fetch.
 */
async function fetchSessionToken(): Promise<string | null> {
  if (typeof window === "undefined") return null;
  if (inflightTokenFetch) return inflightTokenFetch;

  inflightTokenFetch = (async () => {
    try {
      const res = await fetch("/api/session/token", { cache: "no-store" });
      if (!res.ok) return null;
      const body = (await res.json()) as { token?: string };
      return body.token ?? null;
    } catch {
      return null;
    } finally {
      inflightTokenFetch = null;
    }
  })();

  return inflightTokenFetch;
}

async function ensureToken(): Promise<string | null> {
  if (cachedToken) return cachedToken;
  const fresh = await fetchSessionToken();
  if (fresh) cachedToken = fresh;
  return cachedToken;
}

export function createApiClient(token?: string): AxiosInstance {
  const client = axios.create({
    baseURL: `${BASE_URL}/api/v1`,
    timeout: 15000,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  // Request: ensure the Authorization header is set from the cached token,
  // pulling a fresh one from /api/session/token if we don't have one yet.
  // Skipped on the server side — there's no httpOnly cookie reader from the
  // browser to talk to, and server callers should be using `apiServer()`.
  client.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
    if (typeof window === "undefined") return config;
    if (config.headers?.Authorization) return config;
    const t = await ensureToken();
    if (t) {
      config.headers = config.headers ?? {};
      (config.headers as Record<string, string>).Authorization = `Bearer ${t}`;
    }
    return config;
  });

  // Response: on 401, try once with a freshly-fetched token. If the refetch
  // also fails (or there's no cookie at all), fall through to the existing
  // clear-cookie + hard-redirect-to-login behaviour.
  client.interceptors.response.use(
    (r) => r,
    async (error: AxiosError) => {
      const status = error?.response?.status;
      const original = error.config as
        | (InternalAxiosRequestConfig & { _retriedWithFreshToken?: boolean })
        | undefined;

      if (status === 401 && original && !original._retriedWithFreshToken) {
        cachedToken = null;
        const fresh = await fetchSessionToken();
        if (fresh) {
          cachedToken = fresh;
          original._retriedWithFreshToken = true;
          original.headers = original.headers ?? {};
          (original.headers as Record<string, string>).Authorization = `Bearer ${fresh}`;
          return client.request(original);
        }
      }

      if (status === 401) {
        void handleUnauthorized();
      }
      return Promise.reject(error);
    },
  );

  return client;
}

export const api = createApiClient();
