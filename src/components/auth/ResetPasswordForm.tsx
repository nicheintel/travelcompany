"use client";

import Link from "next/link";
import { useActionState } from "react";
import { resetPassword } from "@/app/actions/auth";
import { FormMessage, SubmitButton, TextField } from "./fields";

export function ResetPasswordForm({ token }: { token: string }) {
  const [state, action, pending] = useActionState(resetPassword, undefined);

  return (
    <form action={action} className="space-y-5" noValidate>
      <input type="hidden" name="token" value={token} />
      <FormMessage message={state?.message} />
      {state?.message && (
        <Link href="/forgot-password" className="block text-sm font-semibold text-brand-700 hover:underline">
          Request a new reset link →
        </Link>
      )}
      <TextField
        label="New password"
        name="password"
        type="password"
        autoComplete="new-password"
        hint="At least 8 characters, with a letter and a number."
        required
        errors={state?.errors?.password}
      />
      <TextField
        label="Confirm new password"
        name="confirmPassword"
        type="password"
        autoComplete="new-password"
        required
        errors={state?.errors?.confirmPassword}
      />
      <SubmitButton pending={pending}>{pending ? "Saving…" : "Set new password"}</SubmitButton>
    </form>
  );
}
