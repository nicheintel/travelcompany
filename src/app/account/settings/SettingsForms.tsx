"use client";

import { useActionState } from "react";
import { changeEmail, changePassword, updateProfile, type SettingsState } from "@/app/actions/account";
import { FormMessage, SubmitButton, TextField } from "@/components/auth/fields";

function Success({ state }: { state: SettingsState }) {
  if (!state?.ok) return null;
  return (
    <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
      {state.ok}
    </p>
  );
}

function Card({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6">
      <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
      <p className="mt-1 text-sm text-slate-500">{description}</p>
      <div className="mt-5">{children}</div>
    </section>
  );
}

export function ProfileForm({ name }: { name: string }) {
  const [state, action, pending] = useActionState(updateProfile, undefined);
  return (
    <Card title="Profile" description="Your name as it appears on your account.">
      <form action={action} className="space-y-4" noValidate>
        <Success state={state} />
        <TextField label="Full name" name="name" autoComplete="name" defaultValue={name} errors={state?.errors?.name} />
        <div className="sm:w-48">
          <SubmitButton pending={pending}>{pending ? "Saving…" : "Save name"}</SubmitButton>
        </div>
      </form>
    </Card>
  );
}

export function EmailForm({ email }: { email: string }) {
  const [state, action, pending] = useActionState(changeEmail, undefined);
  return (
    <Card title="Email address" description={`Currently ${email}. We'll notify your old address when it changes.`}>
      <form action={action} className="space-y-4" noValidate>
        <Success state={state} />
        <FormMessage message={state?.message} />
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label="New email" name="email" type="email" autoComplete="email" errors={state?.errors?.email} />
          <TextField
            label="Current password"
            name="currentPassword"
            type="password"
            autoComplete="current-password"
            errors={state?.errors?.currentPassword}
          />
        </div>
        <div className="sm:w-48">
          <SubmitButton pending={pending}>{pending ? "Saving…" : "Change email"}</SubmitButton>
        </div>
      </form>
    </Card>
  );
}

export function PasswordForm() {
  const [state, action, pending] = useActionState(changePassword, undefined);
  return (
    <Card title="Password" description="Changing your password signs you out on all other devices.">
      <form action={action} className="space-y-4" noValidate>
        <Success state={state} />
        <TextField
          label="Current password"
          name="currentPassword"
          type="password"
          autoComplete="current-password"
          errors={state?.errors?.currentPassword}
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            label="New password"
            name="password"
            type="password"
            autoComplete="new-password"
            hint="At least 8 characters, with a letter and a number."
            errors={state?.errors?.password}
          />
          <TextField
            label="Confirm new password"
            name="confirmPassword"
            type="password"
            autoComplete="new-password"
            errors={state?.errors?.confirmPassword}
          />
        </div>
        <div className="sm:w-48">
          <SubmitButton pending={pending}>{pending ? "Saving…" : "Change password"}</SubmitButton>
        </div>
      </form>
    </Card>
  );
}
