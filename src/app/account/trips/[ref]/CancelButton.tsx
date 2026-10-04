"use client";

import { useFormStatus } from "react-dom";

export function CancelButton() {
  const { pending } = useFormStatus();
  return (
    <button
      type="submit"
      disabled={pending}
      onClick={(e) => {
        if (!confirm("Cancel this reservation? This can't be undone.")) e.preventDefault();
      }}
      className="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50 disabled:opacity-60"
    >
      {pending ? "Cancelling…" : "Cancel reservation"}
    </button>
  );
}
