import type { Metadata } from "next";
import Link from "next/link";
import { BookingsTable } from "@/components/admin/BookingsTable";
import { int, str } from "@/lib/search-params";
import { adminSearchBookings } from "@/lib/server/bookings";
import { requireAdmin } from "@/lib/server/dal";
import { findUserById } from "@/lib/server/users";

export const metadata: Metadata = { title: "Bookings" };

const SELECT = "rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm";

export default async function AdminBookings({ searchParams }: PageProps<"/admin/bookings">) {
  await requireAdmin("/admin/bookings");
  const sp = await searchParams;
  const q = str(sp.q) ?? "";
  const status = str(sp.status) ?? "";
  const kind = str(sp.kind) ?? "";
  const userId = int(str(sp.user), 0, 0, Number.MAX_SAFE_INTEGER) || undefined;
  const page = int(str(sp.page), 1, 1, 10000);
  const customer = userId ? findUserById(userId) : null;

  const { bookings, hasMore } = adminSearchBookings({ q, status, kind, userId, page });
  const pageHref = (n: number) => {
    const params = new URLSearchParams({ q, status, kind, page: String(n), ...(userId ? { user: String(userId) } : {}) });
    return `/admin/bookings?${params}`;
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Bookings</h1>
        {customer && (
          <p className="mt-1 text-sm text-slate-600">
            Showing bookings by <strong>{customer.name}</strong> ({customer.email}) ·{" "}
            <Link href="/admin/bookings" className="font-semibold text-brand-700 hover:underline">
              show all
            </Link>
          </p>
        )}
      </div>

      <form className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        {userId && <input type="hidden" name="user" value={userId} />}
        <label className="flex min-w-64 flex-1 flex-col gap-1 text-xs font-medium text-slate-500">
          Search
          <input
            name="q"
            defaultValue={q}
            placeholder="Reference, name, email, phone or traveler"
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
          />
        </label>
        <label className="flex flex-col gap-1 text-xs font-medium text-slate-500">
          Status
          <select name="status" defaultValue={status} className={SELECT}>
            <option value="">All</option>
            <option value="reserved">Unpaid</option>
            <option value="paid">Paid</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs font-medium text-slate-500">
          Type
          <select name="kind" defaultValue={kind} className={SELECT}>
            <option value="">All</option>
            <option value="flight">Flights</option>
            <option value="hotel">Hotels</option>
            <option value="package">Packages</option>
          </select>
        </label>
        <button type="submit" className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">
          Filter
        </button>
        {(q || status || kind) && (
          <Link href={userId ? `/admin/bookings?user=${userId}` : "/admin/bookings"} className="py-2 text-sm font-medium text-slate-500 hover:text-slate-800">
            Clear
          </Link>
        )}
      </form>

      <BookingsTable bookings={bookings} />

      {(page > 1 || hasMore) && (
        <div className="flex justify-between text-sm font-semibold">
          {page > 1 ? <Link href={pageHref(page - 1)} className="text-brand-700 hover:underline">← Newer</Link> : <span />}
          <span className="text-slate-500">Page {page}</span>
          {hasMore ? <Link href={pageHref(page + 1)} className="text-brand-700 hover:underline">Older →</Link> : <span />}
        </div>
      )}
    </div>
  );
}
