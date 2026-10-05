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

const CABIN_LABELS = ['economy' => 'Economy', 'premium' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'];
