<?php
declare(strict_types=1);

// ---------- Search parameters (shared by results and booking pages) ----------

function parse_flight_params(array $q): array
{
    $from = airport($q['from'] ?? '');
    $to = airport($q['to'] ?? '');
    $min = earliest_date();
    $depart = date_param((string) ($q['depart'] ?? ''), $min) ?? add_days($min, 15);
    $trip = ($q['trip'] ?? '') === 'oneway' ? 'oneway' : 'roundtrip';
    $return = $trip === 'oneway' ? null : (date_param((string) ($q['return'] ?? ''), $depart) ?? add_days($depart, 7));
    $adults = max(1, min(9, (int) ($q['adults'] ?? 1) ?: 1));
    $children = max(0, min(8, (int) ($q['children'] ?? 0)));
    $cabin = isset(CABIN_LABELS[$q['cabin'] ?? '']) ? $q['cabin'] : 'economy';
    $search = $from && $to && $from['code'] !== $to['code']
        ? compact('from', 'to', 'depart', 'adults', 'children', 'cabin') + ['return' => $return]
        : null;
    $query = http_build_query(array_filter([
        'from' => $from['code'] ?? '', 'to' => $to['code'] ?? '', 'depart' => $depart, 'return' => $return,
        'trip' => $trip, 'adults' => $adults, 'children' => $children, 'cabin' => $cabin,
    ], fn($v) => $v !== null && $v !== ''));
    return compact('from', 'to', 'depart', 'return', 'trip', 'adults', 'children', 'cabin', 'search', 'query');
}

function parse_hotel_params(array $q): array
{
    $city = airport($q['to'] ?? '');
    $min = earliest_date();
    $checkin = date_param((string) ($q['checkin'] ?? ''), $min) ?? add_days($min, 15);
    $checkout = date_param((string) ($q['checkout'] ?? ''), add_days($checkin, 1)) ?? add_days($checkin, 3);
    if ($checkout > add_days($checkin, 30)) $checkout = add_days($checkin, 30);
    $nights = (int) ((strtotime($checkout) - strtotime($checkin)) / 86400);
    $adults = max(1, min(9, (int) ($q['adults'] ?? 2) ?: 2));
    $children = max(0, min(8, (int) ($q['children'] ?? 0)));
    $rooms = min(max(1, min(4, (int) ($q['rooms'] ?? 1) ?: 1)), $adults);
    $search = $city ? compact('city', 'checkin', 'checkout', 'nights', 'adults', 'children', 'rooms') : null;
    $query = http_build_query(['to' => $city['code'] ?? '', 'checkin' => $checkin, 'checkout' => $checkout, 'adults' => $adults, 'children' => $children, 'rooms' => $rooms]);
    return compact('city', 'checkin', 'checkout', 'adults', 'children', 'rooms', 'search', 'query');
}

// ---------- Pricing ----------

function markup_rate(string $kind): float
{
    $r = (float) config($kind === 'flight' ? 'flight_markup_rate' : 'hotel_markup_rate');
    return $r >= 0 && $r <= 3 ? $r : 0.2;
}

function member_discount_rate(): float
{
    $r = (float) config('member_discount_rate');
    return $r >= 0 && $r < 1 ? $r : 0.1;
}

/** Customer price in whole dollars, rounded up so the margin never drops below the rate. */
function sell_price(float $netUsd, float $markup): int
{
    return (int) ceil(round($netUsd * (1 + $markup), 6));
}

/** Converts to USD using config fx_rates_to_usd; null when the currency is unknown. */
function to_usd(float $amount, string $currency): ?float
{
    $rate = ((array) config('fx_rates_to_usd'))[strtoupper($currency)] ?? null;
    return $rate ? $amount * (float) $rate : null;
}

// ---------- HTTP ----------

/**
 * CA bundle for https on XAMPP/Windows, where php.ini often has no curl.cainfo.
 * Uses the bundle XAMPP ships with; certificates are always verified.
 */
function ca_bundle(): ?string
{
    if (ini_get('curl.cainfo')) return null; // already configured in php.ini
    $ini = php_ini_loaded_file();
    $candidates = array_filter([
        $ini ? dirname($ini, 2) . '/apache/bin/curl-ca-bundle.crt' : null,
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        $ini ? dirname($ini) . '/extras/ssl/cacert.pem' : null,
    ]);
    foreach ($candidates as $file) {
        if (is_file($file)) return $file;
    }
    return null;
}

