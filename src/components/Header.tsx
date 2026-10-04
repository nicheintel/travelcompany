"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { Logo } from "./Logo";
import { BedIcon, CloseIcon, MenuIcon, PackageIcon, PlaneIcon } from "./icons";

const NAV = [
  { href: "/flights", label: "Flights", icon: PlaneIcon },
  { href: "/hotels", label: "Hotels", icon: BedIcon },
  { href: "/packages", label: "Packages", icon: PackageIcon },
];

export function Header() {
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
        </div>
      )}
    </header>
  );
}
