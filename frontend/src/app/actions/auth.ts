"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { clearSession, getSession, setSession } from "@/lib/session";

const LoginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

export interface LoginResult {
  ok: boolean;
  error?: "invalid" | "network" | "unknown" | "forbidden";
  message?: string;
}

export async function loginAction(formData: FormData): Promise<LoginResult> {
  const parsed = LoginSchema.safeParse({
    email: formData.get("email"),
    password: formData.get("password"),
  });

  if (!parsed.success) {
    return { ok: false, error: "invalid" };
  }

  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://backend.test";

  let response: Response;
  try {
    response = await fetch(`${base}/api/v1/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        email: parsed.data.email,
        password: parsed.data.password,
        device_name: "maxpos-web",
      }),
      cache: "no-store",
    });
  } catch {
    return { ok: false, error: "network" };
  }

  if (response.status === 422 || response.status === 401) {
    return { ok: false, error: "invalid" };
  }
  if (response.status === 403) {
    const body = (await response.json()) as { data?: { message?: string } };
    return { ok: false, error: "forbidden", message: body?.data?.message ?? undefined };
  }
  if (!response.ok) {
    return { ok: false, error: "unknown" };
  }

  const body = (await response.json()) as {
    data: { token: string; user: Record<string, unknown> };
  };

  await setSession({
    token: body.data.token,
    user: {
      id: body.data.user.id as number | string,
      name: body.data.user.name as string,
      email: body.data.user.email as string,
      tenant_id: (body.data.user.tenant_id as string | null) ?? null,
      roles: (body.data.user.roles as string[]) ?? [],
      permissions: (body.data.user.permissions as string[]) ?? [],
    },
  });

  return { ok: true };
}

export async function logoutAction(): Promise<void> {
  const session = await getSession();
  if (session) {
    const base = process.env.NEXT_PUBLIC_API_URL ?? "http://backend.test";
    try {
      await fetch(`${base}/api/v1/auth/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${session.token}`,
        },
        cache: "no-store",
      });
    } catch {
      // ignore — clearing cookie below is authoritative client-side
    }
  }
  await clearSession();
  redirect("/login");
}
