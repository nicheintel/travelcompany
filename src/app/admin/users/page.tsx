import type { Metadata } from "next";
import Link from "next/link";
import { LocalTime } from "@/components/admin/LocalTime";
import { int, str } from "@/lib/search-params";
import { requireAdmin } from "@/lib/server/dal";
import { searchUsers } from "@/lib/server/users";
import { RoleButton } from "./RoleButton";

export const metadata: Metadata = { title: "Users" };

export default async function AdminUsers({ searchParams }: PageProps<"/admin/users">) {
  const me = await requireAdmin("/admin/users");
  const sp = await searchParams;
  const q = str(sp.q) ?? "";
  const page = int(str(sp.page), 1, 1, 10000);
  const { users, hasMore } = searchUsers(q, page);
  const pageHref = (n: number) => `/admin/users?${new URLSearchParams({ q, page: String(n) })}`;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-900">Users</h1>
      <form className="flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input
          name="q"
          defaultValue={q}
          placeholder="Search by name or email"
          className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
        <button type="submit" className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">
          Search
        </button>
      </form>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-4 py-3 font-semibold">Name</th>
              <th className="px-4 py-3 font-semibold">Joined</th>
              <th className="px-4 py-3 font-semibold">Bookings</th>
              <th className="px-4 py-3 font-semibold">Role</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {users.map((u) => (
              <tr key={u.id}>
                <td className="px-4 py-3">
                  <p className="font-medium text-slate-900">
                    {u.name} {u.id === me.id && <span className="text-xs text-slate-400">(you)</span>}
                  </p>
                  <p className="text-xs text-slate-500">{u.email}</p>
                </td>
                <td className="px-4 py-3 text-slate-600">
                  <LocalTime ms={u.createdAt} dateOnly />
                </td>
                <td className="px-4 py-3">
                  {u.bookings ? (
                    <Link href={`/admin/bookings?user=${u.id}`} className="font-semibold text-brand-700 hover:underline">
                      {u.bookings} booking{u.bookings === 1 ? "" : "s"}
                    </Link>
                  ) : (
                    <span className="text-slate-400">None</span>
                  )}
                </td>
                <td className="px-4 py-3">
                  {u.role === "admin" ? (
                    <span className="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">
                      Admin{u.adminByConfig ? " · ADMIN_EMAILS" : ""}
                    </span>
                  ) : (
                    <span className="text-slate-500">Customer</span>
                  )}
                </td>
                <td className="px-4 py-3 text-right">
                  {u.id !== me.id && !u.adminByConfig && (
                    <RoleButton userId={u.id} isAdmin={u.role === "admin"} name={u.name} />
                  )}
                </td>
              </tr>
            ))}
            {!users.length && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                  No users found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

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
