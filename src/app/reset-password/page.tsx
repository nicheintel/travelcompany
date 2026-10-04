import type { Metadata } from "next";
import Link from "next/link";
import { AuthShell } from "@/components/auth/AuthShell";
import { ResetPasswordForm } from "@/components/auth/ResetPasswordForm";
import { findResetUser } from "@/lib/server/password-reset";
import { str } from "@/lib/search-params";

export const metadata: Metadata = {
  title: "Choose a new password",
  // The token is in the URL — don't leak it to other sites via the Referer header.
  referrer: "no-referrer",
};

export default async function ResetPasswordPage({ searchParams }: PageProps<"/reset-password">) {
  const token = str((await searchParams).token) ?? "";
  const valid = findResetUser(token) !== null;

  if (!valid) {
    return (
      <AuthShell
        title="This link has expired"
        subtitle="Password reset links work once and expire after 1 hour."
      >
        <Link
          href="/forgot-password"
          className="block w-full rounded-xl bg-brand-600 py-3 text-center font-semibold text-white hover:bg-brand-700"
        >
          Send me a new link
        </Link>
      </AuthShell>
    );
  }

  return (
    <AuthShell
      title="Choose a new password"
      subtitle="For your security, you'll be signed out on all other devices."
    >
      <ResetPasswordForm token={token} />
    </AuthShell>
  );
}
