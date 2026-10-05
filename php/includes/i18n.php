<?php
defined('TC_APP') || exit;

// ---------- Languages ----------
// Customer pages are translated from English (machine translation, lang/<code>.json).
// The admin area and emails stay in English; legal pages stay in English (the English text applies).

/** code => [native name, flag file (assets/flags/<flag>.svg), html lang] */
const LANGUAGES = [
    'en' => ['English', 'gb', 'en'],
    'fil' => ['Filipino', 'ph', 'fil'],
    'es' => ['Español', 'es', 'es'],
    'fr' => ['Français', 'fr', 'fr'],
    'de' => ['Deutsch', 'de', 'de'],
    'it' => ['Italiano', 'it', 'it'],
    'pt' => ['Português', 'pt', 'pt'],
    'nl' => ['Nederlands', 'nl', 'nl'],
    'pl' => ['Polski', 'pl', 'pl'],
    'ru' => ['Русский', 'ru', 'ru'],
    'tr' => ['Türkçe', 'tr', 'tr'],
    'hi' => ['हिन्दी', 'in', 'hi'],
    'id' => ['Bahasa Indonesia', 'id', 'id'],
    'ms' => ['Bahasa Melayu', 'my', 'ms'],
    'th' => ['ไทย', 'th', 'th'],
    'vi' => ['Tiếng Việt', 'vn', 'vi'],
    'zh' => ['简体中文', 'cn', 'zh-CN'],
    'zh-TW' => ['繁體中文', 'tw', 'zh-TW'],
    'ja' => ['日本語', 'jp', 'ja'],
    'ko' => ['한국어', 'kr', 'ko'],
];

/** Forces a language for a while (e.g. 'en' while writing an email); null = back to the visitor's choice. */
function lang_override(?string $code = null, bool $set = false): ?string
{
    static $forced = null;
    if ($set) $forced = $code;
    return $forced;
}

function current_lang(): string
{
    if ($forced = lang_override()) return $forced;
    static $lang = null;
    if ($lang !== null) return $lang;
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (PHP_SAPI === 'cli' || str_contains($script, '/admin/')) return $lang = 'en';
    $c = (string) ($_COOKIE['tc_lang'] ?? '');
    if (isset(LANGUAGES[$c])) return $lang = $c;
    // First visit: the browser's preferred language, if we have it.
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $tag = strtolower(trim(explode(';', $part)[0]));
        if ($tag === '') continue;
        if (in_array($tag, ['zh-tw', 'zh-hk', 'zh-hant', 'zh-mo'], true) || str_starts_with($tag, 'zh-hant')) return $lang = 'zh-TW';
        $base = explode('-', $tag)[0];
        if ($base === 'tl') $base = 'fil';
        if (isset(LANGUAGES[$base])) return $lang = $base;
    }
    return $lang = 'en';
}

/** Runs $fn with the site in English (emails, records), then restores the visitor's language. */
function in_english(callable $fn): mixed
{
    $before = lang_override();
    lang_override('en', true);
    try {
        return $fn();
    } finally {
        lang_override($before, true);
    }
}

function translations(string $lang): array
{
    static $loaded = [];
    if (!isset($loaded[$lang])) {
        $file = dirname(__DIR__) . '/lang/' . $lang . '.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $loaded[$lang] = is_array($data) ? $data : [];
    }
    return $loaded[$lang];
}

/**
 * Translates English UI text into the visitor's language (plain text — escape it with e()).
 * {name} placeholders are filled from $vars: t('Hi, {name}!', ['name' => 'Ana']).
 */
