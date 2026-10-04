import type { Metadata } from "next";
import { requireAdmin } from "@/lib/server/dal";
import { AdminNav } from "./AdminNav";

export const metadata: Metadata = {
  title: { default: "Admin", template: "%s · Admin" },
  robots: { index: false, follow: false },
};

export default async function AdminLayout({ children }: LayoutProps<"/admin">) {
  // Pages and actions each check again — a layout check alone isn't enough (see Next.js auth guide).
  await requireAdmin("/admin");
  return (
    <div className="min-h-full bg-slate-50">
      <div className="bg-brand-900">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <p className="font-semibold text-white">
            Admin <span className="font-normal text-brand-200">· travel assistant dashboard</span>
          </p>
          <AdminNav />
        </div>
      </div>
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">{children}</div>
    </div>
  );
}
