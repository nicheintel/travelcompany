import type { Metadata } from "next";
import { ComingSoon } from "@/components/ComingSoon";
import { UserIcon } from "@/components/icons";

export const metadata: Metadata = { title: "Create account" };

export default function RegisterPage() {
  return (
    <ComingSoon
      icon={<UserIcon width={32} height={32} />}
      title="Create your free account"
      body="Registration is coming next. Members will get saved trips, price alerts and member-only deals."
    />
  );
}
