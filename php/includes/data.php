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
