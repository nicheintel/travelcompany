import Link from "next/link";
import { SITE_NAME, SITE_TAGLINE } from "@/lib/site";
import { Logo } from "./Logo";

const COLUMNS = [
  {
    title: "Book",
    links: [
      { href: "/flights", label: "Cheap flights" },
      { href: "/hotels", label: "Hotels" },
      { href: "/packages", label: "Vacation packages" },
      { href: "/packages", label: "Flight + Hotel + Car" },
    ],
  },
  {
    title: "Account",
    links: [
      { href: "/signin", label: "Sign in" },
      { href: "/register", label: "Create account" },
      { href: "/account", label: "My trips" },
    ],
  },
  {
    title: "Support",
    links: [
      { href: "/#faq", label: "FAQ" },
      { href: "/#why-us", label: "Why book with us" },
      { href: "/#newsletter", label: "Deal alerts" },
    ],
  },
];

export function Footer() {
  return (
    <footer className="mt-auto bg-brand-950 text-slate-300">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_repeat(3,1fr)]">
        <div className="space-y-4">
          <Logo light />
          <p className="max-w-xs text-sm leading-relaxed text-slate-400">{SITE_TAGLINE}.</p>
        </div>
        {COLUMNS.map((col) => (
          <div key={col.title}>
            <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-white">
              {col.title}
            </h3>
            <ul className="space-y-2 text-sm">
              {col.links.map((l) => (
                <li key={l.label}>
                  <Link href={l.href} className="hover:text-white">
                    {l.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <p>© {new Date().getFullYear()} {SITE_NAME}. All rights reserved.</p>
          <p>Prices shown are examples and may change until booking is confirmed.</p>
        </div>
      </div>
    </footer>
  );
}
