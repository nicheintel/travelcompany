import Link from "next/link";
import { SITE_NAME_PARTS } from "@/lib/site";
import { PlaneIcon } from "./icons";

export function Logo({ light = false }: { light?: boolean }) {
  return (
    <Link href="/" className="flex items-center gap-2 font-bold text-xl tracking-tight">
      <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md">
        <PlaneIcon width={18} height={18} />
      </span>
      <span className={light ? "text-white" : "text-slate-900"}>
        {SITE_NAME_PARTS[0]}
        <span className="text-accent-500">{SITE_NAME_PARTS[1]}</span>
      </span>
    </Link>
  );
}
