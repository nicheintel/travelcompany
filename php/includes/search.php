<?php
declare(strict_types=1);
defined('TC_APP') || exit;

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
    $infants = max(0, min($adults, 4, (int) ($q['infants'] ?? 0))); // airlines: one lap infant per adult
    $cabin = isset(CABIN_LABELS[$q['cabin'] ?? '']) ? $q['cabin'] : 'economy';
    $search = $from && $to && $from['code'] !== $to['code']
        ? compact('from', 'to', 'depart', 'adults', 'children', 'infants', 'cabin') + ['return' => $return]
        : null;
    $query = http_build_query(array_filter([
        'from' => $from['code'] ?? '', 'to' => $to['code'] ?? '', 'depart' => $depart, 'return' => $return,
        'trip' => $trip, 'adults' => $adults, 'children' => $children, 'infants' => $infants ?: null, 'cabin' => $cabin,
    ], fn($v) => $v !== null && $v !== ''));
    return compact('from', 'to', 'depart', 'return', 'trip', 'adults', 'children', 'infants', 'cabin', 'search', 'query');
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
    $currency = strtoupper($currency);
    if ($currency === 'USD') return $amount;
    $live = fx_rates()['rates'][$currency] ?? null; // units per 1 USD, refreshed daily
    if ($live) return $amount / $live;
    $rate = ((array) config('fx_rates_to_usd'))[$currency] ?? null;
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

/**
 * Safety rule: a Duffel TEST token only returns a fake airline. Once PayPal takes real money,
 * those flights must not be sellable, so flight search shows "coming soon" until a live token is set.
 */
/** Where flights come from: 'duffel', 'liteapi', or null when no supplier is set up. */
function flight_supplier(): ?string
{
    if (config('flight_supplier') === 'liteapi' && liteapi_enabled()) return 'liteapi';
    return duffel_enabled() ? 'duffel' : null;
}

