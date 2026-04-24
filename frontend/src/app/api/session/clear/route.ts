import { NextResponse } from "next/server";
import { clearSession } from "@/lib/session";

/**
 * Server-side endpoint that clears the httpOnly `maxpos_session` cookie.
 *
 * The axios 401 interceptor posts here before redirecting to /login — without
 * it, middleware would see the still-set cookie and bounce the user back to
 * the dashboard, re-firing the 401 query and looping.
 */
export async function POST(): Promise<NextResponse> {
  await clearSession();
  return NextResponse.json({ ok: true });
}
