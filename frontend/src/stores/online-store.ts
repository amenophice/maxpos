"use client";

import { create } from "zustand";

interface OnlineState {
  isOnline: boolean;
  setOnline: (value: boolean) => void;
}

export const useOnlineStore = create<OnlineState>((set) => ({
  isOnline: typeof navigator !== "undefined" ? navigator.onLine : true,
  setOnline: (value) => set({ isOnline: value }),
}));
