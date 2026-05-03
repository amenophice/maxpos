import withSerwistInit from "@serwist/next";
import createNextIntlPlugin from "next-intl/plugin";
import type { NextConfig } from "next";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const withSerwist = withSerwistInit({
  swSrc: "src/app/sw.ts",
  swDest: "public/sw.js",
  disable: process.env.NODE_ENV === "development",
});

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async headers() {
    return [
      {
        source: "/sw.js",
        headers: [
          { key: "Cache-Control", value: "public, max-age=0, must-revalidate" },
          { key: "Service-Worker-Allowed", value: "/" },
        ],
      },
    ];
  },
  webpack: (config) => {
    /**
     * Windows drive-letter casing warning suppression.
     *
     * On Windows, Next.js sometimes resolves project paths with an uppercase
     * drive letter (`C:\Dev\MaXPos\...`) and other tools/modules resolve the
     * same paths with lowercase (`C:\dev\MaXPos\...`). Since Windows'
     * filesystem is case-insensitive the files are identical, but webpack
     * treats them as "multiple modules with names that only differ in
     * casing" and emits dozens of warnings per build.
     *
     * The warning cannot be fixed from user code — it's a Next + Windows
     * quirk. Suppress this warning class only; leave all other warnings
     * visible.
     */
    config.ignoreWarnings = [
      ...(config.ignoreWarnings ?? []),
      { message: /There are multiple modules with names that only differ in casing/ },
      /multiple modules with names that only differ in casing/,
    ];
    return config;
  },
};

export default withSerwist(withNextIntl(nextConfig));
