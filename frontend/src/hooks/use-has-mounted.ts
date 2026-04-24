"use client";

import { useSyncExternalStore } from "react";

/**
 * Returns `true` only on the client after commit; `false` during SSR and
 * during the initial client render pass before hydration completes.
 *
 * Use this to gate UI that depends on `window`, `navigator`, or any
 * client-only store whose initial value differs between server and client —
 * the server render will use the stable "not mounted" branch, eliminating
 * hydration mismatches without tripping the `react-hooks/set-state-in-effect`
 * rule that a naive `useState + useEffect(() => setMounted(true))` pattern
 * triggers.
 */
const noop = () => () => {};
const getClientSnapshot = () => true;
const getServerSnapshot = () => false;

export function useHasMounted(): boolean {
  return useSyncExternalStore(noop, getClientSnapshot, getServerSnapshot);
}