function http_json(string $method, string $url, array $headers, ?array $body = null, int $timeout = 30, bool $form = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP curl extension is off. In C:\\xampp\\php\\php.ini remove the ; before extension=curl and restart Apache.');
    }
    $ch = curl_init($url);
    if ($ca = ca_bundle()) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $h = array_merge(['Accept: application/json'], $headers);
    if ($body !== null) {
        $h[] = $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
        // An empty JSON body must be an object ({}), not a list ([]).
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form ? http_build_query($body) : ($body === [] ? '{}' : json_encode($body)));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '', // accept gzip
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException("Request to $url failed: $error");
    }
    $json = json_decode((string) $raw, true);
    return ['status' => $status, 'json' => is_array($json) ? $json : []];
}

// ---------- Duffel (flights) ----------

function duffel_enabled(): bool
{
    return (string) config('duffel_access_token') !== '';
}

function duffel(string $method, string $path, ?array $data = null): array
{
    $res = http_json($method, rtrim((string) config('duffel_api_base'), '/') . $path, [
        'Authorization: Bearer ' . config('duffel_access_token'),
        'Duffel-Version: v2',
    ], $data === null ? null : ['data' => $data], 45);
    if ($res['status'] >= 300) {
        throw new RuntimeException('Duffel ' . $res['status'] . ': ' . ($res['json']['errors'][0]['message'] ?? 'request failed'));
    }
    return $res['json']['data'] ?? [];
}

