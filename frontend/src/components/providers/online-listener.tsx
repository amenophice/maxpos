"use client";

import { useEffect } from "react";
import { processPendingQueue } from "@/lib/sync/receipts-sync";
import { useOnlineStore } from "@/stores/online-store";

export function OnlineListener() {
  const setOnline = useOnlineStore((s) => s.setOnline);

  useEffect(() => {
    const on = () => {
      setOnline(true);
      // Attempt to drain any receipts queued while offline. Fire-and-forget;
      // the processor no-ops if the queue is empty or already draining.
      void processPendingQueue();
    };
    const off = () => setOnline(false);
    setOnline(navigator.onLine);
    if (navigator.onLine) void processPendingQueue();
    window.addEventListener("online", on);
    window.addEventListener("offline", off);
    return () => {
      window.removeEventListener("online", on);
      window.removeEventListener("offline", off);
    };
  }, [setOnline]);

  return null;
}
