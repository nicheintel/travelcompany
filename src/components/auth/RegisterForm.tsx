"use client";

import Link from "next/link";
import { useActionState, useState } from "react";
import { register } from "@/app/actions/auth";
import { CheckIcon } from "../icons";
import { FormMessage, SubmitButton, TextField } from "./fields";

const RULES = [
  { label: "At least 8 characters", test: (p: string) => p.length >= 8 },
  { label: "A letter", test: (p: string) => /[a-zA-Z]/.test(p) },
  { label: "A number", test: (p: string) => /[0-9]/.test(p) },
];

export function RegisterForm({ next }: { next?: string }) {
  const [state, action, pending] = useActionState(register, undefined);
  const [password, setPassword] = useState("");

  return (
    <form action={action} className="space-y-5" noValidate>
      {next && <input type="hidden" name="next" value={next} />}
      <FormMessage message={state?.message} />
      <TextField
        label="Full name"
        name="name"
        autoComplete="name"
        placeholder="Alex Rivera"
        required
        defaultValue={state?.values?.name}
        errors={state?.errors?.name}
      />
      <TextField
        label="Email"
        name="email"
        type="email"
        autoComplete="email"
        placeholder="you@example.com"
        required
        defaultValue={state?.values?.email}
        errors={state?.errors?.email}
      />
      <div>
        <TextField
          label="Password"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          errors={state?.errors?.password}
        />
        <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
          {RULES.map((r) => {
            const ok = r.test(password);
            return (
              <li key={r.label} className={`flex items-center gap-1 ${ok ? "text-emerald-600" : "text-slate-500"}`}>
                <CheckIcon width={12} height={12} className={ok ? "" : "opacity-30"} />
                {r.label}
              </li>
            );
          })}
        </ul>
      </div>
      <TextField
        label="Confirm password"
        name="confirmPassword"
        type="password"
        autoComplete="new-password"
        required
        errors={state?.errors?.confirmPassword}
      />
      <SubmitButton pending={pending}>{pending ? "Creating account…" : "Create account"}</SubmitButton>
      <p className="text-center text-xs text-slate-500">
        By creating an account you agree to our Terms of Service and Privacy Policy.
      </p>
      <p className="text-center text-sm text-slate-600">
        Already have an account?{" "}
        <Link
          href={next ? `/signin?next=${encodeURIComponent(next)}` : "/signin"}
          className="font-semibold text-brand-700 hover:underline"
        >
          Sign in
        </Link>
      </p>
    </form>
  );
}
