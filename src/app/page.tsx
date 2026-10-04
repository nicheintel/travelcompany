import Link from "next/link";
import {
  CarIcon,
  HeadsetIcon,
  PackageIcon,
  PlaneIcon,
  SearchIcon,
  ShieldIcon,
  TagIcon,
  UserIcon,
} from "@/components/icons";
import { PackageCard } from "@/components/PackageCard";
import { SearchTabs } from "@/components/search/SearchTabs";
import { POPULAR_DESTINATIONS } from "@/lib/destinations";
import { formatPrice } from "@/lib/format";
import { PROMO_PACKAGES } from "@/lib/packages";

const WHY_US = [
  {
    icon: TagIcon,
    title: "Low fares, no surprises",
    body: "We compare hundreds of airlines and hotels so you see the real price up front — taxes and fees included.",
  },
  {
    icon: PackageIcon,
    title: "Bundle & save",
    body: "Combine your flight, hotel and car in one booking and unlock package-only rates.",
  },
  {
    icon: HeadsetIcon,
    title: "A real travel assistant",
    body: "Our team helps you plan, change or cancel — by chat or phone, whenever you need us.",
  },
  {
    icon: ShieldIcon,
    title: "Secure booking",
    body: "Your account and payment details are protected with industry-standard encryption.",
  },
];

const STEPS = [
  { icon: SearchIcon, title: "Search", body: "Tell us where and when. We find the cheapest flights, stays and bundles." },
  { icon: TagIcon, title: "Compare", body: "Filter by price, stops, airline or hotel rating and pick what fits." },
  { icon: UserIcon, title: "Book with your account", body: "Sign in to save trips, get price alerts and check out in seconds." },
];

const FAQ = [
  {
    q: "How do you find affordable flights?",
    a: "We search many airlines and booking sources at once and sort the results so the best-value options appear first. Flexible dates usually unlock even lower fares.",
  },
  {
    q: "What is included in a promo package?",
    a: "Every package includes round-trip flights and a hotel stay. Many also include a rental car, airport transfers, breakfast or tours — check the package details.",
  },
  {
    q: "Do I need an account to book?",
    a: "You can search without an account. To book, save trips and receive member-only deals, you'll sign in or create a free account.",
  },
  {
    q: "Can I change or cancel my booking?",
    a: "It depends on the fare or hotel rules. Refundable options are clearly marked, and our assistants can help with changes.",
  },
];