function iso_minutes(?string $d): ?int
{
    if (!$d || !preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/', $d, $m)) return null;
    return (int) ($m[1] ?? 0) * 1440 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
}

function clock_minutes(string $t): int
{
    return (int) substr($t, 11, 2) * 60 + (int) substr($t, 14, 2);
}

function duffel_leg(array $slice): array
{
    $segs = $slice['segments'];
    $first = $segs[0];
    $last = $segs[count($segs) - 1];
    $depart = clock_minutes($first['departing_at']);
    $arrive = clock_minutes($last['arriving_at']);
    $dayOffset = (int) round((strtotime(substr($last['arriving_at'], 0, 10)) - strtotime(substr($first['departing_at'], 0, 10))) / 86400);
    $technical = array_sum(array_map(fn($s) => count($s['stops'] ?? []), $segs));
    return [
        'from' => $slice['origin']['iata_code'] ?? '', 'to' => $slice['destination']['iata_code'] ?? '',
        'date' => substr($first['departing_at'], 0, 10),
        'depart' => $depart, 'arrive' => $arrive, 'day_offset' => $dayOffset,
        'duration' => iso_minutes($slice['duration'] ?? null) ?? ($dayOffset * 1440 + $arrive - $depart),
        'stops' => count($segs) - 1 + $technical,
        'stop_cities' => array_map(fn($s) => $s['destination']['iata_code'] ?? $s['destination']['name'], array_slice($segs, 0, -1)),
        'flight_number' => ($first['marketing_carrier']['iata_code'] ?? '') . $first['marketing_carrier_flight_number'],
    ];
}

/** Maps a Duffel offer to our format with the marked-up price, plus our cost. */
function duffel_map(array $o): ?array
{
    $net = (float) $o['total_amount'];
    $netUsd = to_usd($net, (string) $o['total_currency']);
    if ($netUsd === null || empty($o['slices'])) return null;
    $markup = markup_rate('flight');
    $total = sell_price($netUsd, $markup);
    $pax = $o['passengers'] ?? [];
    $adults = count(array_filter($pax, fn($p) => ($p['type'] ?? null) === 'adult' || ($p['age'] ?? 99) >= 12));
    $code = (string) ($o['owner']['iata_code'] ?? '');
    $palette = ['#1c54f0', '#0f766e', '#7c3aed', '#f06c06', '#be123c', '#0369a1', '#15803d', '#a16207'];
    return [
        'id' => $o['id'],
        'airline' => ['code' => $code, 'name' => $o['owner']['name'], 'color' => $palette[crc32($code ?: $o['owner']['name']) % count($palette)], 'logo' => $o['owner']['logo_symbol_url'] ?? null],
        'outbound' => duffel_leg($o['slices'][0]),
        'inbound' => isset($o['slices'][1]) ? duffel_leg($o['slices'][1]) : null,
        'total' => $total,
        'refundable' => (bool) ($o['conditions']['refund_before_departure']['allowed'] ?? false),
        'seats_left' => null,
        'adults' => $adults,
        'children' => count($pax) - $adults,
        'origin_city' => $o['slices'][0]['origin']['city_name'] ?? $o['slices'][0]['origin']['name'],
        'destination_city' => $o['slices'][0]['destination']['city_name'] ?? $o['slices'][0]['destination']['name'],
        'cost' => ['provider' => 'duffel', 'offer_id' => $o['id'], 'net_amount' => $net, 'net_currency' => $o['total_currency'], 'net_usd' => $netUsd, 'markup_rate' => $markup],
    ];
}

function duffel_search(array $s): array
{
    $slices = [['origin' => $s['from']['code'], 'destination' => $s['to']['code'], 'departure_date' => $s['depart']]];
    if ($s['return']) {
        $slices[] = ['origin' => $s['to']['code'], 'destination' => $s['from']['code'], 'departure_date' => $s['return']];
    }
    $passengers = array_merge(
        array_fill(0, $s['adults'], ['type' => 'adult']),
        array_fill(0, $s['children'], ['age' => 8]), // the form doesn't ask children's ages
    );
    $cabin = ['economy' => 'economy', 'premium' => 'premium_economy', 'business' => 'business', 'first' => 'first'][$s['cabin']];
    $req = duffel('POST', '/air/offer_requests?return_offers=true&supplier_timeout=20000', compact('slices', 'passengers') + ['cabin_class' => $cabin]);
    // Only keep offers for the route the customer asked for (same airport, or same city e.g. JFK/LGA).
    $matches = function (array $place, array $wanted): bool {
        return ($place['iata_code'] ?? null) === $wanted['code']
            || (($place['iata_city_code'] ?? null) !== null && ($place['iata_city_code'] ?? null) === ($wanted['city_code'] ?? false));
    };
    $want = [
        ['code' => $s['from']['code'], 'city_code' => $req['slices'][0]['origin']['iata_city_code'] ?? null],
        ['code' => $s['to']['code'], 'city_code' => $req['slices'][0]['destination']['iata_city_code'] ?? null],
    ];
    $raw = $req['offers'] ?? [];
    $raw = array_filter($raw, fn($o) => isset($o['slices'][0])
        && $matches($o['slices'][0]['origin'], $want[0]) && $matches($o['slices'][0]['destination'], $want[1]));
    if (count($raw) < count($req['offers'] ?? [])) {
        error_log('[duffel] dropped ' . (count($req['offers']) - count($raw)) . ' offers for a different route than ' . $want[0]['code'] . '-' . $want[1]['code']);
    }
    $offers = array_values(array_filter(array_map('duffel_map', $raw)));
    usort($offers, fn($a, $b) => $a['total'] <=> $b['total']);
    return array_slice($offers, 0, 60);
}

/** Latest price for one offer, or null if it expired. */
function duffel_offer(string $id): ?array
{
    if (!preg_match('/^off_[A-Za-z0-9]+$/', $id)) return null;
    try {
        $o = duffel('GET', '/air/offers/' . $id);
        if (strtotime($o['expires_at']) < time()) return null;
        return duffel_map($o);
    } catch (Throwable $e) {
        error_log('[duffel] ' . $e->getMessage());
        return null;
    }
}

// ---------- LiteAPI (hotels) ----------

function liteapi_enabled(): bool
{
    return (string) config('liteapi_key') !== '';
}

function liteapi(string $method, string $path, ?array $body = null): array
{
    $res = http_json($method, rtrim((string) config('liteapi_api_base'), '/') . $path, ['X-API-Key: ' . config('liteapi_key')], $body, 30);
    if ($res['status'] >= 300) {
        $err = $res['json']['error'] ?? 'request failed';
        throw new RuntimeException('LiteAPI ' . $res['status'] . ': ' . (is_array($err) ? ($err['message'] ?? 'error') : $err));
    }
    return $res['json'];
}

/** Hotel list per city, cached for 12 hours in storage/. */
function liteapi_city_hotels(array $city): array
{
    $file = dirname(__DIR__) . '/storage/hotels-' . $city['code'] . '.json';
    if (is_file($file) && filemtime($file) > time() - 43200) {
        return json_decode((string) file_get_contents($file), true) ?: [];
    }
    $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $city['city']) ?: $city['city'];
    $data = liteapi('GET', '/data/hotels?' . http_build_query(['countryCode' => $city['cc'], 'cityName' => $plain, 'limit' => 60]))['data'] ?? [];
    @file_put_contents($file, json_encode($data));
    return $data;
}

function liteapi_occupancies(array $s): array
{
    $out = [];
    for ($i = 0; $i < $s['rooms']; $i++) {
        $kids = intdiv($s['children'], $s['rooms']) + ($i < $s['children'] % $s['rooms'] ? 1 : 0);
        $out[] = [
            'adults' => intdiv($s['adults'], $s['rooms']) + ($i < $s['adults'] % $s['rooms'] ? 1 : 0),
            'children' => array_fill(0, $kids, 8), // the form doesn't ask children's ages
        ];
    }
    return $out;
}

function liteapi_rates(array $hotelIds, array $s): array
{
    return liteapi('POST', '/hotels/rates', [
        'hotelIds' => $hotelIds, 'checkin' => $s['checkin'], 'checkout' => $s['checkout'],
        'currency' => 'USD', 'guestNationality' => 'US', 'occupancies' => liteapi_occupancies($s), 'timeout' => 12,
    ])['data'] ?? [];
}