function flights_on_hold(): bool
{
    return flight_supplier() === 'duffel' && duffel_enabled() && str_starts_with((string) config('duffel_access_token'), 'duffel_test_')
        && function_exists('paypal_enabled') && paypal_enabled() && paypal_live();
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
    $tax = isset($o['tax_amount']) ? to_usd((float) $o['tax_amount'], (string) ($o['tax_currency'] ?? $o['total_currency'])) : null;
    $pax = $o['passengers'] ?? [];
    $adults = count(array_filter($pax, fn($p) => ($p['type'] ?? null) === 'adult' || ($p['age'] ?? 0) >= 12));
    $infants = count(array_filter($pax, fn($p) => ($p['type'] ?? null) === 'infant_without_seat' || (isset($p['age']) && $p['age'] < 2)));
    $code = (string) ($o['owner']['iata_code'] ?? '');
    $palette = ['#1c54f0', '#0f766e', '#7c3aed', '#f06c06', '#be123c', '#0369a1', '#15803d', '#a16207'];
    return [
        'id' => $o['id'],
        'airline' => ['code' => $code, 'name' => $o['owner']['name'], 'color' => $palette[crc32($code ?: $o['owner']['name']) % count($palette)], 'logo' => $o['owner']['logo_symbol_url'] ?? null],
        'outbound' => duffel_leg($o['slices'][0]),
        'inbound' => isset($o['slices'][1]) ? duffel_leg($o['slices'][1]) : null,
        'total' => $total,
        'taxes' => $tax ? (int) round($tax) : null,
        'refundable' => (bool) ($o['conditions']['refund_before_departure']['allowed'] ?? false),
        'seats_left' => null,
        'adults' => $adults,
        'children' => count($pax) - $adults - $infants,
        'infants' => $infants,
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
        array_fill(0, $s['infants'] ?? 0, ['type' => 'infant_without_seat']), // under 2, on an adult's lap
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
    $name = trim(preg_replace('/\s*\(.*\)$/', '', $city['city'])); // "Bohol (Panglao)" -> "Bohol"
    $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $data = liteapi('GET', '/data/hotels?' . http_build_query(['countryCode' => $city['cc'], 'cityName' => $plain, 'limit' => 60]))['data'] ?? [];
    if (!$data) {
        // Smaller places: hotels within 40 km of the airport instead.
        $data = liteapi('GET', '/data/hotels?' . http_build_query(['latitude' => $city['lat'], 'longitude' => $city['lon'], 'radius' => 40000, 'limit' => 60]))['data'] ?? [];
    }
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
    // Taxes inside the price, and charges the hotel collects itself at check-in (resort fees, city taxes…).
    $taxIn = 0.0;
    $atHotel = [];
    foreach ($best['room']['rates'] as $r) {
        foreach ($r['retailRate']['taxesAndFees'] ?? [] as $f) {
            if (!is_array($f) || !array_key_exists('included', $f) || !isset($f['amount'])) continue;
            $usd = to_usd((float) $f['amount'], (string) ($f['currency'] ?? $best['currency']));
            if (!$usd || $usd <= 0) continue;
            if (filter_var($f['included'], FILTER_VALIDATE_BOOLEAN)) {
                $taxIn += $usd;
            } else {
                $label = trim((string) ($f['description'] ?? ''));
                if ($label === '' || mb_strtoupper($label) === $label) $label = $label === '' ? 'Local taxes and fees' : mb_strtoupper(mb_substr($label, 0, 1)) . mb_strtolower(mb_substr($label, 1));
                $atHotel[$label] = ($atHotel[$label] ?? 0) + $usd;
            }
        }
    }
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
        'taxes' => $taxIn > 0 && $taxIn < $stay ? (int) round($taxIn) : null,
        'at_hotel' => array_map(fn($label, $usd) => ['label' => $label, 'amount' => (int) ceil($usd)], array_keys($atHotel), array_values($atHotel)),
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

/**
 * Live searches cost money at the supplier after a free allowance, so each visitor gets at
 * most 40 live searches per 10 minutes, and the same search repeated within 3 minutes
 * (refresh, back button) is answered from their session instead of calling the supplier again.
 */
function live_search(string $kind, array $s, callable $fetch): array
{
    $key = $kind . ':' . sha1(serialize($s));
    $cache = &$_SESSION['search_cache'];
    $cache = array_filter((array) $cache, fn($c) => $c['at'] > time() - 180);
    if (isset($cache[$key])) return $cache[$key]['result'];
    if (ip_throttled('search', 40, 600)) {
        return ['items' => [], 'live' => true, 'error' => t("You've searched a lot in a short time. Please wait a few minutes and try again.")];
    }
    $result = ['items' => $fetch($s), 'live' => true, 'error' => null];
    $cache = array_slice($cache, -2, null, true) + [$key => ['at' => time(), 'result' => $result]];
    return $result;
}

function search_flights(array $s): array
{
    if (flights_on_hold()) return not_connected() + ['on_hold' => true];
    $supplier = flight_supplier();
    if (!$supplier) return demo_mode() ? ['items' => sample_flights($s), 'live' => false, 'error' => null] : not_connected();
    try {
        return $supplier === 'liteapi' ? live_search('flights-liteapi', $s, 'liteapi_flight_search') : live_search('flights', $s, 'duffel_search');
    } catch (Throwable $e) {
        error_log('[' . $supplier . '] flight search failed: ' . $e->getMessage());
        return ['items' => [], 'live' => true, 'error' => t("We couldn't load live fares just now. Please try again in a moment.")];
    }
}

function search_hotels(array $s): array
{
    if (!liteapi_enabled()) return demo_mode() ? ['items' => sample_hotels($s), 'live' => false, 'error' => null] : not_connected();
    try {
        return live_search('hotels', $s, 'liteapi_search');
    } catch (Throwable $e) {
        error_log('[liteapi] search failed: ' . $e->getMessage());
        return ['items' => [], 'live' => true, 'error' => t("We couldn't load live hotel prices just now. Please try again in a moment.")];
    }
}

// ---------- LiteAPI (flights) ----------

const LITEAPI_CABINS = ['economy' => 'economy', 'premium' => 'premium', 'business' => 'business', 'first' => 'first'];

/** One direction of a LiteAPI journey in our leg format (same as Duffel's). */
function liteapi_flight_leg(array $segs, ?int $minutes): array
{
    $first = $segs[0];
    $last = $segs[count($segs) - 1];
    $depart = clock_minutes($first['departureTime']);
    $arrive = clock_minutes($last['arrivalTime']);
    $dayOffset = (int) round((strtotime(substr($last['arrivalTime'], 0, 10)) - strtotime(substr($first['departureTime'], 0, 10))) / 86400);
    return [
        'from' => $first['originCode'] ?? '', 'to' => $last['destinationCode'] ?? '',
        'date' => substr($first['departureTime'], 0, 10),
        'depart' => $depart, 'arrive' => $arrive, 'day_offset' => $dayOffset,
        'duration' => $minutes ?? ($dayOffset * 1440 + $arrive - $depart),
        'stops' => count($segs) - 1 + array_sum(array_map(fn($x) => (int) ($x['stopCount'] ?? 0), $segs)),
        'stop_cities' => array_map(fn($x) => $x['destinationCode'] ?? '', array_slice($segs, 0, -1)),
        'flight_number' => ($first['carrier']['marketingCode'] ?? '') . ($first['flight']['marketingNumber'] ?? ''),
    ];
}

/** Maps a LiteAPI journey (its cheapest offer) to our flight format, with the marked-up price. */
/** One LiteAPI journey as an offer for our results; null (with the reason in $why) when it can't be sold. */
function liteapi_flight_map(array $j, array $s, ?string &$why = null): ?array
{
    $o = $j['cheapestOffer'] ?? null;
    $segs = $j['segments'] ?? [];
    if (!$o || !$segs || empty($o['offerId'])) { $why = 'no bookable offer in the answer'; return null; }
    $out = array_values(array_filter($segs, fn($x) => ($x['direction'] ?? 'OUTBOUND') === 'OUTBOUND'));
    $in = array_values(array_filter($segs, fn($x) => ($x['direction'] ?? '') === 'INBOUND'));
    if (!$out || ($s['return'] && !$in)) { $why = $out ? 'no return flight in this offer' : 'no outbound flight in this offer'; return null; }
    $mins = [];
    foreach ($j['legDurations'] ?? [] as $d) $mins[$d['direction'] ?? ''] = $d['duration']['minutes'] ?? null;

    // Total from the per-passenger prices, so it always matches the travelers searched for.
    $pp = $o['pricing']['display']['perPassenger'] ?? [];
    $currency = (string) ($o['pricing']['display']['currency'] ?? 'USD');
    $net = ($pp['adult']['total'] ?? 0) * $s['adults'] + ($pp['child']['total'] ?? 0) * $s['children'] + ($pp['infant']['total'] ?? 0) * ($s['infants'] ?? 0);
    if ($net <= 0 || ($s['children'] && !isset($pp['child'])) || (($s['infants'] ?? 0) && !isset($pp['infant']))) { $why = 'no price for every traveler (adults, children, infants)'; return null; }
    $netUsd = to_usd((float) $net, $currency);
    if ($netUsd === null) { $why = "price in $currency, which we can't convert to USD"; return null; }
    $markup = markup_rate('flight');
    // Government taxes and airline fees inside that price (shown as their own line when booking).
    $tax = 0.0;
    foreach (['adult' => $s['adults'], 'child' => $s['children'], 'infant' => $s['infants'] ?? 0] as $type => $n) {
        $tax += ((float) ($pp[$type]['taxes'] ?? 0) + (float) ($pp[$type]['fees'] ?? 0)) * $n;
    }
    $taxUsd = $tax > 0 ? to_usd($tax, $currency) : null;
    $carrier = $out[0]['carrier'] ?? [];
    $code = (string) ($carrier['marketingCode'] ?? '');
    $palette = ['#1c54f0', '#0f766e', '#7c3aed', '#f06c06', '#be123c', '#0369a1', '#15803d', '#a16207'];
    $bags = $o['baggage'] ?? [];
    $checkedFrom = null;
    foreach ($bags['paid'] ?? [] as $b) {
        if (($b['bagType'] ?? '') === 'checked' && isset($b['pricing']['display']['amount'])) {
            $checkedFrom = min($checkedFrom ?? PHP_INT_MAX, (float) $b['pricing']['display']['amount']);
        }
    }
    return [
        'id' => (string) $o['offerId'],
        'airline' => ['code' => $code, 'name' => (string) ($carrier['marketingName'] ?? $code), 'color' => $palette[crc32($code ?: 'x') % count($palette)], 'logo' => $carrier['marketingLogo'] ?? null],
        'outbound' => liteapi_flight_leg($out, $mins['OUTBOUND'] ?? null),
        'inbound' => $in ? liteapi_flight_leg($in, $mins['INBOUND'] ?? null) : null,
        'total' => sell_price($netUsd, $markup),
        'taxes' => $taxUsd ? (int) round($taxUsd) : null,
        'refundable' => (bool) ($o['terms']['refundable'] ?? false),
        'seats_left' => isset($o['fare']['seatsRemaining']) ? (int) $o['fare']['seatsRemaining'] : null,
        'adults' => $s['adults'], 'children' => $s['children'], 'infants' => $s['infants'] ?? 0,
        'origin_city' => $s['from']['city'], 'destination_city' => $s['to']['city'],
        'cabin' => (string) ($o['segmentFares'][0]['cabin'] ?? ''),
        'baggage' => ['carry_on' => (bool) ($bags['hasCarryOnBag'] ?? false), 'checked' => (bool) ($bags['hasCheckedBag'] ?? false), 'checked_from' => $checkedFrom],
        'terms' => array_values(array_filter(array_map(fn($t) => (string) ($t['message'] ?? ''), $o['terms']['summary'] ?? []))),
        'expires_at' => (string) ($o['expiration'] ?? ''),
        'cost' => ['provider' => 'liteapi_flights', 'offer_id' => (string) $o['offerId'], 'net_amount' => round((float) $net, 2), 'net_currency' => $currency, 'net_usd' => $netUsd, 'markup_rate' => $markup],
    ];
}

/** Every journey in a LiteAPI /flights/rates answer (all result groups, not only the first). */
function liteapi_journeys(array $json): array
{
    $all = [];
    foreach ($json['data'] ?? [] as $group) foreach ($group['journeys'] ?? [] as $j) $all[] = $j;
    return $all;
}

/**
 * Checks each journey the way the search does. Returns [offer or null, reason it's hidden or null, airline name].
 * Used by the search and by Admin → Diagnostics → LiteAPI flight search test.
 */
function liteapi_flight_review(array $journeys, array $s): array
{
    // Only the route asked for (same airport or same city), and the cabin asked for.
    $sameCity = fn(string $code, array $want) => $code === $want['code'] || (($a = airport($code)) && $a['city'] === $want['city'] && $a['cc'] === $want['cc']);
    $cabin = LITEAPI_CABINS[$s['cabin']] ?? 'economy';
    $rows = [];
    foreach ($journeys as $j) {
        $why = null;
        $o = liteapi_flight_map($j, $s, $why);
        $first = array_values(array_filter($j['segments'] ?? [], fn($x) => ($x['direction'] ?? 'OUTBOUND') === 'OUTBOUND'))[0]['carrier'] ?? [];
        $name = (string) ($first['marketingName'] ?? $first['marketingCode'] ?? 'Unknown airline');
        if ($o && (!$sameCity($o['outbound']['from'], $s['from']) || !$sameCity($o['outbound']['to'], $s['to']))) {
            $why = "different route ({$o['outbound']['from']} to {$o['outbound']['to']})";
        } elseif ($o && $o['cabin'] !== '' && !str_contains(strtolower($o['cabin']), $cabin)) {
            $why = "cabin is \"{$o['cabin']}\", the search asked for $cabin";
        }
        $rows[] = [$why === null ? $o : null, $why, $name];
    }
    return $rows;
}

function liteapi_flight_body(array $s): array
{
    $legs = [['origin' => $s['from']['code'], 'destination' => $s['to']['code'], 'date' => $s['depart'], 'direction' => 'OUTBOUND']];
    if ($s['return']) $legs[] = ['origin' => $s['to']['code'], 'destination' => $s['from']['code'], 'date' => $s['return'], 'direction' => 'INBOUND'];
    return ['legs' => $legs, 'adults' => $s['adults'], 'children' => $s['children'], 'infants' => $s['infants'] ?? 0, 'currency' => 'USD'];
}

function liteapi_flight_search(array $s): array
{
    $journeys = liteapi_journeys(liteapi('POST', '/flights/rates', liteapi_flight_body($s)));
    $offers = array_values(array_filter(array_column(liteapi_flight_review($journeys, $s), 0)));
    usort($offers, fn($a, $b) => $a['total'] <=> $b['total']);
    $offers = array_slice($offers, 0, 60);
    remember_flight_offers($offers);
    return $offers;
}

/** Keeps the offers we showed (until they expire), so booking uses LiteAPI's price, not the URL. */
function remember_flight_offers(array $offers): void
{
    db_run('DELETE FROM flight_offers WHERE expires_at < ?', [now_utc()]);
    foreach ($offers as $o) {
        $exp = strtotime($o['expires_at']) ?: time() + 1800;
        db_run(
            'INSERT INTO flight_offers (id_hash, data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)',
            [hash('sha256', $o['id']), json_encode($o), gmdate('Y-m-d H:i:s', $exp)],
        );
    }
}

function find_flight_offer(string $id): ?array
{
    if ($id === '' || strlen($id) > 1000) return null;
    $row = db_one('SELECT data FROM flight_offers WHERE id_hash = ? AND expires_at > ?', [hash('sha256', $id), now_utc()]);
    return $row ? json_decode($row['data'], true) : null;
}
