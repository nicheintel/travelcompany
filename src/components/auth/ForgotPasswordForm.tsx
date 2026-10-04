"use client";

import Link from "next/link";
import { useActionState } from "react";
import { requestPasswordReset } from "@/app/actions/auth";
import { CheckIcon } from "../icons";
import { SubmitButton, TextField } from "./fields";

export function ForgotPasswordForm() {
  const [state, action, pending] = useActionState(requestPasswordReset, undefined);

  if (state?.sent) {
    return (
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">
            <CheckIcon width={16} height={16} />
          </span>
          <p className="text-sm text-emerald-900">
            If an account exists for <strong>{state.email}</strong>, we&apos;ve sent a link to reset your
            password. It expires in 1 hour — check your spam folder if you don&apos;t see it.
          </p>
        </div>
        <Link href="/signin" className="block text-center text-sm font-semibold text-brand-700 hover:underline">
          ← Back to sign in
        </Link>
      </div>
    );
  }

  return (
    <form action={action} className="space-y-5" noValidate>
      <TextField
        label="Email"
        name="email"
        type="email"
        autoComplete="email"
        placeholder="you@example.com"
        required
        defaultValue={state?.email}
        errors={state?.error ? [state.error] : undefined}
      />
      <SubmitButton pending={pending}>{pending ? "Sending…" : "Send reset link"}</SubmitButton>
      <p className="text-center text-sm text-slate-600">
        Remembered it?{" "}
        <Link href="/signin" className="font-semibold text-brand-700 hover:underline">
          Sign in
        </Link>
      </p>
    </form>
  );
}
