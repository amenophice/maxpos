"use client";

import { useEffect } from "react";
import { useOnlineStore } from "@/stores/online-store";

export function OnlineListener() {
  const setOnline = useOnlineStore((s) => s.setOnline);

  useEffect(() => {
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    setOnline(navigator.onLine);
    window.addEventListener("online", on);
    window.addEventListener("offline", off);
    return () => {
      window.removeEventListener("online", on);
      window.removeEventListener("offline", off);
    };
  }, [setOnline]);

  return null;
}
