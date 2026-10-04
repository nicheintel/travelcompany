<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * Sample flights and hotels, shown until Duffel / LiteAPI keys are configured.
 * Seeded, so the same search always gives the same results.
 */

/** Seeded random number in [0, 1). Call seed_random() first. */
function rnd(): float
{
    return mt_rand() / (mt_getrandmax() + 1);
}

function seed_random(string $text): void
{
    mt_srand(crc32($text));
}

const SAMPLE_AIRLINES = [
    ['SK', 'SkyBridge Air', '#1c54f0'], ['PA', 'Pacific Atlas', '#0f766e'], ['NV', 'Nova Airways', '#7c3aed'],
    ['SU', 'SunJet', '#f06c06'], ['MR', 'Meridian Airlines', '#be123c'], ['BL', 'BlueLine Express', '#0369a1'],
];
const HUBS = ['DXB', 'DOH', 'IST', 'AMS', 'SIN', 'HKG', 'ORD', 'LHR', 'ICN'];
const CABIN_MULTIPLIER = ['economy' => 1, 'premium' => 1.6, 'business' => 3.2, 'first' => 5.5];

function sample_leg(array $from, array $to, string $date, array $airline): array
{
    $km = distance_km($from, $to);
    $roll = rnd();
    $stops = $km > 4000 ? ($roll < 0.35 ? 0 : ($roll < 0.85 ? 1 : 2)) : ($roll < 0.65 ? 0 : 1);
    $hubs = array_values(array_filter(HUBS, fn($c) => $c !== $from['code'] && $c !== $to['code']));
    $stopCities = [];
    for ($i = 0; $i < $stops; $i++) {
        $stopCities[] = $hubs[(int) floor(rnd() * count($hubs))];
    }
    $depart = (int) (round((300 + rnd() * 1020) / 5) * 5);
    $duration = (int) round($km / 820 * 60 + 35) + $stops * (int) round(60 + rnd() * 180);
    return [
        'from' => $from['code'], 'to' => $to['code'], 'date' => $date,
        'depart' => $depart, 'arrive' => ($depart + $duration) % 1440, 'day_offset' => intdiv($depart + $duration, 1440),
        'duration' => $duration, 'stops' => $stops, 'stop_cities' => $stopCities,
        'flight_number' => $airline['code'] . (100 + (int) floor(rnd() * 899)),
    ];
}

function sample_flights(array $s): array
{
    seed_random("{$s['from']['code']}-{$s['to']['code']}-{$s['depart']}-{$s['return']}-{$s['cabin']}");
    $base = 49 + distance_km($s['from'], $s['to']) * 0.085;
    $offers = [];
    $count = 10 + (int) floor(rnd() * 6);
    for ($i = 0; $i < $count; $i++) {
        [$code, $name, $color] = SAMPLE_AIRLINES[(int) floor(rnd() * count(SAMPLE_AIRLINES))];
        $airline = ['code' => $code, 'name' => $name, 'color' => $color, 'logo' => null];
        $out = sample_leg($s['from'], $s['to'], $s['depart'], $airline);
        $in = $s['return'] ? sample_leg($s['to'], $s['from'], $s['return'], $airline) : null;
        $pp = $base * (1.15 - $out['stops'] * 0.12) * (0.75 + rnd() * 0.6) * CABIN_MULTIPLIER[$s['cabin']];
        $pp = (int) round($in ? $pp * 1.85 : $pp);
        $child = (int) round($pp * 0.75);
        $seats = 1 + (int) floor(rnd() * 9);
        $offers[] = [
            'id' => "$code-$i", 'airline' => $airline, 'outbound' => $out, 'inbound' => $in,
            'per_adult' => $pp, 'per_child' => $child,
            'total' => $pp * $s['adults'] + $child * $s['children'],
            'seats_left' => $seats, 'refundable' => rnd() > 0.6,
        ];
    }
    usort($offers, fn($a, $b) => $a['total'] <=> $b['total']);
    return $offers;
}

