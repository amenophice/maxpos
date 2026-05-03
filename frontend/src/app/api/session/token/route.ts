import { NextResponse } from "next/server";
import { getSession } from "@/lib/session";

/**
 * Server-side endpoint that hands the browser the bearer token from the
 * httpOnly `maxpos_session` cookie.
 *
 * The cookie itself stays httpOnly — JavaScript on the client cannot read
 * `document.cookie`. This same-origin GET is the only way the client-side
 * axios instance (`src/lib/api.ts`) can learn the token to set on outgoing
 * `Authorization: Bearer …` headers.
 *
 * Pairs with `/api/session/clear` (POST) which wipes the cookie when the
 * backend rejects the token.
 */
export async function GET(): Promise<NextResponse> {
  const session = await getSession();
  if (!session) {
    return NextResponse.json(
      { error: "no_session" },
      { status: 401, headers: { "Cache-Control": "no-store" } },
    );
  }
  return NextResponse.json(
    { token: session.token },
    { headers: { "Cache-Control": "no-store" } },
  );
}
