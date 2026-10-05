<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * Booking prices. Always rebuilt on the server from the search parameters —
 * the browser only says *what* to book, never the price.
 */

const BOOKING_KINDS = ['flight', 'hotel', 'package'];

function slots(int $adults, int $children, int $infants = 0): array
{
    $out = [];
    for ($i = 1; $i <= $adults; $i++) $out[] = ['label' => "Adult $i", 'dob' => true];
    for ($i = 1; $i <= $children; $i++) $out[] = ['label' => "Child $i", 'dob' => true];
    for ($i = 1; $i <= $infants; $i++) $out[] = ['label' => "Infant $i", 'dob' => true];
    return $out;
}

function finish_quote(array $q): array
{
    $subtotal = array_sum(array_column($q['lines'], 'amount'));
    $rate = empty($q['no_discount']) ? member_discount_rate() : 0.0;
    $discount = (int) round($subtotal * $rate);
    return $q + ['subtotal' => $subtotal, 'discount' => $discount, 'discount_rate' => $rate, 'total' => $subtotal - $discount];
}

/**
 * Turns the airline's fare-rule messages ("Change fee (before departure): 193 PLN", "Refundable with penalty", …)
 * into short notes plus one line per kind of fee, showing the lowest fee converted to US dollars.
 */
function fare_policy(array $messages): ?array
{
    $notes = [];
    $fees = [];
    foreach ($messages as $msg) {
        $msg = trim((string) $msg);
        if ($msg === '') continue;
        if (preg_match('/^(.+?):\s*([\d.,]+)\s*([A-Z]{3})$/', $msg, $m)) {
            $usd = to_usd((float) str_replace(',', '', $m[2]), $m[3]);
            $label = trim($m[1]);
            if ($usd === null) { $notes[$msg] = true; continue; } // unknown currency: show as the airline wrote it
            $fees[$label] = isset($fees[$label]) ? min($fees[$label], $usd) : $usd;
        } else {
            $notes[$msg] = true;
        }
    }
    if (!$notes && !$fees) return null;
    $rows = [];
    foreach ($fees as $label => $usd) $rows[] = ['label' => $label, 'from' => (int) ceil($usd)];
    return ['notes' => array_keys($notes), 'fees' => $rows];
}

function flight_quote(array $params): ?array
{
    $p = parse_flight_params($params);
    if (!$p['search'] || flights_on_hold()) return null;
    $offerId = (string) ($params['offer'] ?? '');
    $cabin = CABIN_LABELS[$p['cabin']];
    $cost = null;
    $note = null;

    $extraFacts = [];
    if (flight_supplier() === 'liteapi') {
        // The offer exactly as LiteAPI returned it in the search (kept on our server until it expires).
        $o = find_flight_offer($offerId);
        if (!$o) return null;
        $adults = $o['adults'];
        $children = $o['children'];
        $infants = $o['infants'];
        $title = "{$o['origin_city']} → {$o['destination_city']}";
        $lines = [['label' => 'Flight for ' . plural($adults + $children + $infants, 'traveler'), 'amount' => $o['total']]];
        $cost = $o['cost'];
        $note = "Live airline fare. Fares can change until your ticket is issued — we'll confirm before charging any difference.";
        $bag = $o['baggage'];
        $policy = fare_policy($o['terms']);
    } elseif (duffel_enabled()) {
        // Trust the airline offer itself (route, passengers, price) rather than the URL.
        $o = duffel_offer($offerId);
        if (!$o) return null;
        $adults = $o['adults'];
        $children = $o['children'];
        $infants = $o['infants'];
        $title = "{$o['origin_city']} → {$o['destination_city']}";
        $people = $adults + $children + $infants;
        $lines = [['label' => 'Flight for ' . plural($people, 'traveler'), 'amount' => $o['total']]];
        $cost = $o['cost'];
        $note = "Live airline fare. Fares can change until your ticket is issued — we'll confirm before charging any difference.";
    } else {
        if (!demo_mode()) return null; // never sell made-up flights
        $o = null;
        foreach (sample_flights($p['search']) as $offer) {
            if ($offer['id'] === $offerId) $o = $offer;
        }
        if (!$o) return null;
        $adults = $p['adults'];
        $children = $p['children'];
        $infants = $p['infants'];
        $title = "{$p['from']['city']} → {$p['to']['city']}";
        $lines = [['label' => "$adults × adult fare", 'amount' => $o['per_adult'] * $adults]];
        if ($children) $lines[] = ['label' => "$children × child fare", 'amount' => $o['per_child'] * $children];
        if ($infants) $lines[] = ['label' => "$infants × infant fare (on lap)", 'amount' => $o['per_infant'] * $infants];
    }

    $facts = [['Depart', fmt_date($o['outbound']['date'])]];
    if ($o['inbound']) $facts[] = ['Return', fmt_date($o['inbound']['date'])];
    array_push($facts, ['Travelers', (string) ($adults + $children + $infants) . ($infants ? ' (incl. ' . plural($infants, 'infant') . ')' : '')], ['Cabin', $cabin], ['Fare', $o['refundable'] ? 'Refundable' : 'Non-refundable'], ...$extraFacts);

    return finish_quote([
        'kind' => 'flight',
        'title' => $title,
        'subtitle' => "{$o['airline']['name']} · " . ($o['inbound'] ? 'Round trip' : 'One way') . " · $cabin",
        'start_date' => $o['outbound']['date'],
        'end_date' => $o['inbound']['date'] ?? null,
        'slots' => slots($adults, $children, $infants),
        'lines' => $lines,
        'facts' => $facts,
        'flight' => ['airline' => $o['airline'], 'outbound' => $o['outbound'], 'inbound' => $o['inbound']],
        'query' => $p['query'] . '&offer=' . rawurlencode($o['id']),
        'note' => $note,
        'supplier' => $cost,
        // Real allowance from the supplier (LiteAPI); null when the supplier doesn't say.
        'baggage' => $bag ?? null,
        // The airline's change/cancellation rules, tidied up (fees converted to USD).
        'policy' => $policy ?? null,
    ]);
}

