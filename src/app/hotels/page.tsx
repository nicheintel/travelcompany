import type { Metadata } from "next";
import Link from "next/link";
import { ComingSoon } from "@/components/ComingSoon";
import { BedIcon } from "@/components/icons";

export const metadata: Metadata = { title: "Hotels" };

export default function HotelsPage() {
  return (
    <ComingSoon
      icon={<BedIcon width={32} height={32} />}
      title="Hotel search is coming soon"
      body="We're adding hotel-only search next. In the meantime, our Flight + Hotel packages include hand-picked hotels at bundle prices."
    >
      <Link href="/packages" className="rounded-xl bg-accent-500 px-5 py-2.5 font-semibold text-white hover:bg-accent-600">
        See hotel packages
      </Link>
      <Link href="/" className="rounded-xl px-5 py-2.5 font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">
        Back to home
      </Link>
    </ComingSoon>
  );
}
