import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AuthShell } from "@/components/auth/AuthShell";
import { RegisterForm } from "@/components/auth/RegisterForm";
import { getCurrentUser } from "@/lib/server/dal";
import { noticeFor, readNextParam } from "@/lib/server/next-param";

export const metadata: Metadata = { title: "Create account" };

export default async function RegisterPage({ searchParams }: PageProps<"/register">) {
  const next = readNextParam((await searchParams).next);
  if (await getCurrentUser()) redirect(next ?? "/account");

  return (
    <AuthShell
      title="Create your free account"
      subtitle="It takes less than a minute. No credit card needed."
      notice={noticeFor(next)}
    >
      <RegisterForm next={next} />
    </AuthShell>
  );
}
