import type { Metadata } from "next";
import Link from "next/link";
import { requireUser } from "@/lib/server/dal";
import { EmailForm, PasswordForm, ProfileForm } from "./SettingsForms";

export const metadata: Metadata = { title: "Account settings" };

export default async function SettingsPage() {
  const user = await requireUser("/account/settings");
  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <nav className="mb-4 text-sm text-slate-500">
        <Link href="/account" className="hover:text-brand-700">
          ← My account
        </Link>
      </nav>
      <h1 className="text-3xl font-bold text-slate-900">Account settings</h1>
      <div className="mt-8 space-y-6">
        <ProfileForm name={user.name} />
        <EmailForm email={user.email} />
        <PasswordForm />
      </div>
    </div>
  );
}
