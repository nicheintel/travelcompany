import Link from "next/link";
import type { ReactNode } from "react";

export function ComingSoon({
  icon,
  title,
  body,
  children,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  children?: ReactNode;
}) {
  return (
    <section className="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center">
      <span className="grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600">{icon}</span>
      <h1 className="mt-6 text-3xl font-bold text-slate-900">{title}</h1>
      <p className="mt-3 text-slate-600">{body}</p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        {children ?? (
          <Link href="/" className="rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white hover:bg-brand-700">
            Back to home
          </Link>
        )}
      </div>
    </section>
  );
}
