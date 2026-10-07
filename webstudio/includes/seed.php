<?php
/**
 * Starter content, added once on the first run. Everything here can be changed or deleted on
 * the admin dashboard. The portfolio items are marked "Design concept" until you replace them
 * with real client projects.
 */
declare(strict_types=1);
defined('WS_APP') || exit;

function seed_content(PDO $pdo): void
{
    $now = now_utc();

    $packages = [
        ['Starter', 'A sharp one-page website to get your business online fast.', 4999, 'one-time', 0,
            "One-page website (up to 6 sections)\nMobile-friendly design\nContact form + click-to-call button\nGoogle Maps & social media links\nBasic SEO setup\n2 rounds of revisions\nReady in 5–7 days"],
        ['Business', 'The complete website for a growing business.', 12999, 'one-time', 1,
            "Up to 5 pages\nCustom design in your brand colors\nInquiry & booking request forms\nMessenger / WhatsApp chat button\nGoogle Business Profile setup\nSEO setup for every page\n3 rounds of revisions\nReady in 10–14 days"],
        ['Online Store', 'Sell your products online, day and night.', 24999, 'one-time', 0,
            "Up to 30 products (add more anytime)\nShopping cart & checkout\nOnline payment setup\nOrder notifications by email\nProduct categories & search\nVideo guide: manage your own store\n3 rounds of revisions\nReady in 3–4 weeks"],
    ];
    $st = $pdo->prepare('INSERT INTO packages (name, tagline, price, price_note, is_featured, features, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)');
    foreach ($packages as $i => $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], ($i + 1) * 10, $now, $now]);
    }

    $faqs = [
        ['How long does it take to build my website?',
            'Most one-page websites are ready in 5–7 days and business websites in 10–14 days. Online stores take about 3–4 weeks. You get a clear timeline before we start, and we keep you updated along the way.'],
        ['What do I need to prepare?',
            "Just your logo (if you have one), a few photos and a short description of your business. Don't have everything yet? No problem — we can help write your text and find quality photos."],
        ['Do you take care of the domain name and hosting?',
            "Yes. We can register your domain (like yourbusiness.com) and set up fast, secure hosting for you, or connect a domain you already own. Domains and hosting have small yearly fees — we'll explain them upfront, so there are no surprises."],
        ['How does payment work?',
            "We ask for a 50% down payment to start, and the remaining 50% once you're happy with your website — before it goes live. We'll send you the payment options with your quote."],
        ['Can I update the website myself?',
            "Absolutely. We can set up your site so you can change text, photos and products yourself, and we'll show you how. Prefer not to? We can handle updates for you at an affordable monthly rate."],
        ["What if I don't like the design?",
            "You'll see a design preview before we build, so changes are easy to make early. Every package includes revision rounds, and we'll work with you until it feels right for your business."],
        ['Will my website show up on Google?',
            'Every website includes SEO basics: clear page titles and descriptions, fast loading, and a sitemap submitted to Google. Rankings take time, but your site will be built on the right foundation from day one.'],
    ];
    $st = $pdo->prepare('INSERT INTO faqs (question, answer, is_active, sort_order, created_at, updated_at) VALUES (?, ?, 1, ?, ?, ?)');
    foreach ($faqs as $i => $f) {
        $st->execute([$f[0], $f[1], ($i + 1) * 10, $now, $now]);
    }

    $projects = [
        ['Brew & Bloom Café', 'Restaurants', 'Warm, menu-first website with online table reservations.', 'sunset'],
        ['Glow Studio Salon', 'Beauty & Wellness', 'Elegant, booking-ready site that shows off services and prices.', 'rose'],
        ['BrightSmile Dental', 'Health & Clinics', 'Calm, trustworthy clinic website with appointment requests.', 'teal'],
        ['Thread & Co.', 'Online Stores', 'Bold fashion store with product filters and quick checkout.', 'midnight'],
        ['Casa Verde Homes', 'Real Estate', 'Property listings with photo galleries and inquiry forms.', 'forest'],
        ['IronPeak Fitness', 'Fitness', 'High-energy gym website with class schedules and membership plans.', 'volt'],
    ];
    $st = $pdo->prepare('INSERT INTO projects (title, category, summary, theme, is_concept, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 1, ?, ?, ?)');
    foreach ($projects as $i => $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], ($i + 1) * 10, $now, $now]);
    }
}
