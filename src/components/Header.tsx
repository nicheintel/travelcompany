"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { signOut } from "@/app/actions/auth";
import { Logo } from "./Logo";
import { BedIcon, CloseIcon, MenuIcon, PackageIcon, PlaneIcon, UserIcon } from "./icons";

export type HeaderUser = { name: string; email: string };

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]!.toUpperCase())
    .join("");
}

const NAV = [
  { href: "/flights", label: "Flights", icon: PlaneIcon },
  { href: "/hotels", label: "Hotels", icon: BedIcon },
  { href: "/packages", label: "Packages", icon: PackageIcon },
];

/**
 * `user` is undefined while the session is still loading (renders a placeholder),
 * null when signed out.
 */
export function Header({ user }: { user?: HeaderUser | null }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <Logo />

        <nav className="hidden items-center gap-1 md:flex">
          {NAV.map(({ href, label, icon: Icon }) => {
            const active = pathname.startsWith(href);
            return (
              <Link
                key={href}
                href={href}
                className={`flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition ${
                  active
                    ? "bg-brand-50 text-brand-700"
                    : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                }`}
              >
                <Icon width={16} height={16} />
                {label}
              </Link>
            );
          })}
        </nav>

        <div className="hidden items-center gap-2 md:flex">
          {user === undefined ? (
            <span className="h-9 w-40 animate-pulse rounded-full bg-slate-100" />
          ) : user ? (
            <UserMenu user={user} />
          ) : (
            <>
              <Link
                href="/signin"
                className="rounded-full px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50"
              >
                Sign in
              </Link>
              <Link
                href="/register"
                className="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"
              >
                Create account
              </Link>
            </>
          )}
        </div>

        <button
          type="button"
          className="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden"
          onClick={() => setOpen((v) => !v)}
          aria-label={open ? "Close menu" : "Open menu"}
          aria-expanded={open}
        >
          {open ? <CloseIcon /> : <MenuIcon />}
        </button>
      </div>

      {open && (
        <div className="border-t border-slate-200 bg-white px-4 pb-4 md:hidden">
          <nav className="flex flex-col py-2">
            {NAV.map(({ href, label, icon: Icon }) => (
              <Link
                key={href}
                href={href}
                onClick={() => setOpen(false)}
                className="flex items-center gap-3 rounded-lg px-3 py-3 text-slate-700 hover:bg-slate-100"
              >
                <Icon width={18} height={18} />
                {label}
              </Link>
            ))}
          </nav>
          {user ? (
            <div className="border-t border-slate-100 pt-3">
              <p className="px-3 text-sm font-semibold text-slate-900">{user.name}</p>
              <p className="px-3 text-xs text-slate-500">{user.email}</p>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <Link
                  href="/account"
                  onClick={() => setOpen(false)}
                  className="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white"
                >
                  My account
                </Link>
                <form action={signOut}>
                  <button
                    type="submit"
                    className="w-full rounded-full border border-slate-200 py-2 text-sm font-semibold text-slate-700"
                  >
                    Sign out
                  </button>
                </form>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-2">
              <Link
                href="/signin"
                onClick={() => setOpen(false)}
                className="rounded-full border border-brand-200 py-2 text-center text-sm font-semibold text-brand-700"
              >
                Sign in
              </Link>
              <Link
                href="/register"
                onClick={() => setOpen(false)}
                className="rounded-full bg-brand-600 py-2 text-center text-sm font-semibold text-white"
              >
                Create account
              </Link>
            </div>
          )}
        </div>
      )}
    </header>
  );
}

function UserMenu({ user }: { user: HeaderUser }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("mousedown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-haspopup="menu"
        className="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-slate-100"
      >
        <span className="grid h-8 w-8 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">
          {initials(user.name)}
        </span>
        <span className="max-w-32 truncate text-sm font-semibold text-slate-800">
          {user.name.split(" ")[0]}
        </span>
      </button>
      {open && (
        <div
          role="menu"
          className="absolute right-0 top-full mt-2 w-60 rounded-xl border border-slate-200 bg-white py-2 shadow-xl"
        >
          <div className="border-b border-slate-100 px-4 pb-3 pt-1">
            <p className="truncate text-sm font-semibold text-slate-900">{user.name}</p>
            <p className="truncate text-xs text-slate-500">{user.email}</p>
          </div>
          <Link
            href="/account"
            role="menuitem"
            onClick={() => setOpen(false)}
            className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
          >
            <UserIcon width={16} height={16} /> My account &amp; trips
          </Link>
          <form action={signOut}>
            <button
              type="submit"
              role="menuitem"
              className="w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
            >
              Sign out
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