function liteapi_map(array $info, array $rates, array $s, int $i): ?array
{
    $best = null;
    foreach ($rates['roomTypes'] ?? [] as $room) {
        $totals = array_map(fn($r) => $r['retailRate']['total'][0] ?? null, $room['rates'] ?? []);
        if (!$totals || in_array(null, $totals, true)) continue;
        $net = array_sum(array_column($totals, 'amount'));
        $floor = array_sum(array_map(fn($r) => $r['retailRate']['suggestedSellingPrice'][0]['amount'] ?? 0, $room['rates']));
        if (!$best || $net < $best['net']) $best = ['room' => $room, 'net' => (float) $net, 'currency' => $totals[0]['currency'], 'floor' => (float) $floor];
    }
    if (!$best) return null;
    $netUsd = to_usd($best['net'], $best['currency']);
    if ($netUsd === null) return null;
    $markup = markup_rate('hotel');
    // Never sell below the hotel's suggested selling price (rate-parity rules).
    $stay = max(sell_price($netUsd, $markup), (int) ceil(to_usd($best['floor'], $best['currency']) ?? 0));
    $rate = $best['room']['rates'][0];
    $gradients = ['from-sky-400 to-blue-700', 'from-emerald-400 to-teal-700', 'from-amber-300 to-orange-600', 'from-rose-400 to-purple-700'];
    return [
        'id' => $rates['hotelId'], 'name' => $info['name'], 'stars' => (int) round((float) ($info['stars'] ?? 0)),
        'rating' => !empty($info['rating']) ? (float) $info['rating'] : null,
        'reviews' => !empty($info['reviewCount']) ? (int) $info['reviewCount'] : null,
        'area' => $info['address'] ?? $s['city']['city'], 'distance' => null, 'room' => $rate['name'],
        'nightly' => (int) ceil($stay / $s['nights'] / $s['rooms']), 'original' => null,
        'amenities' => preg_match('/breakfast/i', (string) ($rate['boardName'] ?? '')) ? ['breakfast'] : [],
        'free_cancel' => ($rate['cancellationPolicies']['refundableTag'] ?? '') === 'RFN',
        'gradient' => $gradients[$i % 4], 'photo' => ($info['main_photo'] ?? '') ?: null, 'stay_total' => $stay,
        'cost' => ['provider' => 'liteapi', 'offer_id' => $best['room']['offerId'], 'net_amount' => $best['net'], 'net_currency' => $best['currency'], 'net_usd' => $netUsd, 'markup_rate' => $markup],
    ];
}

function liteapi_search(array $s): array
{
    $list = liteapi_city_hotels($s['city']);
    if (!$list) return [];
    $byId = array_column($list, null, 'id');
    $out = [];
    foreach (liteapi_rates(array_keys($byId), $s) as $i => $r) {
        if (isset($byId[$r['hotelId']]) && ($h = liteapi_map($byId[$r['hotelId']], $r, $s, $i))) $out[] = $h;
    }
    return $out;
}

function liteapi_hotel(array $s, string $hotelId): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $hotelId)) return null;
    try {
        $info = array_column(liteapi_city_hotels($s['city']), null, 'id')[$hotelId] ?? null;
        if (!$info) return null;
        $rates = liteapi_rates([$hotelId], $s);
        return $rates ? liteapi_map($info, $rates[0], $s, 0) : null;
    } catch (Throwable $e) {
        error_log('[liteapi] ' . $e->getMessage());
        return null;
    }
}

// ---------- Search entry points (live when keys are set, otherwise sample data) ----------

function demo_mode(): bool
{
    return filter_var(config('demo_mode'), FILTER_VALIDATE_BOOLEAN);
}

/** No supplier key: sample data in demo mode only, otherwise "not connected". */
function not_connected(): array
{
    return ['items' => [], 'live' => false, 'error' => null, 'not_connected' => true];
}

function search_flights(array $s): array
{
    if (!duffel_enabled()) return demo_mode() ? ['items' => sample_flights($s), 'live' => false, 'error' => null] : not_connected();
    try {
        return ['items' => duffel_search($s), 'live' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('[duffel] search failed: ' . $e->getMessage());
        return ['items' => [], 'live' => true, 'error' => "We couldn't load live fares just now. Please try again in a moment."];
    }
}

function search_hotels(array $s): array
{
    if (!liteapi_enabled()) return demo_mode() ? ['items' => sample_hotels($s), 'live' => false, 'error' => null] : not_connected();
    try {
        return ['items' => liteapi_search($s), 'live' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('[liteapi] search failed: ' . $e->getMessage());
        return ['items' => [], 'live' => true, 'error' => "We couldn't load live hotel prices just now. Please try again in a moment."];
    }
}
