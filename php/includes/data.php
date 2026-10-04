<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/** code => [city, country, airport name, lat, lon, ISO country code] */
const AIRPORTS = [
    'JFK' => ['New York', 'United States', 'John F. Kennedy Intl', 40.64, -73.78, 'US'],
    'LAX' => ['Los Angeles', 'United States', 'Los Angeles Intl', 33.94, -118.41, 'US'],
    'ORD' => ['Chicago', 'United States', "O'Hare Intl", 41.98, -87.9, 'US'],
    'MIA' => ['Miami', 'United States', 'Miami Intl', 25.79, -80.29, 'US'],
    'SFO' => ['San Francisco', 'United States', 'San Francisco Intl', 37.62, -122.38, 'US'],
    'LAS' => ['Las Vegas', 'United States', 'Harry Reid Intl', 36.08, -115.15, 'US'],
    'MCO' => ['Orlando', 'United States', 'Orlando Intl', 28.43, -81.31, 'US'],
    'HNL' => ['Honolulu', 'United States', 'Daniel K. Inouye Intl', 21.32, -157.92, 'US'],
    'YYZ' => ['Toronto', 'Canada', 'Toronto Pearson Intl', 43.68, -79.63, 'CA'],
    'CUN' => ['Cancún', 'Mexico', 'Cancún Intl', 21.04, -86.87, 'MX'],
    'LHR' => ['London', 'United Kingdom', 'Heathrow', 51.47, -0.45, 'GB'],
    'CDG' => ['Paris', 'France', 'Charles de Gaulle', 49.01, 2.55, 'FR'],
    'FCO' => ['Rome', 'Italy', 'Leonardo da Vinci–Fiumicino', 41.8, 12.25, 'IT'],
    'BCN' => ['Barcelona', 'Spain', 'Barcelona–El Prat', 41.3, 2.08, 'ES'],
    'AMS' => ['Amsterdam', 'Netherlands', 'Schiphol', 52.31, 4.76, 'NL'],
    'IST' => ['Istanbul', 'Türkiye', 'Istanbul Airport', 41.26, 28.74, 'TR'],
    'DXB' => ['Dubai', 'United Arab Emirates', 'Dubai Intl', 25.25, 55.36, 'AE'],
    'DOH' => ['Doha', 'Qatar', 'Hamad Intl', 25.27, 51.61, 'QA'],
    'SIN' => ['Singapore', 'Singapore', 'Changi', 1.36, 103.99, 'SG'],
    'BKK' => ['Bangkok', 'Thailand', 'Suvarnabhumi', 13.69, 100.75, 'TH'],
    'DPS' => ['Bali', 'Indonesia', 'Ngurah Rai Intl', -8.75, 115.17, 'ID'],
    'KUL' => ['Kuala Lumpur', 'Malaysia', 'Kuala Lumpur Intl', 2.74, 101.7, 'MY'],
    'MNL' => ['Manila', 'Philippines', 'Ninoy Aquino Intl', 14.51, 121.02, 'PH'],
    'CEB' => ['Cebu', 'Philippines', 'Mactan–Cebu Intl', 10.31, 123.98, 'PH'],
    'HKG' => ['Hong Kong', 'Hong Kong', 'Hong Kong Intl', 22.31, 113.91, 'HK'],
    'NRT' => ['Tokyo', 'Japan', 'Narita Intl', 35.77, 140.39, 'JP'],
    'ICN' => ['Seoul', 'South Korea', 'Incheon Intl', 37.46, 126.44, 'KR'],
    'DEL' => ['New Delhi', 'India', 'Indira Gandhi Intl', 28.56, 77.1, 'IN'],
    'SYD' => ['Sydney', 'Australia', 'Kingsford Smith', -33.94, 151.18, 'AU'],
    'GRU' => ['São Paulo', 'Brazil', 'Guarulhos Intl', -23.43, -46.47, 'BR'],
];

function airport(?string $code): ?array
{
    $code = strtoupper(trim((string) $code));
    if (!isset(AIRPORTS[$code])) {
        return null;
    }
    [$city, $country, $name, $lat, $lon, $cc] = AIRPORTS[$code];
    return compact('code', 'city', 'country', 'name', 'lat', 'lon', 'cc');
}

/** Airports as JSON for the search boxes' autocomplete. */
function airports_json(): string
{
    $list = [];
    foreach (AIRPORTS as $code => [$city, $country, $name]) {
        $list[] = ['code' => $code, 'city' => $city, 'country' => $country, 'name' => $name];
    }
    return json_encode($list, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
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