function t(string $en, array $vars = []): string
{
    $lang = current_lang();
    $text = $en;
    if ($lang !== 'en') {
        $tr = translations($lang)[$en] ?? null;
        if (is_string($tr) && $tr !== '') $text = $tr;
        elseif (getenv('I18N_RECORD')) i18n_record_missing($en);
    }
    if ($vars) {
        $text = strtr($text, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', array_values($vars))));
    }
    return $text;
}

/** Like t(), escaped for HTML; $html vars are inserted as-is (they must already be safe HTML). */
function th(string $en, array $vars = [], array $html = []): string
{
    $out = e(t($en, $vars));
    foreach ($html as $k => $v) $out = str_replace('{' . $k . '}', $v, $out);
    return $out;
}

/** "1 traveler" / "3 travelers", translated. */
function tn(int $n, string $one, string $many): string
{
    return t($n === 1 ? $one : $many, ['n' => $n]);
}

/** Development aid: lists English text that has no translation yet (only when I18N_RECORD is set). */
function i18n_record_missing(string $en): void
{
    static $seen = [];
    if (isset($seen[$en])) return;
    $seen[$en] = true;
    @file_put_contents(dirname(__DIR__) . '/storage/i18n-missing.txt', json_encode($en, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function html_lang(): string
{
    return LANGUAGES[current_lang()][2];
}

function lang_flag(string $code): string
{
    $flag = LANGUAGES[$code][1] ?? 'gb';
    return '<img src="' . e(asset('flags/' . $flag . '.svg')) . '" alt="" width="20" height="15" class="h-[15px] w-5 shrink-0 rounded-sm object-cover ring-1 ring-black/10">';
}

/** Localized date, e.g. "Mon, Oct 5" in English or "lun., 5 oct." in French. */
function fmt_local(DateTimeImmutable $d, string $pattern, string $english): string
{
    $lang = current_lang();
    if ($lang === 'en' || !class_exists('IntlDateFormatter')) return $d->format($english);
    $f = new IntlDateFormatter(str_replace('-', '_', html_lang()), IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, $pattern);
    $out = $f->format($d);
    return $out === false ? $d->format($english) : $out;
}

// ---------- Currencies ----------
// Prices are always charged in US dollars. Other currencies are shown for convenience,
// converted with real daily exchange rates. Without fresh rates, the site shows US dollars only.

/** code => [symbol, name] */
const CURRENCIES = [
    'USD' => ['$', 'US dollar'],
    'PHP' => ['₱', 'Philippine peso'],
    'EUR' => ['€', 'Euro'],
    'GBP' => ['£', 'British pound'],
    'AUD' => ['A$', 'Australian dollar'],
    'CAD' => ['C$', 'Canadian dollar'],
    'NZD' => ['NZ$', 'New Zealand dollar'],
    'SGD' => ['S$', 'Singapore dollar'],
    'HKD' => ['HK$', 'Hong Kong dollar'],
    'JPY' => ['¥', 'Japanese yen'],
    'CNY' => ['CN¥', 'Chinese yuan'],
    'KRW' => ['₩', 'South Korean won'],
    'TWD' => ['NT$', 'New Taiwan dollar'],
    'THB' => ['฿', 'Thai baht'],
    'VND' => ['₫', 'Vietnamese dong'],
    'IDR' => ['Rp', 'Indonesian rupiah'],
    'MYR' => ['RM', 'Malaysian ringgit'],
    'INR' => ['₹', 'Indian rupee'],
    'AED' => ['AED', 'UAE dirham'],
    'SAR' => ['SAR', 'Saudi riyal'],
    'QAR' => ['QAR', 'Qatari riyal'],
    'KWD' => ['KWD', 'Kuwaiti dinar'],
    'CHF' => ['CHF', 'Swiss franc'],
    'SEK' => ['kr', 'Swedish krona'],
    'NOK' => ['kr', 'Norwegian krone'],
    'DKK' => ['kr', 'Danish krone'],
    'PLN' => ['zł', 'Polish złoty'],
    'TRY' => ['₺', 'Turkish lira'],
    'RUB' => ['₽', 'Russian ruble'],
    'BRL' => ['R$', 'Brazilian real'],
    'MXN' => ['MX$', 'Mexican peso'],
    'ZAR' => ['R', 'South African rand'],
];

/** The currency the visitor picked, if we have a fresh rate for it; otherwise USD. */
function current_currency(): string
{
    $c = strtoupper((string) ($_COOKIE['tc_cur'] ?? 'USD'));
    if ($c === 'USD' || !isset(CURRENCIES[$c]) || str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/')) return 'USD';
    return isset(fx_rates()['rates'][$c]) ? $c : 'USD';
}

/**
 * Real exchange rates (units of each currency per 1 USD), refreshed every 12 hours from
 * open.er-api.com (ExchangeRate-API), with the European Central Bank (Frankfurter) as backup.
 * Returns [] when no rates newer than 3 days are available — then only US dollars are shown.
 */
function fx_rates(): array
{
    static $memo = null;
    if ($memo !== null) return $memo;
    $file = dirname(__DIR__) . '/storage/fx-rates.json';
    $cache = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $cache = is_array($cache) ? $cache : [];
    $fresh = ($cache['fetched_at'] ?? 0) > time() - 12 * 3600;
    $recentTry = ($cache['tried_at'] ?? 0) > time() - 900;
    if (!$fresh && !$recentTry && PHP_SAPI !== 'cli' && config('fx_api_url') !== '') {
        $cache['tried_at'] = time();
        $new = fx_download();
        if ($new) $cache = $new + ['tried_at' => time()];
        @file_put_contents($file, json_encode($cache), LOCK_EX);
    }
    $usable = !empty($cache['rates']) && ($cache['fetched_at'] ?? 0) > time() - 3 * 86400;
    return $memo = $usable ? $cache : [];
}

function fx_download(): ?array
{
    $sources = [
        (string) config('fx_api_url') => fn($j) => ($j['result'] ?? '') === 'success' ? [$j['rates'] ?? [], 'ExchangeRate-API', (int) ($j['time_last_update_unix'] ?? time())] : null,
        (string) config('fx_backup_url') => fn($j) => isset($j['rates']) ? [$j['rates'] + ['USD' => 1], 'European Central Bank', strtotime((string) ($j['date'] ?? '')) ?: time()] : null,
    ];
    foreach ($sources as $url => $read) {
        if ($url === '') continue;
        try {
            $res = http_json('GET', $url, [], null, 6);
            $got = $res['status'] === 200 ? $read($res['json']) : null;
            if (!$got) continue;
            [$all, $source, $updated] = $got;
            $rates = [];
            foreach (array_keys(CURRENCIES) as $code) {
                $r = $all[$code] ?? null;
                if (is_numeric($r) && (float) $r > 0) $rates[$code] = (float) $r;
            }
            if (count($rates) > 5) return ['rates' => $rates, 'source' => $source, 'updated_at' => $updated, 'fetched_at' => time()];
        } catch (Throwable $e) {
            error_log('[fx] ' . $e->getMessage());
        }
    }
    return null;
}

/** A US-dollar amount shown in the visitor's currency, e.g. "₱6,950" (or "$124"). */
function price(float|int $usd, ?string $currency = null): string
{
    $cur = $currency ?? current_currency();
    if ($cur === 'USD') return money($usd);
    $rate = fx_rates()['rates'][$cur] ?? null;
    if (!$rate) return money($usd);
    return CURRENCIES[$cur][0] . number_format(round((float) $usd * $rate), 0);
}

/** "≈ ₱6,950" next to a US-dollar total, or '' when the visitor uses US dollars. */
function price_hint(float|int $usd): string
{
    return current_currency() === 'USD' ? '' : '≈ ' . price($usd);
}

/** Data the browser needs to show prices (range sliders) in the visitor's currency. */
function currency_attrs(): string
{
    $cur = current_currency();
    $rate = $cur === 'USD' ? 1 : (fx_rates()['rates'][$cur] ?? 1);
    return 'data-cur-symbol="' . e(CURRENCIES[$cur][0]) . '" data-cur-rate="' . e((string) $rate) . '"';
}

/** The few texts app.js shows, in the visitor's language. */
function js_strings(): array
{
    if (current_lang() === 'en') return [];
    // i18n-keys: 'Economy', 'Premium Economy', 'Business', 'First', '{n} traveler', '{n} travelers', '{n} room', '{n} rooms', "Please choose where you're flying from and to.", 'Origin and destination must be different.', 'Please choose a destination.', 'Leaving from and going to must be different.', 'Hide', 'Show', 'Hide password', 'Show password'
    $keys = ['Economy', 'Premium Economy', 'Business', 'First', '{n} traveler', '{n} travelers', '{n} room', '{n} rooms',
        "Please choose where you're flying from and to.", 'Origin and destination must be different.', 'Please choose a destination.',
        'Leaving from and going to must be different.', 'Hide', 'Show', 'Hide password', 'Show password'];
    $out = [];
    foreach ($keys as $k) $out[$k] = t($k);
    return $out;
}

/**
 * Shows text from a saved quote (always stored in English) in the visitor's language:
 * known labels and patterns are translated; supplier text (airline, hotel, fare rules) stays as it is.
 * $startIso: the trip's first date, used to put stored dates like "Mon, Oct 5" back into the right year.
 */
function quote_text(?string $s, ?string $startIso = null): string
{
    $s = (string) $s;
    if ($s === '' || current_lang() === 'en') return $s;
    // i18n-keys: 'Refundable', 'Non-refundable', 'Free cancellation', 'Economy', 'Premium Economy', 'Business', 'First', 'Round trip', 'One way', 'Taxes & fees', 'Carry-on included', 'No carry-on included', 'checked bag included', 'checked bag not included', 'Round-trip flights', 'Hotel', 'Rental car', "Live airline fare. Fares can change until your ticket is issued — we'll confirm before charging any difference.", 'Live hotel rate. Some cities charge a local tourist tax, payable at the hotel.'
    $whole = t($s);
    if ($whole !== $s) return $whole;
    $m = [];
    if (preg_match('/^Flight for (\d+) travelers?$/', $s, $m)) return tn((int) $m[1], 'Flight for {n} traveler', 'Flight for {n} travelers');
    if (preg_match('/^(\d+) × (adult fare|child fare|infant fare \(on lap\))$/', $s, $m)) {
        // i18n-keys: '{n} × adult fare', '{n} × child fare', '{n} × infant fare (on lap)'
        return t('{n} × ' . $m[2], ['n' => $m[1]]);
    }
    if (preg_match('/^(\d+) × (\S+) per person$/u', $s, $m)) return t('{n} × {price} per person', ['n' => $m[1], 'price' => $m[2]]);
    if (preg_match('/^(\d+) nights? × (\d+) rooms?( \(taxes included\))?$/', $s, $m)) {
        $stay = t('{nights} × {rooms}', ['nights' => tn((int) $m[1], '{n} night', '{n} nights'), 'rooms' => tn((int) $m[2], '{n} room', '{n} rooms')]);
        return !empty($m[3]) ? t('{stay} (taxes included)', ['stay' => $stay]) : $stay;
    }
    if (preg_match('/^(\d+) \(incl\. (\d+) infants?\)$/', $s, $m)) return t('{n} (incl. {infants})', ['n' => $m[1], 'infants' => tn((int) $m[2], '{n} infant', '{n} infants')]);
    if (preg_match('/^(\d+) \((\d+) adults?(?:, (\d+) child(?:ren)?)?\)$/', $s, $m)) {
        $who = tn((int) $m[2], '{n} adult', '{n} adults') . (!empty($m[3]) ? ', ' . tn((int) $m[3], '{n} child', '{n} children') : '');
        return $m[1] . ' (' . $who . ')';
    }
    if (preg_match('/^checked bag from (\S+)$/u', $s, $m)) return t('checked bag from {price}', ['price' => $m[1]]);
    if (preg_match('/^(?:[A-Z][a-z]{2}, )?[A-Z][a-z]{2} \d{1,2}$/', $s)) return quote_date($s, $startIso);
    if (preg_match('/^((?:[A-Z][a-z]{2}, )?[A-Z][a-z]{2} \d{1,2}) – ((?:[A-Z][a-z]{2}, )?[A-Z][a-z]{2} \d{1,2})$/u', $s, $m)) {
        return quote_date($m[1], $startIso) . ' – ' . quote_date($m[2], $startIso);
    }
    // Joined parts ("Carry-on included · checked bag from $25", "Hotel, Rental car, …", subtitles)
    foreach ([' · ', ', '] as $sep) {
        if (str_contains($s, $sep)) {
            $parts = explode($sep, $s);
            $out = array_map(fn($p) => quote_text($p, $startIso), $parts);
            if ($out !== $parts) return implode($sep, $out);
        }
    }
    return $s;
}

/** A stored English date ("Mon, Oct 5") shown in the visitor's language. */
function quote_date(string $s, ?string $startIso): string
{
    if (!$startIso || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startIso)) return $s;
    $md = preg_replace('/^[A-Z][a-z]{2}, /', '', $s);
    $year = (int) substr($startIso, 0, 4);
    foreach ([$year, $year + 1] as $y) {
        $d = DateTimeImmutable::createFromFormat('!M j Y', "$md $y", new DateTimeZone('UTC'));
        if ($d && $d->format('Y-m-d') >= add_days($startIso, -1)) return fmt_date($d->format('Y-m-d'));
    }
    return $s;
}