export default function Home() {
  return (
    <>
      {/* Hero */}
      <section className="relative overflow-hidden bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600">
        <svg
          className="pointer-events-none absolute inset-0 h-full w-full opacity-[0.12]"
          viewBox="0 0 1200 600"
          preserveAspectRatio="xMidYMid slice"
          aria-hidden
        >
          <path
            d="M-50 480 C 250 380, 450 560, 700 420 S 1100 220, 1300 300"
            fill="none"
            stroke="white"
            strokeWidth="2"
            strokeDasharray="10 12"
          />
          <circle cx="980" cy="120" r="140" fill="white" opacity="0.4" />
          <circle cx="160" cy="80" r="60" fill="white" opacity="0.3" />
        </svg>
        <PlaneIcon
          className="pointer-events-none absolute right-[8%] top-24 hidden rotate-12 text-white/20 lg:block"
          width={120}
          height={120}
          strokeWidth={1}
        />

        <div className="relative mx-auto max-w-7xl px-4 pb-16 pt-14 sm:px-6 sm:pt-20 lg:pb-24">
          <div className="max-w-2xl text-white">
            <p className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium ring-1 ring-white/20">
              <span className="h-2 w-2 rounded-full bg-accent-400" />
              Your personal travel assistant
            </p>
            <h1 className="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
              Fly further. <span className="text-accent-400">Pay less.</span>
            </h1>
            <p className="mt-4 text-lg text-brand-100 sm:text-xl">
              Affordable flights, hand-picked hotels and money-saving Flight + Hotel + Car
              packages — all in one place.
            </p>
          </div>

          <div className="mt-10">
            <SearchTabs />
          </div>

          <ul className="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-sm text-brand-100">
            <li className="flex items-center gap-2">
              <ShieldIcon width={16} height={16} /> Secure booking
            </li>
            <li className="flex items-center gap-2">
              <TagIcon width={16} height={16} /> No hidden fees
            </li>
            <li className="flex items-center gap-2">
              <HeadsetIcon width={16} height={16} /> 24/7 travel support
            </li>
          </ul>
        </div>
      </section>

      {/* Promo banners */}
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div className="grid gap-4 md:grid-cols-3">
          <Link
            href="/packages"
            className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-accent-400 to-accent-600 p-6 text-white shadow-lg"
          >
            <PackageIcon className="absolute -bottom-4 -right-4 text-white/20" width={120} height={120} />
            <p className="text-sm font-semibold uppercase tracking-wider text-white/80">Packages</p>
            <h3 className="mt-2 text-2xl font-bold">Flight + Hotel + Car</h3>
            <p className="mt-1 text-white/90">Save up to 40% when you bundle.</p>
            <span className="mt-4 inline-block font-semibold underline-offset-4 group-hover:underline">
              See promo packages →
            </span>
          </Link>
          <Link
            href="/flights"
            className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-500 to-brand-700 p-6 text-white shadow-lg"
          >
            <PlaneIcon className="absolute -bottom-4 -right-4 text-white/20" width={120} height={120} />
            <p className="text-sm font-semibold uppercase tracking-wider text-white/80">Flights</p>
            <h3 className="mt-2 text-2xl font-bold">Weekend getaways</h3>
            <p className="mt-1 text-white/90">Round trips from {formatPrice(199)}.</p>
            <span className="mt-4 inline-block font-semibold underline-offset-4 group-hover:underline">
              Find cheap flights →
            </span>
          </Link>
          <Link
            href="/register"
            className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 p-6 text-white shadow-lg"
          >
            <UserIcon className="absolute -bottom-4 -right-4 text-white/20" width={120} height={120} />
            <p className="text-sm font-semibold uppercase tracking-wider text-white/80">Members</p>
            <h3 className="mt-2 text-2xl font-bold">Extra 10% off</h3>
            <p className="mt-1 text-white/90">Free account, member-only prices.</p>
            <span className="mt-4 inline-block font-semibold underline-offset-4 group-hover:underline">
              Create free account →
            </span>
          </Link>
        </div>
      </section>

      {/* Popular destinations */}
      <section className="mx-auto max-w-7xl px-4 pb-14 sm:px-6">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-slate-900 sm:text-3xl">Popular destinations</h2>
            <p className="mt-1 text-slate-600">Round-trip fares from New York this season.</p>
          </div>
          <Link href="/flights" className="hidden text-sm font-semibold text-brand-700 hover:underline sm:block">
            Explore all flights →
          </Link>
        </div>
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
          {POPULAR_DESTINATIONS.map((d) => (
            <Link
              key={d.code}
              href={`/flights?from=JFK&to=${d.code}`}
              className={`group relative flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br ${d.gradient} p-4 text-white shadow-md transition hover:-translate-y-1 hover:shadow-xl`}
            >
              <span className="absolute right-3 top-3 rounded-md bg-black/20 px-2 py-0.5 font-mono text-xs font-semibold backdrop-blur">
                {d.code}
              </span>
              <p className="text-xs font-medium text-white/80">{d.country}</p>
              <p className="text-lg font-bold">{d.city}</p>
              <p className="mt-1 text-sm">
                from <span className="font-bold">{formatPrice(d.fromPrice)}</span>
              </p>
            </Link>
          ))}
        </div>
      </section>

      {/* Featured packages */}
      <section className="bg-white py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          <div className="mb-6 flex items-end justify-between gap-4">
            <div>
              <p className="text-sm font-semibold uppercase tracking-wider text-accent-600">Promo packages</p>
              <h2 className="text-2xl font-bold text-slate-900 sm:text-3xl">This week&apos;s best bundles</h2>
            </div>
            <Link href="/packages" className="text-sm font-semibold text-brand-700 hover:underline">
              View all packages →
            </Link>
          </div>
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {PROMO_PACKAGES.slice(0, 4).map((pkg) => (
              <PackageCard key={pkg.id} pkg={pkg} />
            ))}
          </div>
        </div>
      </section>

      {/* Why us */}
      <section id="why-us" className="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
        <h2 className="text-center text-2xl font-bold text-slate-900 sm:text-3xl">
          Why travelers book with us
        </h2>
        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {WHY_US.map(({ icon: Icon, title, body }) => (
            <div key={title} className="rounded-2xl border border-slate-200 bg-white p-6">
              <span className="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-600">
                <Icon width={24} height={24} />
              </span>
              <h3 className="mt-4 font-semibold text-slate-900">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* How it works */}
      <section className="bg-brand-50 py-16">
        <div className="mx-auto max-w-7xl px-4 sm:px-6">
          <h2 className="text-center text-2xl font-bold text-slate-900 sm:text-3xl">How it works</h2>
          <ol className="mt-10 grid gap-6 md:grid-cols-3">
            {STEPS.map(({ icon: Icon, title, body }, i) => (
              <li key={title} className="relative rounded-2xl bg-white p-6 shadow-sm">
                <span className="absolute right-5 top-4 text-5xl font-black text-brand-100">{i + 1}</span>
                <span className="grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white">
                  <Icon width={22} height={22} />
                </span>
                <h3 className="mt-4 text-lg font-semibold text-slate-900">{title}</h3>
                <p className="mt-2 text-sm text-slate-600">{body}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* FAQ */}
      <section id="faq" className="mx-auto max-w-3xl scroll-mt-20 px-4 py-16 sm:px-6">
        <h2 className="text-center text-2xl font-bold text-slate-900 sm:text-3xl">Frequently asked questions</h2>
        <div className="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
          {FAQ.map(({ q, a }) => (
            <details key={q} className="group p-5">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                {q}
                <span className="text-xl text-brand-600 transition group-open:rotate-45">+</span>
              </summary>
              <p className="mt-3 text-sm leading-relaxed text-slate-600">{a}</p>
            </details>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section id="newsletter" className="scroll-mt-20 px-4 pb-16 sm:px-6">
        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-r from-brand-700 to-brand-500 px-6 py-12 text-white sm:px-12">
          <CarIcon className="absolute -right-6 -top-6 text-white/10" width={200} height={200} />
          <div className="relative grid items-center gap-8 lg:grid-cols-2">
            <div>
              <h2 className="text-3xl font-bold">Get member-only deals</h2>
              <p className="mt-2 text-brand-100">
                Create a free account to save searches, track prices and unlock secret fares.
              </p>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row lg:justify-end">
              <Link
                href="/register"
                className="rounded-xl bg-accent-500 px-6 py-3 text-center font-bold text-white shadow-lg hover:bg-accent-600"
              >
                Create free account
              </Link>
              <Link
                href="/signin"
                className="rounded-xl bg-white/10 px-6 py-3 text-center font-semibold text-white ring-1 ring-white/30 hover:bg-white/20"
              >
                I already have an account
              </Link>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
