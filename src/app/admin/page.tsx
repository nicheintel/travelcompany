import type { Metadata } from "next";
import Link from "next/link";
import { BookingsTable } from "@/components/admin/BookingsTable";
import { addDays, formatPrice } from "@/lib/format";
import { serverToday } from "@/lib/search-params";
import { adminNeedsAttention, adminSearchBookings, adminStats } from "@/lib/server/bookings";
import { requireAdmin } from "@/lib/server/dal";

export const metadata: Metadata = { title: "Overview" };

function Stat({ label, value, hint, href }: { label: string; value: string; hint?: string; href?: string }) {
  const body = (
    <>
      <p className="text-sm font-medium text-slate-500">{label}</p>
      <p className="mt-1 text-3xl font-extrabold text-slate-900">{value}</p>
      {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
    </>
  );
  const cls = "block rounded-xl border border-slate-200 bg-white p-5";
  return href ? (
    <Link href={href} className={`${cls} transition hover:border-brand-300 hover:shadow-md`}>
      {body}
    </Link>
  ) : (
    <div className={cls}>{body}</div>
  );
}

export default async function AdminOverview() {
  const admin = await requireAdmin("/admin");
  const today = serverToday();
  const stats = adminStats(today);
  const attention = adminNeedsAttention(today, addDays(today, 14));
  const recent = adminSearchBookings({ page: 1 }).bookings.slice(0, 10);

  return (
    <div className="space-y-10">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Hello, {admin.name.split(" ")[0]}</h1>
        <p className="text-slate-600">Here&apos;s what needs your attention today.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat
          label="Awaiting payment"
          value={String(stats.awaitingPayment.n)}
          hint={`${formatPrice(stats.awaitingPayment.total ?? 0)} in upcoming unpaid trips`}
          href="/admin/bookings?status=reserved"
        />
        <Stat label="Bookings (7 days)" value={String(stats.bookings7d)} href="/admin/bookings" />
        <Stat label="Revenue (30 days)" value={formatPrice(stats.revenue30d)} hint="Paid bookings" href="/admin/bookings?status=paid" />
        <Stat label="New members (7 days)" value={String(stats.newUsers7d)} href="/admin/users" />
      </div>

      <section>
        <div className="mb-3">
          <h2 className="text-lg font-semibold text-slate-900">Needs a call ({attention.length})</h2>
          <p className="text-sm text-slate-500">
            Unpaid trips departing within 14 days, or reserved more than 24 hours ago — soonest departure first.
          </p>
        </div>
        <BookingsTable bookings={attention} empty="All caught up — no unpaid trips need a call right now. 🎉" />
      </section>

      <section>
        <div className="mb-3 flex items-end justify-between">
          <h2 className="text-lg font-semibold text-slate-900">Latest bookings</h2>
          <Link href="/admin/bookings" className="text-sm font-semibold text-brand-700 hover:underline">
            All bookings →
          </Link>
        </div>
        <BookingsTable bookings={recent} empty="No bookings yet." />
      </section>
    </div>
  );
}
