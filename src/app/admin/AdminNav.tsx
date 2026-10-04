"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const LINKS = [
  { href: "/admin", label: "Overview", exact: true },
  { href: "/admin/bookings", label: "Bookings" },
  { href: "/admin/users", label: "Users" },
];

export function AdminNav() {
  const pathname = usePathname();
  return (
    <nav className="flex gap-1 overflow-x-auto">
      {LINKS.map(({ href, label, exact }) => {
        const active = exact ? pathname === href : pathname.startsWith(href);
        return (
          <Link
            key={href}
            href={href}
            className={`shrink-0 rounded-lg px-4 py-2 text-sm font-semibold transition ${
              active ? "bg-white text-brand-800 shadow-sm" : "text-brand-100 hover:bg-white/10 hover:text-white"
            }`}
          >
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
