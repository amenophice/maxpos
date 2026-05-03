import { getRequestConfig } from "next-intl/server";
import { cookies } from "next/headers";
import en from "../../messages/en.json";
import ro from "../../messages/ro.json";

export const defaultLocale = "ro" as const;
export const locales = ["ro", "en"] as const;
export type Locale = (typeof locales)[number];

const catalogs: Record<Locale, typeof ro> = { ro, en };

export default getRequestConfig(async () => {
  const cookieStore = await cookies();
  const cookieLocale = cookieStore.get("maxpos_locale")?.value;
  const locale: Locale = (locales as readonly string[]).includes(cookieLocale ?? "")
    ? (cookieLocale as Locale)
    : defaultLocale;

  return {
    locale,
    messages: catalogs[locale],
    timeZone: "Europe/Bucharest",
  };
});
