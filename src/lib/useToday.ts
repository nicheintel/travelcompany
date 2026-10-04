"use client";

import { useSyncExternalStore } from "react";

function todayIso() {
  const d = new Date();
  const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
}

const subscribe = () => () => {};

/**
 * Today's date (YYYY-MM-DD) in the visitor's timezone.
 * Returns "" during server rendering so static pages never bake in a stale date.
 */
export function useToday() {
  return useSyncExternalStore(subscribe, todayIso, () => "");
}