const AMENITY_LABELS = [
    'wifi' => 'Free Wi-Fi', 'pool' => 'Pool', 'breakfast' => 'Breakfast included', 'gym' => 'Fitness center',
    'spa' => 'Spa', 'parking' => 'Free parking', 'shuttle' => 'Airport shuttle',
];
const CITY_COST = [
    'JFK' => 1.6, 'SFO' => 1.5, 'LHR' => 1.5, 'HNL' => 1.4, 'CDG' => 1.4, 'NRT' => 1.3, 'DXB' => 1.3, 'SIN' => 1.3, 'AMS' => 1.3,
    'MIA' => 1.2, 'LAX' => 1.2, 'SYD' => 1.2, 'FCO' => 1.1, 'BCN' => 1.1, 'HKG' => 1.2, 'DOH' => 1.1, 'ORD' => 1.1,
    'CUN' => 0.9, 'IST' => 0.7, 'GRU' => 0.7, 'KUL' => 0.6, 'BKK' => 0.6, 'DPS' => 0.6, 'MNL' => 0.6, 'CEB' => 0.6, 'DEL' => 0.5,
];
const HOTEL_TAX_RATE = 0.12;

function sample_hotels(array $s): array
{
    $nameA = ['Grand', 'Royal', 'Harbor', 'Garden', 'Skyline', 'Riverside', 'Central', 'Palm', 'Urban', 'Heritage', 'Azure', 'Lotus'];
    $nameB = ['Hotel', 'Suites', 'Resort', 'Inn', 'Residences', 'Boutique Hotel', 'Plaza', 'Lodge'];
    $areas = ['City Center', 'Old Town', 'Waterfront', 'Business District', 'Arts Quarter', 'Near the Airport', 'Beachfront', 'Shopping District'];
    $rooms = ['Standard Double Room', 'Deluxe King Room', 'Superior Twin Room', 'Junior Suite', 'Family Room'];
    $gradients = ['from-sky-400 to-blue-700', 'from-emerald-400 to-teal-700', 'from-amber-300 to-orange-600', 'from-rose-400 to-purple-700', 'from-indigo-400 to-slate-800', 'from-cyan-300 to-sky-700'];
    $starMult = [0, 0.45, 0.6, 1, 1.6, 2.8];
    $cost = CITY_COST[$s['city']['code']] ?? 1;

    // Hotel identity is seeded by city; prices vary with the date.
    mt_srand(crc32('dates-' . $s['city']['code'] . $s['checkin']));
    $dateFactors = [];
    for ($i = 0; $i < 30; $i++) {
        $dateFactors[] = 0.85 + rnd() * 0.3;
    }
    seed_random('hotels-' . $s['city']['code']);
    $count = 14 + (int) floor(rnd() * 5);
    $used = [];
    $hotels = [];
    for ($i = 0; $i < $count; $i++) {
        do {
            $a = $nameA[(int) floor(rnd() * count($nameA))];
            $b = $nameB[(int) floor(rnd() * count($nameB))];
            $name = rnd() < 0.5 ? "$a $b {$s['city']['city']}" : "The $a $b";
        } while (isset($used[$name]));
        $used[$name] = true;
        $roll = rnd();
        $stars = $roll < 0.1 ? 2 : ($roll < 0.4 ? 3 : ($roll < 0.8 ? 4 : 5));
        $rating = round(min(9.8, 6.4 + $stars * 0.45 + rnd() * 1.6), 1);
        $nightly = (int) round((55 + rnd() * 70) * $starMult[$stars] * $cost * $dateFactors[$i % 30]);
        $onSale = rnd() < 0.35;
        $amenities = [];
        foreach (array_keys(AMENITY_LABELS) as $am) {
            $p = $am === 'wifi' ? 0.95 : (in_array($am, ['pool', 'spa'], true) ? 0.15 + $stars * 0.12 : 0.4);
            if (rnd() < $p) $amenities[] = $am;
        }
        $hotels[] = [
            'id' => "{$s['city']['code']}-$i", 'name' => $name, 'stars' => $stars, 'rating' => $rating,
            'reviews' => 80 + (int) floor(rnd() * 4200),
            'area' => $areas[(int) floor(rnd() * count($areas))],
            'distance' => round(0.2 + rnd() * 9, 1),
            'room' => $rooms[(int) floor(rnd() * count($rooms))],
            'nightly' => $nightly,
            'original' => $onSale ? (int) round($nightly * (1.15 + rnd() * 0.3)) : null,
            'amenities' => $amenities,
            'free_cancel' => rnd() < 0.6,
            'gradient' => $gradients[$i % count($gradients)],
            'photo' => null,
            'stay_total' => null,
        ];
    }
    return $hotels;
}

function rating_label(float $r): string
{
    return $r >= 9 ? 'Exceptional' : ($r >= 8.5 ? 'Excellent' : ($r >= 8 ? 'Very good' : ($r >= 7 ? 'Good' : 'Pleasant')));
}
