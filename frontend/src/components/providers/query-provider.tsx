"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

/**
 * ReactQueryDevtools is intentionally NOT imported here.
 *
 * On Windows + Next 16 + webpack + pnpm we hit a ChunkLoadError when the
 * devtools panel lazy-loads its chunk: webpack emits the chunk under one drive
 * casing (`C:\Dev\...`) while the browser requests it via another (`C:\dev\...`),
 * the request "succeeds" but the module never registers, React's error boundary
 * commits, and Next's dev HMR force-reloads the page. The reload restarts the
 * devtools chunk fetch, which fails the same way — hence the infinite refresh
 * loop users were seeing on the dashboard.
 *
 * We don't need devtools to ship MVP. Re-enable this once we're building on
 * Linux (CI, prod, Docker dev container) where the filesystem is case-sensitive
 * and the chunk-load bug doesn't apply. Tracking: see CHANGELOG "Prompt 4 — Fix 3".
 */
export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
