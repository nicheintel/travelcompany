import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { Footer } from "@/components/Footer";
import { Suspense } from "react";
import { Header } from "@/components/Header";
import { getCurrentUser } from "@/lib/server/dal";
import { SITE_NAME, SITE_TAGLINE } from "@/lib/site";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: {
    default: `${SITE_NAME} — Cheap flights, hotels & holiday packages`,
    template: `%s | ${SITE_NAME}`,
  },
  description: SITE_TAGLINE,
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="en"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col font-sans">
        <Suspense fallback={<Header />}>
          <HeaderWithUser />
        </Suspense>
        <main className="flex-1">{children}</main>
        <Footer />
      </body>
    </html>
  );
}

/** Reads the session in its own Suspense boundary so the rest of the page can stream first. */
async function HeaderWithUser() {
  const user = await getCurrentUser();
  return <Header user={user ? { name: user.name, email: user.email, isAdmin: user.role === "admin" } : null} />;
}
