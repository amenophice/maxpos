import axios, { type AxiosInstance } from "axios";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://backend.test";

let isClearingSession = false;

/**
 * On a 401 we must clear our own httpOnly `maxpos_session` cookie BEFORE
 * navigating to /login — otherwise the Next middleware sees the still-valid
 * session cookie, bounces the user back to `/`, the dashboard re-fires the
 * failing `/me` query, and we loop.
 *
 * `/api/session/clear` is a same-origin Next route handler that wipes the
 * cookie server-side; we can't delete it from the client because it's
 * httpOnly. The `isClearingSession` guard prevents a storm of overlapping
 * 401s from calling the endpoint many times.
 */
async function handleUnauthorized(): Promise<void> {
  if (typeof window === "undefined") return;
  if (window.location.pathname.startsWith("/login")) return;
  if (isClearingSession) return;
  isClearingSession = true;

  try {
    await fetch("/api/session/clear", { method: "POST", cache: "no-store" });
  } catch {
    // ignore — fall through to hard redirect regardless
  }
  window.location.href = "/login";
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

  client.interceptors.response.use(
    (r) => r,
    (error) => {
      if (error?.response?.status === 401) {
        void handleUnauthorized();
      }
      return Promise.reject(error);
    },
  );

  return client;
}

export const api = createApiClient();
