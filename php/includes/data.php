<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/** Every airport with scheduled passenger flights (includes/airports.php, from OurAirports). */
function airports(): array
{
    static $all = null;
    return $all ??= require __DIR__ . '/airports.php';
}

function airport(?string $code): ?array
{
    $code = strtoupper(trim((string) $code));
    $all = airports();
    if (!isset($all[$code])) {
        return null;
    }
    [$city, $country, $name, $lat, $lon, $cc] = $all[$code];
    return compact('code', 'city', 'country', 'name', 'lat', 'lon', 'cc');
}

function distance_km(array $a, array $b): float
{
    $r = fn($d) => $d * M_PI / 180;
    $h = sin($r($b['lat'] - $a['lat']) / 2) ** 2
        + cos($r($a['lat'])) * cos($r($b['lat'])) * sin($r($b['lon'] - $a['lon']) / 2) ** 2;
    return 2 * 6371 * asin(sqrt($h));
}

/**
 * Homepage destination cards: city, country, airport, background colour, default photo.
 * Default photos are free-to-use Unsplash photos (unsplash.com/license); a photo uploaded on
 * Admin → Packages replaces them.
 */
const UNSPLASH = 'https://images.unsplash.com/photo-%s?auto=format&fit=crop&w=600&h=800&q=70';
const POPULAR_DESTINATIONS = [
    ['Paris', 'France', 'CDG', 'from-rose-400 to-purple-700', '1502602898657-3e91760cbb34'],
    ['Tokyo', 'Japan', 'NRT', 'from-fuchsia-500 to-red-600', '1540959733332-eab4deabeeaf'],
    ['Bali', 'Indonesia', 'DPS', 'from-emerald-400 to-cyan-700', '1537996194471-e657df975ab4'],
    ['Cancún', 'Mexico', 'CUN', 'from-cyan-400 to-blue-700', '1510097467424-192d713fd8b2'],
    ['Dubai', 'UAE', 'DXB', 'from-amber-400 to-orange-700', '1512453979798-5ea266f8880c'],
    ['London', 'United Kingdom', 'LHR', 'from-slate-400 to-indigo-800', '1513635269975-59663e0ac1ad'],
];

/** Uploaded photo for a destination card, else the default one. */
function destination_photo(string $code, string $unsplashId): array
{
    $own = site_image("dest:$code");
    return $own ? [$own, false] : [sprintf(UNSPLASH, $unsplashId), true];
}

const CABIN_LABELS = ['economy' => 'Economy', 'premium' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'];

/** Questions and answers for the Help center (the homepage shows the first five). */
function faq_items(): array
{
    $pct = (int) round(member_discount_rate() * 100);
    return [
        ['How do you find affordable flights?', 'We search live fares from many airlines through our flight supplier and show the best-value options first. Prices include taxes and our service fee. Flexible dates usually find lower fares.'],
        ['Do I need an account to book?', 'You can search without an account. To book, create a free account and confirm your email address — that\'s where we send your confirmations and tickets.' . ($pct > 0 ? " Members also get $pct% off flights and hotels." : '')],
        ['When is my trip confirmed?', 'Reserving is free and holds the price shown. After you pay, a travel assistant buys your ticket or room and emails you the confirmation code (for example, your airline booking reference). Your trip is confirmed when you receive it — you\'ll also see it on your trip page.'],
        ['How do I pay?', 'After reserving, pay securely online with PayPal or a debit/credit card through PayPal, using the Pay button on your trip page or in your email. We never see your card number. You can also ask a travel assistant about other options.'],
        ['What is included in a promo package?', 'Every package includes round-trip flights and a hotel stay. Some also include a rental car or extras like breakfast or tours — each package lists exactly what is included, and the price is per person.'],
        ['Can I change or cancel my booking?', 'Before paying, you can cancel your reservation for free on your trip page. After paying, changes and refunds follow the airline\'s and hotel\'s rules — contact us and we\'ll tell you what\'s possible and any fees before anything is changed.'],
        ['How long do refunds take?', 'Refunds go back to your original payment method once the airline or hotel has refunded us. This can take a few days to several weeks, depending on the supplier.'],
        ['What if the price changes before my ticket is issued?', 'Prices can change until your trip is ticketed. If it goes up, we\'ll contact you before doing anything — you can accept the new price, pick another option, or cancel for a full refund.'],
        ['What documents do I need?', 'Each traveler needs a valid passport or ID, and any visas or health documents required for the countries on the trip. Names on the booking must match the passport exactly.'],
        ['I didn\'t get my email. What should I do?', 'Check your spam or junk folder first. You can send a new confirmation link from the yellow bar at the top of the page, and see all your trips under My trips. Still missing? Contact us.'],
    ];
}
