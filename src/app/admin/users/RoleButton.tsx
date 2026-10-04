"use client";

import { useActionState } from "react";
import { setRoleAction } from "@/app/actions/admin";

export function RoleButton({ userId, isAdmin, name }: { userId: number; isAdmin: boolean; name: string }) {
  const [state, action, pending] = useActionState(setRoleAction, undefined);
  return (
    <form
      action={action}
      onSubmit={(e) => {
        const msg = isAdmin ? `Remove admin access for ${name}?` : `Give ${name} admin access? They'll see all bookings and customers.`;
        if (!confirm(msg)) e.preventDefault();
      }}
      className="flex flex-col items-end gap-1"
    >
      <input type="hidden" name="userId" value={userId} />
      <input type="hidden" name="role" value={isAdmin ? "customer" : "admin"} />
      <button
        type="submit"
        disabled={pending}
        className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 disabled:opacity-60 ${
          isAdmin ? "text-red-600 ring-red-200 hover:bg-red-50" : "text-brand-700 ring-brand-200 hover:bg-brand-50"
        }`}
      >
        {isAdmin ? "Remove admin" : "Make admin"}
      </button>
      {state?.error && <span className="text-xs text-red-600">{state.error}</span>}
    </form>
  );
}
