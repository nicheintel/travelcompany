"use client";

import { useSyncExternalStore } from "react";

const subscribe = () => () => {};

/** Timestamp in the viewer's timezone (UTC during server render, then local). */
export function LocalTime({ ms, dateOnly = false }: { ms: number; dateOnly?: boolean }) {
  const isClient = useSyncExternalStore(subscribe, () => true, () => false);
  const opts: Intl.DateTimeFormatOptions = dateOnly
    ? { dateStyle: "medium" }
    : { dateStyle: "medium", timeStyle: "short" };
  const text = new Date(ms).toLocaleString("en-US", isClient ? opts : { ...opts, timeZone: "UTC" });
  return <time dateTime={new Date(ms).toISOString()}>{isClient || dateOnly ? text : `${text} UTC`}</time>;
}
