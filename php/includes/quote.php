<?php
declare(strict_types=1);

/*
 * Booking prices. Always rebuilt on the server from the search parameters —
 * the browser only says *what* to book, never the price.
 */

const BOOKING_KINDS = ['flight', 'hotel', 'package'];

function slots(int $adults, int $children): array
{
    $out = [];
    for ($i = 1; $i <= $adults; $i++) $out[] = ['label' => "Adult $i", 'dob' => true];
    for ($i = 1; $i <= $children; $i++) $out[] = ['label' => "Child $i", 'dob' => true];
    return $out;
}

function finish_quote(array $q): array
{
    $subtotal = array_sum(array_column($q['lines'], 'amount'));
    $rate = member_discount_rate();
    $discount = (int) round($subtotal * $rate);
    return $q + ['subtotal' => $subtotal, 'discount' => $discount, 'discount_rate' => $rate, 'total' => $subtotal - $discount];
}

function flight_quote(array $params): ?array
{
    $p = parse_flight_params($params);
    if (!$p['search']) return null;
    $offerId = (string) ($params['offer'] ?? '');
    $cabin = CABIN_LABELS[$p['cabin']];
    $cost = null;
    $note = null;

    if (duffel_enabled()) {
        // Trust the airline offer itself (route, passengers, price) rather than the URL.
        $o = duffel_offer($offerId);
        if (!$o) return null;
        $adults = $o['adults'];
        $children = $o['children'];
        $title = "{$o['origin_city']} → {$o['destination_city']}";
        $people = $adults + $children;
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
        $title = "{$p['from']['city']} → {$p['to']['city']}";
        $lines = [['label' => "$adults × adult fare", 'amount' => $o['per_adult'] * $adults]];
        if ($children) $lines[] = ['label' => "$children × child fare", 'amount' => $o['per_child'] * $children];
    }

    $facts = [['Depart', fmt_date($o['outbound']['date'])]];
    if ($o['inbound']) $facts[] = ['Return', fmt_date($o['inbound']['date'])];
    array_push($facts, ['Travelers', (string) ($adults + $children)], ['Cabin', $cabin], ['Fare', $o['refundable'] ? 'Refundable' : 'Non-refundable']);

    return finish_quote([
        'kind' => 'flight',
        'title' => $title,
        'subtitle' => "{$o['airline']['name']} · " . ($o['inbound'] ? 'Round trip' : 'One way') . " · $cabin",
        'start_date' => $o['outbound']['date'],
        'end_date' => $o['inbound']['date'] ?? null,
        'slots' => slots($adults, $children),
        'lines' => $lines,
        'facts' => $facts,
        'flight' => ['airline' => $o['airline'], 'outbound' => $o['outbound'], 'inbound' => $o['inbound']],
        'query' => $p['query'] . '&offer=' . rawurlencode($o['id']),
        'note' => $note,
        'supplier' => $cost,
    ]);
}

function package_quote(array $params): ?array
{
    $pkg = find_package((string) ($params['id'] ?? ''));
    if (!$pkg) return null;
    $from = airport($params['from'] ?? '');
    if (!$from || $from['code'] === $pkg['code']) $from = airport('JFK');
    $depart = date_param((string) ($params['depart'] ?? ''), earliest_date()) ?? add_days(earliest_date(), 22);
    $return = add_days($depart, $pkg['nights']);
    $adults = max(1, min(6, (int) ($params['adults'] ?? 2) ?: 2));
    $includes = array_merge(['Round-trip flights', 'Hotel'], $pkg['car'] ? ['Rental car'] : []);

    return finish_quote([
        'kind' => 'package',
        'title' => $pkg['title'],
        'subtitle' => "{$from['city']} → {$pkg['destination']} · {$pkg['nights']} nights · {$pkg['hotel']}",
        'start_date' => $depart,
        'end_date' => $return,
        'slots' => slots($adults, 0),
        'lines' => [['label' => "$adults × package price", 'amount' => $pkg['price'] * $adults]],
        'facts' => [
            ['Leaving from', "{$from['city']} ({$from['code']})"],
            ['Dates', fmt_date($depart) . ' – ' . fmt_date($return)],
            ['Hotel', "{$pkg['hotel']} ({$pkg['stars']}★)"],
            ['Includes', implode(', ', $includes)],
            ['Travelers', (string) $adults],
        ],
        'query' => http_build_query(['id' => $pkg['id'], 'from' => $from['code'], 'depart' => $depart, 'adults' => $adults]),
        'note' => null,
        'supplier' => null,
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
    return match ($kind) {
        'flight' => flight_quote($params),
        'package' => package_quote($params),
        'hotel' => hotel_quote($params),
        default => null,
    };
}
