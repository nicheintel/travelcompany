<?php
declare(strict_types=1);
defined('TC_APP') || exit;

// ---------- Icons (inline SVG) ----------

const ICONS = [
    'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
    'bed' => '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>',
    'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.6A2 2 0 0 0 13.7 6H8.3a2 2 0 0 0-1.6.8L4 10l-1.5.5C1.7 10.8 1 11.6 1 12.5V16c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
    'package' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
    'swap' => '<path d="m16 3 4 4-4 4M20 7H4M8 21l-4-4 4-4M4 17h16"/>',
    'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'check' => '<path d="M20 6 9 17l-5-5"/>',
    'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    'tag' => '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    'headset' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
];

function icon(string $name, int $size = 20, string $class = '', float $stroke = 1.8): string
{
    $paths = ICONS[$name] ?? '';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke
        . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="' . e($class) . '">' . $paths . '</svg>';
}

function star_icons(int $n, int $size = 13): string
{
    $star = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>';
    return str_repeat($star, max(0, $n));
}

// ---------- Form pieces ----------

const FIELD_BOX = 'flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100';
const FIELD_LABEL = 'text-xs font-medium uppercase tracking-wide text-slate-500';
const FIELD_INPUT = 'w-full bg-transparent text-base font-semibold text-slate-900 outline-none placeholder:font-normal placeholder:text-slate-400';

/** City/airport box with autocomplete (app.js). Submits the IATA code in a hidden input. */
function airport_field(string $name, string $label, string $placeholder, ?string $code): string
{
    $a = airport($code);
    $display = $a ? "{$a['city']} ({$a['code']})" : '';
    return '<div class="relative" data-airport>'
        . '<label class="' . FIELD_BOX . '"><span class="' . FIELD_LABEL . '">' . e($label) . '</span>'
        . '<span class="flex items-center gap-2">' . icon('pin', 16, 'shrink-0 text-brand-500')
        . '<input type="text" autocomplete="off" role="combobox" aria-expanded="false" placeholder="' . e($placeholder) . '" value="' . e($display) . '" class="' . FIELD_INPUT . ' truncate" data-airport-input></span></label>'
        . '<input type="hidden" name="' . e($name) . '" value="' . e($a['code'] ?? '') . '" data-airport-code>'
        . '<ul role="listbox" class="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white py-2 shadow-xl sm:min-w-80" data-airport-list></ul>'
        . '</div>';
}

/**
 * Date box. $value may be empty: app.js then fills it with today + $defaultOffset days
 * (or $afterDays after the field named in $after), so cached pages never show stale dates.
 */
function date_field(string $name, string $label, string $value = '', int $defaultOffset = 14, string $after = '', int $afterDays = 0): string
{
    return '<label class="' . FIELD_BOX . '" data-date-box><span class="' . FIELD_LABEL . '">' . e($label) . '</span>'
        . '<span class="flex items-center gap-2">' . icon('calendar', 16, 'shrink-0 text-brand-500')
        . '<input type="date" name="' . e($name) . '" value="' . e($value) . '" required class="' . FIELD_INPUT . '"'
        . ' data-date data-offset="' . $defaultOffset . '"' . ($after ? ' data-after="' . e($after) . '" data-after-days="' . $afterDays . '"' : '') . '></span></label>';
}

