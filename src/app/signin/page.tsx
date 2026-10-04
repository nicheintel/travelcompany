import type { Metadata } from "next";
import { ComingSoon } from "@/components/ComingSoon";
import { UserIcon } from "@/components/icons";

export const metadata: Metadata = { title: "Sign in" };

export default function SignInPage() {
  return (
    <ComingSoon
      icon={<UserIcon width={32} height={32} />}
      title="Sign in"
      body="Account sign-in is the next feature we're building. Soon you'll be able to save trips, track prices and book in a few clicks."
    />
  );
}
