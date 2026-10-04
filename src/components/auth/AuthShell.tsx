import type { ReactNode } from "react";
import { CheckIcon, PlaneIcon } from "../icons";

const PERKS = [
  "Member-only prices — extra savings on flights & packages",
  "Save trips and pick up where you left off",
  "Price alerts when fares to your destination drop",
  "Faster checkout with your saved traveler details",
];

export function AuthShell({
  title,
  subtitle,
  notice,
  children,
}: {
  title: string;
  subtitle: ReactNode;
  notice?: string;
  children: ReactNode;
}) {
  return (
    <section className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_1.1fr] lg:py-16">
      <div className="relative hidden overflow-hidden rounded-3xl bg-gradient-to-br from-brand-900 via-brand-700 to-brand-500 p-10 text-white lg:block">
        <PlaneIcon
          className="absolute -right-6 top-10 rotate-12 text-white/10"
          width={220}
          height={220}
          strokeWidth={1}
        />
        <div className="relative">
          <p className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium ring-1 ring-white/20">
            <span className="h-2 w-2 rounded-full bg-accent-400" />
            Free membership
          </p>
          <h2 className="mt-6 text-3xl font-extrabold leading-tight">
            Travel smarter with your <span className="text-accent-400">free account</span>
          </h2>
          <ul className="mt-8 space-y-4">
            {PERKS.map((perk) => (
              <li key={perk} className="flex items-start gap-3 text-brand-50">
                <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/15">
                  <CheckIcon width={14} height={14} />
                </span>
                {perk}
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="mx-auto w-full max-w-md self-center">
        {notice && (
          <p className="mb-5 rounded-xl bg-accent-500/10 px-4 py-3 text-sm font-medium text-accent-600 ring-1 ring-accent-500/30">
            {notice}
          </p>
        )}
        <h1 className="text-3xl font-bold text-slate-900">{title}</h1>
        <p className="mt-2 text-slate-600">{subtitle}</p>
        <div className="mt-8">{children}</div>
      </div>
    </section>
  );
}