/** Travelers popover with counters (app.js). */
function travelers_field(int $adults, int $children, ?int $rooms = null, ?string $cabin = null): string
{
    $counter = fn(string $key, string $label, string $hint, int $v, int $min, int $max) =>
        '<div class="flex items-center justify-between py-2"><div><p class="font-medium text-slate-900">' . $label . '</p><p class="text-xs text-slate-500">' . $hint . '</p></div>'
        . '<div class="flex items-center gap-3" data-counter="' . $key . '" data-min="' . $min . '" data-max="' . $max . '">'
        . '<button type="button" data-step="-1" class="grid h-8 w-8 place-items-center rounded-full border border-slate-300 text-lg text-slate-700 hover:border-brand-500 hover:text-brand-600 disabled:opacity-30" aria-label="Fewer ' . strtolower($label) . '">−</button>'
        . '<span class="w-4 text-center font-semibold" data-count>' . $v . '</span>'
        . '<button type="button" data-step="1" class="grid h-8 w-8 place-items-center rounded-full border border-slate-300 text-lg text-slate-700 hover:border-brand-500 hover:text-brand-600 disabled:opacity-30" aria-label="More ' . strtolower($label) . '">+</button></div></div>';

    $html = '<div class="relative" data-travelers>'
        . '<button type="button" aria-expanded="false" class="flex w-full flex-col rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-left transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" data-travelers-toggle>'
        . '<span class="' . FIELD_LABEL . '">' . ($rooms !== null ? 'Travelers &amp; rooms' : 'Travelers &amp; class') . '</span>'
        . '<span class="flex items-center gap-2">' . icon('user', 16, 'shrink-0 text-brand-500') . '<span class="truncate text-base font-semibold text-slate-900" data-travelers-summary></span></span></button>'
        . '<input type="hidden" name="adults" value="' . $adults . '"><input type="hidden" name="children" value="' . $children . '">'
        . ($rooms !== null ? '<input type="hidden" name="rooms" value="' . $rooms . '">' : '')
        . ($cabin !== null ? '<input type="hidden" name="cabin" value="' . e($cabin) . '">' : '')
        . '<div class="absolute right-0 top-full z-30 mt-2 hidden w-full min-w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-xl" data-travelers-panel>'
        . $counter('adults', 'Adults', 'Age 12+', $adults, 1, 9)
        . $counter('children', 'Children', 'Age 2–11', $children, 0, 8)
        . ($rooms !== null ? $counter('rooms', 'Rooms', 'Max 4 per booking', $rooms, 1, 4) : '');
    if ($cabin !== null) {
        $html .= '<div class="mt-2 border-t border-slate-100 pt-3"><p class="mb-2 text-sm font-medium text-slate-900">Cabin class</p><div class="grid grid-cols-2 gap-2">';
        foreach (CABIN_LABELS as $key => $label) {
            $html .= '<button type="button" data-cabin="' . $key . '" class="rounded-lg border px-3 py-2 text-sm border-slate-200 text-slate-700 hover:border-slate-300">' . $label . '</button>';
        }
        $html .= '</div></div>';
    }
    return $html . '<button type="button" class="mt-4 w-full rounded-lg bg-brand-600 py-2 text-sm font-semibold text-white hover:bg-brand-700" data-travelers-done>Done</button></div></div>';
}

function search_button(string $label): string
{
    return '<button type="submit" class="flex h-full min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-accent-500 px-6 text-base font-bold text-white shadow-lg shadow-accent-500/30 transition hover:bg-accent-600">'
        . icon('search', 18) . '<span>' . e($label) . '</span></button>';
}

function flight_search_form(array $d = []): string
{
    $trip = $d['trip'] ?? 'roundtrip';
    $pill = fn($value, $label) => '<label class="cursor-pointer rounded-full border px-4 py-1.5 text-sm font-medium transition border-slate-200 bg-white text-slate-700 hover:border-slate-300 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-600 has-[:checked]:text-white">'
        . '<input type="radio" name="trip" value="' . $value . '"' . ($trip === $value ? ' checked' : '') . ' class="sr-only" data-trip>' . $label . '</label>';
    return '<form action="' . e(url('flights.php')) . '" method="get" class="space-y-4" data-search-form="flight">'
        . '<div class="flex flex-wrap items-center gap-2">' . $pill('roundtrip', 'Round trip') . $pill('oneway', 'One way') . '</div>'
        . '<div class="grid gap-3 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_1fr_auto]">'
        . '<div class="relative grid gap-3 sm:grid-cols-2 lg:col-span-2">'
        . airport_field('from', 'From', 'City or airport', $d['from'] ?? 'JFK')
        . '<button type="button" class="absolute left-1/2 top-1/2 z-10 hidden h-9 w-9 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-brand-600 shadow-sm hover:bg-brand-50 sm:grid" aria-label="Swap origin and destination" data-swap>' . icon('swap', 16) . '</button>'
        . airport_field('to', 'To', 'Where to?', $d['to'] ?? null)
        . '</div>'
        . date_field('depart', 'Depart', $d['depart'] ?? '', 14)
        . '<div data-return-box>' . date_field('return', 'Return', $d['return'] ?? '', 21, 'depart', 7) . '</div>'
        . travelers_field($d['adults'] ?? 1, $d['children'] ?? 0, null, $d['cabin'] ?? 'economy')
        . search_button('Search')
        . '</div><p role="alert" class="hidden text-sm font-medium text-red-600" data-form-error></p></form>';
}

