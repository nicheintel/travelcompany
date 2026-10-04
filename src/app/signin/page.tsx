import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AuthShell } from "@/components/auth/AuthShell";
import { SignInForm } from "@/components/auth/SignInForm";
import { getCurrentUser } from "@/lib/server/dal";
import { noticeFor, readNextParam } from "@/lib/server/next-param";

export const metadata: Metadata = { title: "Sign in" };

export default async function SignInPage({ searchParams }: PageProps<"/signin">) {
  const next = readNextParam((await searchParams).next);
  if (await getCurrentUser()) redirect(next ?? "/account");

  return (
    <AuthShell title="Welcome back" subtitle="Sign in to manage your trips and unlock member prices." notice={noticeFor(next)}>
      <SignInForm next={next} />
    </AuthShell>
  );
}