function package_quote(array $params): ?array
{
    $pkg = find_package((string) ($params['id'] ?? ''));
    if (!$pkg) return null;
    [$first, $last] = package_dates($pkg);
    if ($first > $last) return null; // the deal has ended
    $depart = date_param((string) ($params['depart'] ?? ''), $first);
    if (!$depart || $depart > $last) $depart = $first;
    $return = add_days($depart, $pkg['nights']);
    $adults = max(1, min(6, (int) ($params['adults'] ?? 2) ?: 2));
    $includes = array_merge(['Round-trip flights', 'Hotel'], $pkg['car'] ? ['Rental car'] : []);

    return finish_quote([
        'kind' => 'package',
        'title' => $pkg['title'],
        'subtitle' => "{$pkg['from_city']} → {$pkg['destination']} · {$pkg['nights']} nights · {$pkg['hotel']}",
        'start_date' => $depart,
        'end_date' => $return,
        'slots' => slots($adults, 0),
        'lines' => [['label' => "$adults × " . money($pkg['price']) . ' per person', 'amount' => $pkg['price'] * $adults]],
        'facts' => [
            ['Leaving from', "{$pkg['from_city']} ({$pkg['from_code']})"],
            ['Dates', fmt_date($depart) . ' – ' . fmt_date($return)],
            ['Hotel', $pkg['hotel'] . ($pkg['stars'] ? " ({$pkg['stars']}★)" : '')],
            ['Includes', implode(', ', array_merge($includes, $pkg['highlights']))],
            ['Travelers', (string) $adults],
        ],
        'query' => http_build_query(['id' => $pkg['slug'], 'depart' => $depart, 'adults' => $adults]),
        'note' => null,
        'supplier' => null,
        'no_discount' => true, // promo prices are already final
        'package' => ['slug' => $pkg['slug'], 'first' => $first, 'last' => $last],
    ]);
}

function hotel_quote(array $params): ?array
{
    $p = parse_hotel_params($params);
    $s = $p['search'];
    if (!$s) return null;
    $hotelId = (string) ($params['hotel'] ?? '');
    if (liteapi_enabled()) {
        $h = liteapi_hotel($s, $hotelId);
    } else {
        if (!demo_mode()) return null; // never sell made-up hotels
        $h = null;
        foreach (sample_hotels($s) as $candidate) {
            if ($candidate['id'] === $hotelId) $h = $candidate;
        }
    }
    if (!$h) return null;

    $stay = plural($s['nights'], 'night') . ' × ' . plural($s['rooms'], 'room');
    $roomTotal = $h['nightly'] * $s['nights'] * $s['rooms'];
    $lines = $h['stay_total']
        ? [['label' => "$stay (taxes included)", 'amount' => $h['stay_total']]]
        : [['label' => $stay, 'amount' => $roomTotal], ['label' => 'Taxes & fees', 'amount' => (int) round($roomTotal * HOTEL_TAX_RATE)]];
    $guests = $s['adults'] + $s['children'];
    $guestText = "$guests (" . plural($s['adults'], 'adult') . ($s['children'] ? ', ' . plural($s['children'], 'child', 'children') : '') . ')';

    return finish_quote([
        'kind' => 'hotel',
        'title' => $h['name'],
        'subtitle' => implode(' · ', array_filter(["{$s['city']['city']}, {$s['city']['country']}", $h['area'], $h['stars'] ? "{$h['stars']}★" : ''])),
        'start_date' => $s['checkin'],
        'end_date' => $s['checkout'],
        'slots' => [['label' => 'Lead guest', 'dob' => false]],
        'lines' => $lines,
        'facts' => [
            ['Check-in', fmt_date($s['checkin'])],
            ['Check-out', fmt_date($s['checkout'])],
            ['Room', "{$s['rooms']} × {$h['room']}"],
            ['Guests', $guestText],
            ['Cancellation', $h['free_cancel'] ? 'Free cancellation' : 'Non-refundable'],
        ],
        'query' => $p['query'] . '&hotel=' . rawurlencode($h['id']),
        'note' => isset($h['cost']) ? 'Live hotel rate. Some cities charge a local tourist tax, payable at the hotel.' : null,
        'supplier' => $h['cost'] ?? null,
    ]);
}

function build_quote(string $kind, array $params): ?array
{
    // Quotes are saved with the booking and used in emails and the admin area, so they're always
    // written in English; customer pages translate them when showing them (quote_text()).
    return in_english(fn() => match ($kind) {
        'flight' => flight_quote($params),
        'package' => package_quote($params),
        'hotel' => hotel_quote($params),
        default => null,
    });
}
