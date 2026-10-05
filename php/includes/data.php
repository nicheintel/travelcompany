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

const POPULAR_DESTINATIONS = [
    ['Paris', 'France', 'CDG', 'from-rose-400 to-purple-700'],
    ['Tokyo', 'Japan', 'NRT', 'from-fuchsia-500 to-red-600'],
    ['Bali', 'Indonesia', 'DPS', 'from-emerald-400 to-cyan-700'],
    ['Cancún', 'Mexico', 'CUN', 'from-cyan-400 to-blue-700'],
    ['Dubai', 'UAE', 'DXB', 'from-amber-400 to-orange-700'],
    ['London', 'United Kingdom', 'LHR', 'from-slate-400 to-indigo-800'],
];

const PACKAGE_CATEGORIES = ['Beach', 'City', 'Adventure', 'Family', 'Romantic'];

function promo_packages(): array
{
    $p = fn(...$a) => array_combine(
        ['id', 'title', 'destination', 'code', 'country', 'nights', 'hotel', 'stars', 'car', 'category', 'price', 'original', 'perks', 'gradient', 'badge'],
        $a,
    );
    return [
        $p('cancun-all-inclusive', 'Cancún All-Inclusive Escape', 'Cancún', 'CUN', 'Mexico', 5, 'Riviera Coral Beach Resort', 5, false, 'Beach', 899, 1349, ['Round-trip flights', 'All meals & drinks', 'Airport transfer'], 'from-cyan-400 via-sky-500 to-blue-700', 'Best seller'),
        $p('bali-villa-car', 'Bali Villa + Scooter & Car', 'Bali', 'DPS', 'Indonesia', 7, 'Ubud Jungle Pool Villas', 4, true, 'Romantic', 1129, 1690, ['Round-trip flights', 'Private pool villa', '7-day car rental'], 'from-emerald-400 via-teal-500 to-cyan-700', 'Save 33%'),
        $p('paris-city-break', 'Paris City Break', 'Paris', 'CDG', 'France', 4, 'Hôtel Le Marais Boutique', 4, false, 'City', 749, 1020, ['Round-trip flights', 'Daily breakfast', 'Seine river cruise'], 'from-rose-400 via-pink-500 to-purple-700', null),
        $p('orlando-family-fun', 'Orlando Family Fun + Car', 'Orlando', 'MCO', 'United States', 6, 'Lakeside Family Suites', 4, true, 'Family', 1049, 1480, ['Round-trip flights', 'Kids stay free', 'SUV rental included'], 'from-amber-300 via-orange-400 to-rose-600', 'Family pick'),
        $p('dubai-luxury', 'Dubai Luxury Stopover', 'Dubai', 'DXB', 'United Arab Emirates', 4, 'Marina Skyline Hotel', 5, false, 'City', 1199, 1650, ['Round-trip flights', 'Desert safari', 'Breakfast included'], 'from-yellow-300 via-amber-500 to-orange-700', null),
        $p('tokyo-explorer', 'Tokyo Explorer', 'Tokyo', 'NRT', 'Japan', 6, 'Shinjuku Garden Hotel', 4, false, 'City', 1399, 1890, ['Round-trip flights', '7-day rail pass', 'Airport limousine bus'], 'from-fuchsia-400 via-rose-500 to-red-600', null),
        $p('hawaii-road-trip', 'Hawaiʻi Beach & Road Trip', 'Honolulu', 'HNL', 'United States', 7, 'Waikiki Shore Resort', 4, true, 'Adventure', 1549, 2190, ['Round-trip flights', 'Convertible rental', 'Snorkel tour'], 'from-sky-300 via-cyan-500 to-teal-700', 'Limited time'),
        $p('cebu-island-hopping', 'Cebu Island Hopping', 'Cebu', 'CEB', 'Philippines', 5, 'Mactan Blue Lagoon Resort', 4, false, 'Beach', 689, 980, ['Round-trip flights', 'Island hopping tour', 'Breakfast included'], 'from-teal-300 via-emerald-500 to-green-700', null),
        $p('rome-romance', 'Roman Holiday for Two', 'Rome', 'FCO', 'Italy', 5, 'Trastevere Charm Hotel', 4, true, 'Romantic', 959, 1310, ['Round-trip flights', 'Tuscany day-trip car', 'Wine tasting'], 'from-orange-300 via-red-400 to-rose-700', null),
    ];
}

function find_package(string $id): ?array
{
    foreach (promo_packages() as $p) {
        if ($p['id'] === $id) return $p;
    }
    return null;
}

const CABIN_LABELS = ['economy' => 'Economy', 'premium' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'];