/** Destination picker for the promo packages created on Admin → Packages. */
function package_search_form(array $d = []): string
{
    $dests = [];
    foreach (active_packages() as $p) $dests[$p['to_code']] = "{$p['destination']}, {$p['country']}";
    asort($dests);
    if (!$dests) {
        return '<div class="flex flex-col gap-3 rounded-xl bg-slate-50 p-5 sm:flex-row sm:items-center sm:justify-between">'
            . '<p class="text-slate-700"><strong class="text-slate-900">New promo packages are coming soon.</strong> Create a free account and our travel assistants can put together a flight + hotel trip for you.</p>'
            . '<a href="' . e(url('register.php')) . '" class="shrink-0 rounded-xl bg-accent-500 px-5 py-3 text-center font-semibold text-white hover:bg-accent-600">Create free account</a></div>';
    }
    $options = '<option value="">All destinations</option>';
    foreach ($dests as $code => $label) {
        $options .= '<option value="' . e($code) . '"' . (($d['to'] ?? '') === $code ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return '<form action="' . e(url('packages.php')) . '#results" method="get" class="space-y-4" data-search-form="package">'
        . '<div class="flex flex-wrap items-center gap-2 text-sm">'
        . '<span class="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 font-medium text-brand-700">' . icon('plane', 14) . ' Flight</span><span class="text-slate-400">+</span>'
        . '<span class="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 font-medium text-brand-700">' . icon('bed', 14) . ' Hotel</span>'
        . '<span class="text-slate-500">· some packages include a car</span></div>'
        . '<div class="grid gap-3 sm:grid-cols-[1fr_auto]">'
        . '<label class="' . FIELD_BOX . '"><span class="' . FIELD_LABEL . '">Going to</span><span class="flex items-center gap-2">' . icon('pin', 16, 'shrink-0 text-brand-500')
        . '<select name="to" class="' . FIELD_INPUT . '">' . $options . '</select></span></label>'
        . search_button('See packages')
        . '</div></form>';
}

function hotel_search_form(array $d = []): string
{
    return '<form action="' . e(url('hotels.php')) . '" method="get" class="space-y-3" data-search-form="hotel">'
        . '<div class="grid gap-3 lg:grid-cols-[2fr_0.8fr_0.8fr_1fr_auto]">'
        . airport_field('to', 'Destination', 'City, e.g. Bangkok', $d['to'] ?? null)
        . date_field('checkin', 'Check-in', $d['checkin'] ?? '', 14)
        . date_field('checkout', 'Check-out', $d['checkout'] ?? '', 17, 'checkin', 3)
        . travelers_field($d['adults'] ?? 2, $d['children'] ?? 0, $d['rooms'] ?? 1)
        . search_button('Search')
        . '</div><p role="alert" class="hidden text-sm font-medium text-red-600" data-form-error></p></form>';
}

// ---------- Cards & summaries ----------

function package_card(array $p): string
{
    $save = $p['was_price'] && $p['was_price'] > $p['price'] ? $p['was_price'] - $p['price'] : 0;
    $chip = fn($i, $t) => '<span class="flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-brand-700">' . icon($i, 12) . " $t</span>";
    $perks = implode('', array_map(fn($x) => '<li class="flex items-start gap-2">' . icon('check', 14, 'mt-0.5 shrink-0 text-emerald-500') . e($x) . '</li>', array_slice($p['highlights'], 0, 4)));
    [$first, $last] = package_dates($p);
    $photo = upload_url($p['image']);
    $top = $photo
        ? '<img src="' . e($photo) . '" alt="' . e($p['destination']) . '" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"><div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-black/20"></div>'
        : '<div class="absolute inset-0 bg-gradient-to-br ' . $p['gradient'] . '"></div><svg class="absolute inset-0 h-full w-full opacity-20" viewBox="0 0 400 180" preserveAspectRatio="none" aria-hidden="true"><path d="M0 140 Q100 100 200 130 T400 120 V180 H0Z" fill="white"/><path d="M0 160 Q120 130 240 155 T400 150 V180 H0Z" fill="white"/></svg>';
    return '<article id="' . e($p['slug']) . '" class="group flex scroll-mt-24 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">'
        . '<div class="relative h-48 overflow-hidden p-4 text-white">' . $top
        . '<div class="relative flex items-start justify-between">' . ($p['badge'] ? '<span class="rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-slate-900 shadow">' . e($p['badge']) . '</span>' : '<span></span>')
        . ($save ? '<span class="rounded-full bg-accent-500 px-3 py-1 text-xs font-bold shadow">-' . (int) round($save / $p['was_price'] * 100) . '%</span>' : '') . '</div>'
        . '<div class="absolute bottom-4 left-4 right-4"><p class="text-sm font-medium text-white/90">' . e($p['country']) . ' · ' . $p['nights'] . ' nights</p><h3 class="text-xl font-bold drop-shadow">' . e($p['destination']) . '</h3></div></div>'
        . '<div class="flex flex-1 flex-col p-5"><h4 class="font-semibold text-slate-900">' . e($p['title']) . '</h4>'
        . '<div class="mt-1 flex items-center gap-2 text-sm text-slate-600">' . ($p['stars'] ? '<span class="flex text-amber-400">' . star_icons($p['stars']) . '</span>' : '') . '<span class="truncate">' . e($p['hotel']) . '</span></div>'
        . '<p class="mt-1 text-xs text-slate-500">From ' . e($p['from_city']) . ' · travel ' . e(fmt_date($first)) . ' – ' . e(fmt_date($last)) . '</p>'
        . '<div class="mt-3 flex flex-wrap gap-1.5 text-xs font-medium">' . $chip('plane', 'Flight') . $chip('bed', 'Hotel') . ($p['car'] ? $chip('car', 'Car') : '') . '</div>'
        . ($perks ? '<ul class="mt-3 space-y-1 text-sm text-slate-600">' . $perks . '</ul>' : '')
        . '<div class="mt-auto flex items-end justify-between gap-3 pt-5"><div>'
        . ($save ? '<p class="text-sm text-slate-400 line-through">' . money($p['was_price']) . '</p>' : '')
        . '<p class="text-2xl font-extrabold text-slate-900">' . money($p['price']) . '<span class="text-xs font-medium text-slate-500"> /person</span></p>'
        . ($save ? '<p class="text-xs font-semibold text-emerald-600">You save ' . money($save) . '</p>' : '') . '</div>'
        . '<a href="' . e(url('book.php', ['kind' => 'package', 'id' => $p['slug']])) . '" class="shrink-0 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Book deal</a></div></div></article>';
}

function leg_row(array $leg, string $label): string
{
    $stopText = $leg['stops'] === 0 ? 'Nonstop' : plural($leg['stops'], 'stop') . ' · ' . implode(', ', $leg['stop_cities']);
    $dots = str_repeat('<span class="mx-1 h-2 w-2 rounded-full border-2 border-accent-500 bg-white"></span>', count($leg['stop_cities']));
    return '<div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 sm:gap-6">'
        . '<div><p class="text-xs font-medium uppercase text-slate-400">' . e($label) . ' · ' . e(fmt_date($leg['date'])) . '</p>'
        . '<p class="text-lg font-bold text-slate-900">' . fmt_time($leg['depart']) . '</p><p class="text-sm text-slate-500">' . e($leg['from']) . '</p></div>'
        . '<div class="text-center"><p class="text-xs text-slate-500">' . fmt_duration($leg['duration']) . '</p>'
        . '<div class="relative my-1 flex items-center"><span class="h-px flex-1 bg-slate-300"></span>' . $dots . icon('plane', 14, 'ml-1 rotate-45 text-slate-400') . '</div>'
        . '<p class="text-xs font-medium ' . ($leg['stops'] === 0 ? 'text-emerald-600' : 'text-accent-600') . '">' . e($stopText) . '</p></div>'
        . '<div class="text-right"><p class="text-xs font-medium uppercase text-slate-400">' . e($leg['flight_number']) . '</p>'
        . '<p class="text-lg font-bold text-slate-900">' . fmt_time($leg['arrive']) . ($leg['day_offset'] > 0 ? '<sup class="ml-0.5 text-xs text-accent-600">+' . $leg['day_offset'] . '</sup>' : '') . '</p>'
        . '<p class="text-sm text-slate-500">' . e($leg['to']) . '</p></div></div>';
}

/** Itinerary + price breakdown, on the booking page and trip pages. */
function trip_summary(array $q): string
{
    $kinds = ['flight' => ['plane', 'Flight'], 'package' => ['package', 'Flight + Hotel package'], 'hotel' => ['bed', 'Hotel']];
    [$ic, $label] = $kinds[$q['kind']];
    $html = '<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">'
        . '<div class="bg-gradient-to-br from-brand-800 to-brand-600 p-5 text-white"><p class="flex items-center gap-2 text-sm font-medium text-brand-100">' . icon($ic, 16) . ' ' . $label . '</p>'
        . '<h2 class="mt-1 text-xl font-bold">' . e($q['title']) . '</h2><p class="mt-1 text-sm text-brand-100">' . e($q['subtitle']) . '</p></div>';
    if (!empty($q['flight'])) {
        $html .= '<div class="space-y-4 border-b border-slate-100 p-5">' . leg_row($q['flight']['outbound'], 'Depart')
            . ($q['flight']['inbound'] ? leg_row($q['flight']['inbound'], 'Return') : '') . '</div>';
    }
    $html .= '<dl class="space-y-2 border-b border-slate-100 p-5 text-sm">';
    foreach ($q['facts'] as [$k, $v]) {
        $html .= '<div class="flex justify-between gap-4"><dt class="text-slate-500">' . e($k) . '</dt><dd class="text-right font-medium text-slate-900">' . e($v) . '</dd></div>';
    }
    $html .= '</dl><dl class="space-y-2 p-5 text-sm">';
    foreach ($q['lines'] as $l) {
        $html .= '<div class="flex justify-between"><dt class="text-slate-600">' . e($l['label']) . '</dt><dd class="text-slate-900">' . money($l['amount']) . '</dd></div>';
    }
    if ($q['discount'] > 0) {
        $html .= '<div class="flex justify-between text-emerald-700"><dt>Member discount (' . round($q['discount_rate'] * 100) . '%)</dt><dd>−' . money($q['discount']) . '</dd></div>';
    }
    $html .= '<div class="flex items-end justify-between border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-900">Total</dt><dd class="text-2xl font-extrabold text-slate-900">' . money($q['total']) . '</dd></div>'
        . '<p class="text-right text-xs text-slate-500">Taxes and fees included</p>'
        . (!empty($q['note']) ? '<p class="pt-2 text-xs leading-relaxed text-slate-500">' . e($q['note']) . '</p>' : '')
        . '</dl></div>';
    return $html;
}

function status_badge(string $status): string
{
    $map = ['reserved' => ['bg-amber-100 text-amber-800', 'Unpaid'], 'paid' => ['bg-red-100 text-red-700', 'Paid · to ticket'], 'ticketed' => ['bg-emerald-100 text-emerald-800', 'Ticketed'], 'cancelled' => ['bg-slate-200 text-slate-600', 'Cancelled']];
    [$cls, $label] = $map[$status] ?? $map['reserved'];
    return '<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-bold ' . $cls . '">' . $label . '</span>';
}

/** Labelled input with optional error. Password fields get a Show/Hide toggle. */
function text_field(string $name, string $label, string $value = '', string $type = 'text', ?string $error = null, array $attrs = [], ?string $hint = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e($k) . '="' . e($v) . '"';
    }
    $border = $error ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100';
    $pw = $type === 'password';
    return '<div><label for="' . $id . '" class="mb-1.5 block text-sm font-medium text-slate-700">' . e($label) . '</label><div class="relative">'
        . '<input id="' . $id . '" name="' . e($name) . '" type="' . e($type) . '"' . ($pw ? '' : ' value="' . e($value) . '"')
        . ($error ? ' aria-invalid="true" aria-describedby="' . $id . '_err"' : '') . $extra
        . ' class="w-full rounded-xl border bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-2 ' . ($pw ? 'pr-16 ' : '') . $border . '">'
        . ($pw ? '<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50" data-toggle-password aria-label="Show password">Show</button>' : '')
        . '</div>'
        . ($error ? '<p id="' . $id . '_err" class="mt-1.5 text-sm text-red-600">' . e($error) . '</p>' : ($hint ? '<p class="mt-1.5 text-xs text-slate-500">' . e($hint) . '</p>' : ''))
        . '</div>';
}

function alert_box(?string $message, string $type = 'error'): string
{
    if (!$message) return '';
    $cls = $type === 'error' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    return '<p role="' . ($type === 'error' ? 'alert' : 'status') . '" class="rounded-xl px-4 py-3 text-sm font-medium ring-1 ' . $cls . '">' . e($message) . '</p>';
}

function submit_button(string $label, string $pendingLabel, string $class = 'w-full'): string
{
    return '<button type="submit" data-pending="' . e($pendingLabel) . '" class="flex items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70 ' . $class . '">' . e($label) . '</button>';
}

function results_notice(array $result, string $what): string
{
    if (!empty($result['not_connected'])) {
        return '<div class="mb-4 rounded-2xl border border-slate-200 bg-white p-8 text-center">'
            . '<p class="text-lg font-semibold text-slate-900">Live ' . $what . ' search isn\'t available yet</p>'
            . '<p class="mt-1 text-slate-600">Please contact our travel assistants and we\'ll find the best ' . $what . ' for you.</p>'
            . (is_admin() ? '<p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">Admin: add your ' . ($what === 'flights' ? 'Duffel' : 'LiteAPI') . ' key on <a class="font-semibold underline" href="' . e(url('admin/settings.php')) . '">Admin → Site settings</a> to show real ' . $what . '.</p>' : '')
            . '</div>';
    }
    if ($result['error']) return '<div class="mb-4">' . alert_box($result['error']) . '</div>';
    if (!$result['items']) {
        return '<p class="mb-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">No ' . $what . ' available for these dates. Try different dates or a nearby airport.</p>';
    }
    if (!$result['live']) {
        return '<p class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-200">DEMO MODE — these ' . $what . ' are made up for testing, not real. Turn off demo_mode before showing the site to customers.</p>';
    }
    return '';
}

// ---------- Auth page shell ----------

function auth_shell_open(string $heading, string $subtitle, ?string $notice = null): string
{
    $perks = ['Member-only prices — extra savings on flights & packages', 'Save trips and pick up where you left off', 'Price alerts when fares to your destination drop', 'Faster checkout with your saved traveler details'];
    $li = implode('', array_map(fn($p) => '<li class="flex items-start gap-3 text-brand-50"><span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/15">' . icon('check', 14) . '</span>' . e($p) . '</li>', $perks));
    return '<section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_1.1fr] lg:py-16">'
        . '<div class="relative hidden overflow-hidden rounded-3xl bg-gradient-to-br from-brand-900 via-brand-700 to-brand-500 p-10 text-white lg:block">'
        . icon('plane', 220, 'absolute -right-6 top-10 rotate-12 text-white/10', 1)
        . '<div class="relative"><p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium ring-1 ring-white/20"><span class="h-2 w-2 rounded-full bg-accent-400"></span>Free membership</p>'
        . '<h2 class="mt-6 text-3xl font-extrabold leading-tight">Travel smarter with your <span class="text-accent-400">free account</span></h2><ul class="mt-8 space-y-4">' . $li . '</ul></div></div>'
        . '<div class="mx-auto w-full max-w-md self-center">'
        . ($notice ? '<p class="mb-5 rounded-xl bg-accent-500/10 px-4 py-3 text-sm font-medium text-accent-600 ring-1 ring-accent-500/30">' . e($notice) . '</p>' : '')
        . '<h1 class="text-3xl font-bold text-slate-900">' . e($heading) . '</h1><p class="mt-2 text-slate-600">' . e($subtitle) . '</p><div class="mt-8">';
}

function auth_shell_close(): string
{
    return '</div></div></section>';
}

/** Why the visitor was sent to sign in. */
function notice_for(?string $next): ?string
{
    if (!$next) return null;
    $path = substr($next, strlen(base_path()));
    if (str_starts_with($path, '/book.php')) return 'Sign in or create a free account to complete your booking.';
    if (str_starts_with($path, '/account') || str_starts_with($path, '/trip')) return 'Please sign in to view your account.';
    return null;
}

function trip_card(array $b): string
{
    $icons = ['flight' => 'plane', 'package' => 'package', 'hotel' => 'bed'];
    $q = $b['quote'];
    [$cls, $label] = match ($b['status']) {
        'paid' => ['text-emerald-700', 'Paid · issuing tickets'],
        'ticketed' => ['text-emerald-700', 'Confirmed'],
        'cancelled' => ['text-slate-500', 'Cancelled'],
        default => ['text-amber-700', 'Reserved · unpaid'],
    };
    return '<a href="' . e(url('trip.php', ['ref' => $b['reference']])) . '" class="flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 hover:shadow-md' . ($b['status'] === 'cancelled' ? ' opacity-60' : '') . '">'
        . '<span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">' . icon($icons[$b['kind']], 22) . '</span>'
        . '<div class="min-w-0 flex-1"><p class="truncate font-semibold text-slate-900">' . e($q['title']) . '</p>'
        . '<p class="truncate text-sm text-slate-500">' . e(fmt_date($q['start_date'])) . ($q['end_date'] ? ' – ' . e(fmt_date($q['end_date'])) : '') . ' · ' . e($b['reference']) . '</p></div>'
        . '<div class="text-right"><p class="font-bold text-slate-900">' . money($b['total']) . '</p><p class="text-xs font-semibold ' . $cls . '">' . $label . '</p></div></a>';
}

// ---------- Admin ----------

function admin_open(string $active): string
{
    $links = [['admin/', 'Overview', 'overview'], ['admin/bookings.php', 'Bookings', 'bookings'], ['admin/users.php', 'Users', 'users'], ['admin/packages.php', 'Packages', 'packages'], ['admin/settings.php', 'Site settings', 'settings'], ['admin/diagnostics.php', 'Diagnostics', 'diagnostics']];
    $nav = implode('', array_map(fn($l) => '<a href="' . e(url($l[0])) . '" class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold transition '
        . ($l[2] === $active ? 'bg-white text-brand-800 shadow-sm' : 'text-brand-100 hover:bg-white/10 hover:text-white') . '">' . $l[1] . '</a>', $links));
    return '<div class="min-h-full bg-slate-50"><div class="bg-brand-900"><div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">'
        . '<p class="font-semibold text-white">Admin <span class="font-normal text-brand-200">· travel assistant dashboard</span></p><nav class="flex gap-1 overflow-x-auto">' . $nav . '</nav></div></div>'
        . '<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">';
}

function admin_close(): string
{
    return '</div></div>';
}

function bookings_table(array $bookings, string $empty = 'No bookings found.'): string
{
    if (!$bookings) return '<p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">' . e($empty) . '</p>';
    $kinds = ['flight' => 'Flight', 'hotel' => 'Hotel', 'package' => 'Package'];
    $rows = '';
    foreach ($bookings as $b) {
        $q = $b['quote'];
        $rows .= '<tr class="hover:bg-brand-50/50">'
            . '<td class="px-4 py-3"><a href="' . e(url('admin/booking.php', ['ref' => $b['reference']])) . '" class="font-mono font-semibold text-brand-700 hover:underline">' . e($b['reference']) . '</a></td>'
            . '<td class="px-4 py-3"><p class="font-medium text-slate-900">' . e($b['customer_name']) . '</p><p class="text-xs text-slate-500">' . e($b['contact_phone']) . '</p></td>'
            . '<td class="max-w-64 px-4 py-3"><p class="truncate font-medium text-slate-900">' . e($q['title']) . '</p><p class="text-xs text-slate-500">' . $kinds[$b['kind']] . ' · ' . plural(count($b['travelers']), 'traveler') . '</p></td>'
            . '<td class="whitespace-nowrap px-4 py-3 text-slate-700">' . e(fmt_date($q['start_date'])) . ($q['end_date'] ? ' – ' . e(fmt_date($q['end_date'])) : '') . '</td>'
            . '<td class="px-4 py-3 text-right font-semibold text-slate-900">' . money($b['total']) . '</td>'
            . '<td class="px-4 py-3">' . status_badge($b['status']) . '</td>'
            . '<td class="whitespace-nowrap px-4 py-3 text-slate-500">' . local_time($b['created_at'], true) . '</td></tr>';
    }
    $th = fn($t, $r = false) => '<th class="px-4 py-3 font-semibold' . ($r ? ' text-right' : '') . '">' . $t . '</th>';
    return '<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full min-w-[820px] text-left text-sm">'
        . '<thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr>'
        . $th('Reference') . $th('Customer') . $th('Trip') . $th('Travel dates') . $th('Total', true) . $th('Status') . $th('Booked')
        . '</tr></thead><tbody class="divide-y divide-slate-100">' . $rows . '</tbody></table></div>';
}

function pager(int $page, bool $hasMore, callable $href): string
{
    if ($page <= 1 && !$hasMore) return '';
    return '<div class="flex justify-between text-sm font-semibold">'
        . ($page > 1 ? '<a href="' . e($href($page - 1)) . '" class="text-brand-700 hover:underline">← Newer</a>' : '<span></span>')
        . '<span class="text-slate-500">Page ' . $page . '</span>'
        . ($hasMore ? '<a href="' . e($href($page + 1)) . '" class="text-brand-700 hover:underline">Older →</a>' : '<span></span>') . '</div>';
}

/**
 * Admin-only: supplier price, markup and what we keep after the member discount.
 * Only call when is_admin() — this must never be rendered for customers.
 */
function admin_cost_line(?array $cost, int $sellTotal): string
{
    if (!$cost || !is_admin()) return '';
    $discount = (int) round($sellTotal * member_discount_rate());
    $keep = $sellTotal - $discount - $cost['net_usd'];
    $net = $cost['net_currency'] === 'USD'
        ? '$' . number_format($cost['net_amount'], 2)
        : number_format($cost['net_amount'], 2) . ' ' . e($cost['net_currency']) . ' (≈$' . number_format($cost['net_usd'], 2) . ')';
    return '<div class="rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100" title="Only admins can see this">'
        . '<span class="font-semibold text-amber-300">Admin</span> · Supplier price <strong>' . $net . '</strong>'
        . ' · +' . round($cost['markup_rate'] * 100) . '% = ' . money($sellTotal)
        . ($discount ? ' · after ' . round(member_discount_rate() * 100) . '% member discount you keep <strong class="' . ($keep >= 0 ? 'text-emerald-300' : 'text-red-300') . '">$' . number_format($keep, 2) . '</strong>'
                     : ' · you keep <strong class="text-emerald-300">$' . number_format($keep, 2) . '</strong>')
        . ' <span class="text-slate-400">(before card &amp; supplier fees)</span></div>';
}
