<?php
/*
 * The trip page before payment, laid out like a travel agent's online authorization form:
 * "Review details and confirm your trip", flight and baggage details, passengers and contact (editable),
 * things to know, Travel Care Protection, payment and the agreement, with a price summary on the side.
 */
declare(strict_types=1);
defined('TC_APP') || exit;

/** A white card with a heading, used for each part of the page. */
function checkout_card(string $id, string $icon, string $title, string $body, string $action = ''): string
{
    return '<section id="' . e($id) . '" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white">'
        . '<div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4"><h2 class="flex items-center gap-2.5 text-lg font-semibold text-slate-900">'
        . '<span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600">' . icon($icon, 18) . '</span>' . e($title) . '</h2>' . $action . '</div>'
        . '<div class="p-6">' . $body . '</div></section>';
}

/** "Review details and confirm your trip", the progress steps and the travel assistant who looks after the booking. */
function checkout_banner(): string
{
    $steps = [t('Search'), t('Choose'), t('Traveler details'), t('Review & pay')];
    $html = '<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 p-6 text-white sm:p-8">'
        . icon('plane', 220, 'pointer-events-none absolute -right-8 -top-10 rotate-12 text-white/5', 1)
        . '<div class="relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center"><div>'
        . '<h2 class="text-2xl font-extrabold sm:text-3xl">' . e(t('Review details and confirm your trip')) . '</h2>'
        . '<p class="mt-1 text-brand-100">' . e(t("You're on the final step. Only a few more minutes to finish!")) . '</p>'
        . '<ol class="mt-6 grid grid-cols-4 gap-2 text-center text-xs font-medium sm:max-w-xl">';
    foreach ($steps as $i => $label) {
        $done = $i < 3;
        $html .= '<li class="relative flex flex-col items-center gap-1.5">'
            . ($i > 0 ? '<span class="absolute right-1/2 top-4 z-0 h-0.5 w-full ' . ($done ? 'bg-accent-400' : 'bg-white/30') . '"></span>' : '')
            . '<span class="relative grid h-8 w-8 place-items-center rounded-full text-sm font-bold ' . ($done ? 'bg-accent-500 text-white' : 'bg-white text-brand-800 ring-4 ring-accent-400/60') . '">' . ($done ? icon('check', 16, '', 2.5) : $i + 1) . '</span>'
            . '<span class="' . ($done ? 'text-brand-100' : 'font-bold text-white') . '">' . e($label) . '</span></li>';
    }
    $html .= '</ol></div>';
    $name = trim((string) config('chat_name'));
    $c = support_contacts();
    if ($name !== '' || $c) {
        $html .= '<div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur lg:min-w-64">'
            . '<p class="text-xs font-semibold uppercase tracking-wide text-accent-400">' . e(t('Your travel assistant')) . '</p>'
            . '<div class="mt-2 flex items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-accent-500 text-lg font-bold text-white">' . e(mb_strtoupper(mb_substr($name ?: (string) config('site_name'), 0, 1))) . '</span>'
            . '<div class="min-w-0 text-sm"><p class="font-semibold">' . e($name ?: (string) config('site_name')) . '</p>'
            . (isset($c['email']) ? '<a href="mailto:' . e($c['email']) . '" class="block truncate text-brand-100 hover:text-white">' . e($c['email']) . '</a>' : '')
            . (isset($c['whatsapp']) ? '<a href="https://wa.me/' . e(preg_replace('/\D/', '', $c['whatsapp'])) . '" target="_blank" rel="noopener" class="block text-brand-100 hover:text-white">WhatsApp ' . e($c['whatsapp']) . '</a>' : '')
            . (isset($c['phone']) ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $c['phone'])) . '" class="block text-brand-100 hover:text-white">' . e($c['phone']) . '</a>' : '')
            . '</div></div></div>';
    }
    return $html . '</div></section>';
}

/** Flight details and fare rules (flights), or the stay / package details. */
function checkout_trip(array $b): string
{
    $q = $b['quote'];
    if (!empty($q['flight'])) {
        $body = '<p class="mb-4 text-sm text-slate-500">' . e(quote_text($q['subtitle'])) . '</p><div class="space-y-5">' . leg_row($q['flight']['outbound'], 'Depart')
            . ($q['flight']['inbound'] ? '<div class="border-t border-dashed border-slate-200 pt-5">' . leg_row($q['flight']['inbound'], 'Return') . '</div>' : '') . '</div>';
        $pol = $q['policy'] ?? null;
        if ($pol) {
            // i18n-keys: 'Refundable with penalty', 'Changes allowed with penalty', 'Change fee', 'Change fee (before departure)', 'Change fee (after departure)', 'Cancellation fee', 'Cancellation fee (before departure)', 'Cancellation fee (after departure)', 'Changes allowed', 'Changes not allowed', 'Non-refundable', 'Refundable'
            $body .= '<details class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><summary class="cursor-pointer font-semibold text-brand-700">' . e(t('Fare rules')) . '</summary>';
            if ($pol['notes']) $body .= '<ul class="mt-3 flex flex-wrap gap-1.5">' . implode('', array_map(fn($n) => '<li class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">' . e(t($n)) . '</li>', $pol['notes'])) . '</ul>';
            if ($pol['fees']) $body .= '<dl class="mt-3 space-y-2">' . implode('', array_map(fn($f) => summary_row(e(t($f['label'])), e(t('from {price}', ['price' => price($f['from'])]))), $pol['fees'])) . '</dl>'
                . '<p class="mt-2 text-xs text-slate-500">' . e(t("Airline fees per traveler, plus any fare difference. We'll confirm the exact amount before making a change.")) . '</p>';
            $body .= '</details>';
        }
        return checkout_card('itinerary', 'plane', t('Flight details'), $body);
    }
    $rows = '';
    foreach ($q['facts'] as [$k, $v]) $rows .= summary_row(e(t($k)), e(quote_text($v, $q['start_date'] ?? null)));
    return checkout_card('itinerary', $b['kind'] === 'hotel' ? 'bed' : 'package', $b['kind'] === 'hotel' ? t('Your stay') : t('Your package'),
        '<p class="font-semibold text-slate-900">' . e($q['title']) . '</p><p class="mt-0.5 text-sm text-slate-500">' . e(quote_text($q['subtitle'])) . '</p><dl class="mt-4 space-y-2.5 text-sm">' . $rows . '</dl>');
}

/** Baggage allowance on the way out and back, checked bags requested, and "Add checked baggage" before paying. */
function checkout_baggage(array $b, bool $canEdit): string
{
    $q = $b['quote'];
    $bag = $q['baggage'] ?? null;
    $leg = $q['flight']['outbound'] ?? null;
    $city = fn(?string $code) => $code ? (airport($code)['city'] ?? $code) : '';
    $dirs = $leg ? [[t('Departure'), $city($leg['from']) . ' → ' . $city($leg['to'])]] : [[t('Your trip'), '']];
    if (!empty($q['flight']['inbound'])) $dirs[] = [t('Return'), $city($q['flight']['inbound']['from']) . ' → ' . $city($q['flight']['inbound']['to'])];
    $line = fn(string $label, string $state) => '<div class="flex items-center justify-between gap-3 py-2"><span class="flex items-center gap-2 text-slate-700">' . icon('luggage', 16, 'text-slate-400') . e($label) . '</span>' . $state . '</div>';
    $yes = '<span class="font-semibold text-emerald-700">✓ ' . e(t('Included')) . '</span>';
    $checked = !empty($bag['checked']) ? $yes
        : '<span class="text-right text-slate-600">' . e(t('Not included')) . (isset($bag['checked_from']) ? '<span class="block text-xs text-slate-500">' . e(t('from {price}', ['price' => price((int) ceil($bag['checked_from']))])) . '</span>' : '') . '</span>';
    $cols = '';
    foreach ($dirs as [$title, $route]) {
        $cols .= '<div class="rounded-xl border border-slate-200 p-4"><p class="font-semibold text-slate-900">' . e($title) . '</p>' . ($route !== '' ? '<p class="text-xs text-slate-500">' . e($route) . '</p>' : '')
            . '<div class="mt-2 divide-y divide-slate-100 text-sm">'
            . $line(t('Carry-on bag'), $bag ? (!empty($bag['carry_on']) ? $yes : '<span class="text-slate-600">' . e(t('Not included')) . '</span>') : '<span class="text-slate-500">' . e(t('Not confirmed yet')) . '</span>')
            . $line(t('Checked bag'), $bag ? $checked : '<span class="text-slate-500">' . e(t('Not confirmed yet')) . '</span>') . '</div></div>';
    }
    $html = '<p class="mb-4 text-sm text-slate-500">' . e($bag ? t('What the airline includes in this fare, for each traveler.') : t("Your travel assistant will confirm what this fare includes. Need a checked bag? Add it below and we'll confirm the airline's price.")) . '</p>'
        . '<div class="grid gap-4 sm:grid-cols-2">' . $cols . '</div>';
    $requested = array_keys(array_filter($b['travelers'], fn($t) => !empty($t['extra_bag'])));
    if ($requested) {
        $who = implode(', ', array_map(fn($i) => trim("{$b['travelers'][$i]['first']} {$b['travelers'][$i]['last']}"), $requested));
        $state = match ($b['bag_status'] ?? null) {
            'added' => t('Added to your total'),
            'included' => t('Already included in your fare'),
            'declined' => t("The airline couldn't add it"),
            default => t("We're confirming the airline's price"),
        };
        $html .= '<p class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900 ring-1 ring-brand-100"><strong>' . e(t('Checked bag requested')) . ':</strong> ' . e($who) . ' · ' . e($state) . '</p>';
    }
    $eligible = array_filter(array_keys($b['travelers']), fn($i) => empty($b['travelers'][$i]['extra_bag']) && !str_starts_with($q['slots'][$i]['label'] ?? '', 'Infant'));
    if ($canEdit && empty($bag['checked']) && ($b['bag_status'] ?? null) === null && $eligible) {
        $html .= '<details class="mt-4 rounded-xl border border-brand-200 bg-white"><summary class="flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white hover:bg-brand-700">＋ ' . e(t('Add checked baggage')) . '</summary>'
            . '<form method="post" class="space-y-3 p-4">' . csrf_field() . '<input type="hidden" name="action" value="add_bag">'
            . '<p class="text-sm font-medium text-slate-700">' . e(t('Who needs a checked bag?')) . '</p>';
        foreach ($eligible as $i) {
            $t = $b['travelers'][$i];
            $html .= '<label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 text-sm hover:bg-slate-50"><input type="checkbox" name="bag[]" value="' . $i . '" class="h-4 w-4 accent-brand-600"> <span class="font-medium text-slate-900">' . e(trim("{$t['first']} {$t['last']}")) . '</span> <span class="text-slate-500">' . e(slot_label($q['slots'][$i]['label'] ?? '')) . '</span></label>';
        }
        $html .= submit_button(t('Request checked bags'), t('Saving…'), 'w-full sm:w-auto sm:px-6') . '</form></details>';
    }
    $html .= '<p class="mt-4 text-xs leading-relaxed text-slate-500"><strong class="font-semibold text-slate-600">' . e(t('Disclaimer:')) . '</strong> '
        . e(t("Extra bags are requested from the airline and aren't guaranteed. We confirm the airline's price and add it to your total before you pay. Bag fees are set by the airline and follow its rules.")) . '</p>';
    return checkout_card('baggage', 'luggage', t('Baggage'), $html);
}

/** Stored traveler details as the Edit form's values. */
function traveler_form_values(array $travelers): array
{
    $v = [];
    foreach ($travelers as $i => $t) {
        $v += ["t{$i}_first" => $t['first'], "t{$i}_last" => $t['last'], "t{$i}_nolast" => !empty($t['no_last_name']) ? '1' : '', "t{$i}_gender" => $t['gender'] ?? '',
            "t{$i}_dob" => $t['dob'] ?? '', "t{$i}_nationality" => $t['nationality'] ?? '', "t{$i}_ff" => $t['frequent_flyer'] ?? '', "t{$i}_meal" => $t['meal'] ?? '', "t{$i}_redress" => $t['redress'] ?? ''];
    }
    return $v;
}

/** Passenger details, with Edit before payment. $form: [values, errors] while editing. */
function checkout_travelers(array $b, bool $canEdit, ?array $form, string $editUrl, string $backUrl): string
{
    $q = $b['quote'];
    $air = $b['kind'] !== 'hotel';
    $title = $air ? t('Passenger details') : t('Guest details');
    $note = '<p class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 ring-1 ring-red-100">' . e($air ? t('Traveler names must match the government-issued ID each traveler will use for this trip.') : t("The guest's name must match their ID at check-in.")) . '</p>';
    if ($form) {
        [$values, $errors] = $form;
        $v = fn(string $k) => (string) ($values[$k] ?? '');
        $fields = '';
        foreach ($q['slots'] as $i => $slot) $fields .= traveler_fieldset($i, $slot, $v, $errors, $air, $b['kind']);
        return checkout_card('travelers', 'user', $title, $note . '<form method="post" novalidate data-pending-form>' . csrf_field() . '<input type="hidden" name="action" value="travelers">'
            . ($errors ? '<div class="mb-4">' . alert_box(t('Please fix the highlighted fields.')) . '</div>' : '')
            . '<div class="space-y-6">' . $fields . '</div><div class="mt-6 flex flex-wrap items-center gap-3">' . submit_button(t('Save changes'), t('Saving…'), 'px-6')
            . '<a href="' . e($backUrl) . '" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">' . e(t('Cancel')) . '</a></div></form>');
    }
    $cards = '';
    foreach ($b['travelers'] as $i => $t) {
        $meta = array_filter([
            isset($t['gender']) ? ($t['gender'] === 'F' ? t('Female') : t('Male')) : null,
            !empty($t['nationality']) ? country_name($t['nationality']) : null,
            !empty($t['frequent_flyer']) ? t('Frequent flyer {number}', ['number' => $t['frequent_flyer']]) : null,
            !empty($t['meal']) && isset(MEAL_PREFERENCES[$t['meal']]) ? t(MEAL_PREFERENCES[$t['meal']]) : null,
            !empty($t['redress']) ? t('Redress number') . ' ' . $t['redress'] : null,
            !empty($t['extra_bag']) ? t('Checked bag requested') : null,
        ]);
        $cards .= '<div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100"><p class="text-xs font-semibold uppercase tracking-wide text-brand-700">' . e(slot_label($q['slots'][$i]['label'] ?? '')) . '</p>'
            . '<dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">'
            . '<div><dt class="text-xs text-slate-500">' . e(t('Last name')) . '</dt><dd class="font-bold uppercase text-slate-900">' . e($t['last'] !== '' ? $t['last'] : '—') . '</dd></div>'
            . '<div><dt class="text-xs text-slate-500">' . e(t('First name')) . '</dt><dd class="font-bold uppercase text-slate-900">' . e($t['first']) . '</dd></div>'
            . (!empty($t['dob']) ? '<div><dt class="text-xs text-slate-500">' . e(t('Date of birth')) . '</dt><dd class="font-semibold text-slate-900">' . e(fmt_dob($t['dob'])) . '</dd></div>' : '')
            . '</dl>' . ($meta ? '<p class="mt-2 text-xs text-slate-600">' . e(implode(' · ', $meta)) . '</p>' : '') . '</div>';
    }
    $edit = $canEdit ? '<a href="' . e($editUrl) . '" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">' . e(t('Edit')) . '</a>' : '';
    return checkout_card('travelers', 'user', $title, $note . '<div class="space-y-3">' . $cards . '</div>', $edit);
}

/** The person we contact about the trip (schedule changes), with Edit before payment. */
function checkout_contact(array $b, bool $canEdit, ?array $form, string $editUrl, string $backUrl): string
{
    $intro = '<p class="mb-4 text-sm text-slate-500">' . e(t("The person we'll contact if anything changes, for example the flight schedule.")) . '</p>';
    if ($form) {
        [$values, $errors] = $form;
        return checkout_card('contact', 'headset', t('Contact person'), $intro . '<form method="post" novalidate data-pending-form>' . csrf_field() . '<input type="hidden" name="action" value="contact">'
            . contact_fields(fn(string $k) => (string) ($values[$k] ?? ''), $errors) . '<div class="mt-6 flex flex-wrap items-center gap-3">' . submit_button(t('Save changes'), t('Saving…'), 'px-6')
            . '<a href="' . e($backUrl) . '" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">' . e(t('Cancel')) . '</a></div></form>');
    }
    $rows = (!empty($b['contact_name']) ? summary_row(e(t('Name')), e($b['contact_name'])) : '') . summary_row(e(t('Email')), e($b['contact_email'])) . summary_row(e(t('Phone')), e($b['contact_phone']));
    $edit = $canEdit ? '<a href="' . e($editUrl) . '" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">' . e(t('Edit')) . '</a>' : '';
    return checkout_card('contact', 'headset', t('Contact person'), $intro . '<dl class="space-y-2.5 text-sm">' . $rows . '</dl>', $edit);
}

/** Passports & visas, payment limits and the fare rules in short (from the "Before you pay" steps). */
function checkout_good_to_know(array $b): string
{
    $items = '';
    foreach (checklist_steps($b) as [$key, $ic, $title, $body]) {
        if (!in_array($key, ['documents', 'limit', 'important'], true)) continue;
        $items .= '<li class="flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-500/15 text-accent-700">' . icon($ic, 18) . '</span>'
            . '<div class="min-w-0"><h3 class="font-semibold text-slate-900">' . e($title) . '</h3><div class="mt-0.5 text-sm leading-relaxed text-slate-600">' . $body . '</div></div></li>';
    }
    return checkout_card('good-to-know', 'alert', t('Before you pay'), '<ul class="space-y-5">' . $items . '</ul>');
}

/** The optional Travel Care Protection add-on, with its own Add / Added button (inside the payment form). */
function checkout_care(array $b): string
{
    $fee = change_service_fee();
    $price = travel_care_price($b);
    $on = !empty($b['quote']['care']);
    $perks = array_values(array_filter([
        $fee > 0 ? [t('No service fee from us'), t('No {site} service fee when you change dates or cancel (normally {fee} each time).', ['site' => config('site_name'), 'fee' => money($fee)])] : null,
        [t('Priority help'), t('Priority help: your change and cancellation requests are handled first by our travel assistants.')],
        [t('Airline rules still apply'), t("You still pay the airline's own penalty and any fare difference.")],
    ]));
    $rows = implode('', array_map(fn($p) => '<li class="flex gap-3">' . icon('check', 18, 'mt-0.5 shrink-0 text-emerald-600', 2.5) . '<div><p class="font-semibold text-slate-900">' . e($p[0]) . '</p><p class="text-sm text-slate-600">' . e($p[1]) . '</p></div></li>', $perks));
    $terms = '<a href="' . e(url('terms.php')) . '#travel-care" target="_blank" class="font-semibold text-brand-700 underline underline-offset-2">' . e(t('terms')) . '</a>';
    return '<section id="travel-care" class="scroll-mt-24 overflow-hidden rounded-2xl border-2 border-accent-400/70 bg-white">'
        . '<div class="relative overflow-hidden bg-gradient-to-r from-brand-950 to-brand-700 px-6 py-5 text-white">' . icon('shield-check', 120, 'pointer-events-none absolute -right-4 -top-4 text-white/10', 1.2)
        . '<p class="relative inline-block rounded-full bg-accent-500 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide">' . e(t('Recommended')) . '</p>'
        . '<h2 class="relative mt-2 flex items-center gap-2 text-xl font-bold">' . icon('shield-check', 22, 'text-accent-400') . e(t('Travel Care Protection')) . '</h2>'
        . '<p class="relative mt-1 text-sm text-brand-100">' . e(t('Extra flexibility if your plans change, for a small fee.')) . '</p></div>'
        . '<div class="grid gap-6 p-6 sm:grid-cols-[1fr_200px] sm:items-center"><ul class="space-y-4">' . $rows . '</ul>'
        . '<div class="rounded-2xl bg-accent-500/5 p-4 text-center ring-1 ring-accent-400/50"><p class="text-sm font-semibold text-slate-600">' . e(t('Travel Care')) . '</p>'
        . '<p class="mt-1 text-3xl font-extrabold text-slate-900">' . money($price) . '</p><p class="text-xs text-slate-500">' . e(t('{rate}% of your ticket price', ['rate' => round(travel_care_rate() * 100)])) . '</p>'
        . '<label class="mt-3 flex cursor-pointer select-none items-center justify-center gap-2 rounded-xl border-2 border-accent-500 px-4 py-2.5 font-bold text-accent-700 transition hover:bg-accent-500/10 has-[:checked]:bg-accent-500 has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent-400">'
        . '<input type="checkbox" id="care-toggle" name="care" value="1"' . ($on ? ' checked' : '') . ' class="peer sr-only">'
        . '<span class="peer-checked:hidden">＋ ' . e(t('Add')) . '</span><span class="hidden peer-checked:inline">✓ ' . e(t('Added')) . '</span></label>'
        . '<a href="' . e(url('terms.php')) . '#travel-care" target="_blank" class="mt-2 block text-xs font-semibold text-brand-700 underline underline-offset-2">' . e(t('Terms & conditions')) . '</a></div></div>'
        . '<div class="border-t border-slate-100 bg-slate-50 px-6 py-4 text-xs leading-relaxed text-slate-500">'
        . th('{rate}% of your ticket price. Travel Care is a {site} service, not insurance, and is non-refundable once you pay. See our {terms}.', ['rate' => round(travel_care_rate() * 100)], ['site' => e((string) config('site_name')), 'terms' => $terms])
        . '<p class="mt-1 font-semibold text-slate-700">' . e(t("Don't miss the chance: Travel Care can't be added after you pay.")) . '</p></div></section>';
}

/** Payment methods, the agreement and "Pay securely now" (the rest of the payment form). */
function checkout_payment(array $b, bool $canPay, bool $canGcash, string $method): string
{
    $provider = payment_provider();
    $terms = fn(string $page, string $label, string $hash = '') => '<a href="' . e(url($page)) . $hash . '" target="_blank" class="font-semibold text-brand-700 underline underline-offset-2">' . e($label) . '</a>';
    $html = '';
    if ($canPay && $canGcash) {
        $card = fn(string $id, string $value, bool $checked, string $title, string $sub, string $tone) => '<label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 p-4 hover:border-slate-300 '
            . ($tone === 'sky' ? 'has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50/50' : 'has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50') . '">'
            . '<input type="radio" name="method" id="' . $id . '" value="' . $value . '"' . ($checked ? ' checked' : '') . ' class="h-4 w-4 ' . ($tone === 'sky' ? 'accent-sky-600' : 'accent-brand-600') . '">'
            . '<span><span class="block font-semibold text-slate-900">' . e($title) . '</span><span class="block text-xs text-slate-500">' . e($sub) . '</span></span></label>';
        $html .= '<fieldset><legend class="mb-2 text-sm font-medium text-slate-700">' . e(t('Choose how to pay')) . '</legend><div class="grid gap-3 sm:grid-cols-2">'
            . $card('pm-paypal', 'paypal', $method !== 'gcash', $provider === 'paypal' ? t('PayPal or card') : t('Card'), t('Pay in US dollars · confirmed instantly'), 'brand')
            . $card('pm-gcash', 'gcash', $method === 'gcash', 'GCash', t('Pay in pesos · confirmed within a few hours'), 'sky') . '</div></fieldset>';
    } else {
        $html .= '<input type="hidden" name="method" value="' . ($canPay ? 'paypal' : 'gcash') . '"><p class="text-sm text-slate-600">'
            . e($canPay ? ($provider === 'paypal' ? t("Pay securely with your PayPal account or any debit/credit card. You'll be taken to PayPal and brought back here afterwards.") : t("Pay securely by card. You'll be taken to our payment partner Stripe and brought back here afterwards.")) : t('Pay in pesos with GCash. We show you the amount and our GCash details next.'))
            . '</p>';
    }
    $html .= '<label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200 has-[:checked]:bg-emerald-50 has-[:checked]:ring-emerald-300">'
        . '<input type="checkbox" name="agree" value="1" required class="mt-0.5 h-5 w-5 shrink-0 accent-emerald-600"><span>'
        . th("By ticking this box I confirm that I have read and accept the {terms}, the {care} and the {privacy}, that I have checked the itinerary, and that every traveler's name matches their passport or travel document. I also confirm that I have the consent of the card holder and of every traveler.", [], [
            'terms' => $terms('terms.php', t('booking terms'), '#bookings'), 'care' => $terms('terms.php', t('Travel Care terms'), '#travel-care'), 'privacy' => $terms('privacy.php', t('Privacy policy'))])
        . '</span></label>'
        . '<div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3">' . '<button type="submit" data-pending="' . e(t('Please wait…')) . '" class="flex w-full items-center justify-center gap-2 rounded-xl bg-accent-500 px-8 py-3.5 text-lg font-bold text-white shadow-lg shadow-accent-500/30 transition hover:bg-accent-600 disabled:cursor-wait disabled:opacity-70 sm:w-auto">' . icon('shield-check', 20) . e(t('Pay securely now')) . '</button>'
        . '<p class="text-sm text-slate-600">' . e(t('Total')) . ': ' . checkout_total_html($b) . '</p></div>'
        . (current_currency() !== 'USD' ? '<p class="mt-2 text-xs text-slate-500">' . e(t('You will be charged in US dollars.')) . '</p>' : '');
    return '<section id="pay" class="scroll-mt-24 overflow-hidden rounded-2xl border-2 border-brand-200 bg-white">'
        . '<div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4"><h2 class="flex items-center gap-2.5 text-lg font-semibold text-slate-900"><span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600">' . icon('card', 18) . '</span>' . e(t('Payment information')) . '</h2>'
        . '<p class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">' . icon('shield-check', 16) . e(t('All transactions are secure and encrypted.')) . '</p></div>'
        . '<div class="p-6">' . $html . '</div></section>';
}

/** The total, with and without Travel Care; the page shows the right one as the Travel Care button is switched. */
function checkout_total_html(array $b, string $class = 'font-bold text-slate-900'): string
{
    $care = (int) ($b['quote']['care'] ?? 0);
    $base = $b['total'] - $care;
    if (!travel_care_available($b)) return '<strong class="' . $class . '">' . money($b['total']) . '</strong>';
    $with = $base + travel_care_price($b);
    return '<strong class="' . $class . '" data-care-off' . ($care ? ' hidden' : '') . '>' . money($base) . '</strong>'
        . '<strong class="' . $class . '" data-care-on' . ($care ? '' : ' hidden') . '>' . money($with) . '</strong>';
}

/** "(one thousand two hundred US dollars)", in the visitor's language when the server can spell numbers. */
function amount_in_words(int $usd): ?string
{
    if (!class_exists('NumberFormatter')) return null;
    $words = (new NumberFormatter(str_replace('-', '_', html_lang()), NumberFormatter::SPELLOUT))->format($usd);
    return $words === false ? null : t('({words} US dollars)', ['words' => $words]);
}

/** Price summary on the side: what's booked, the booking reference, price lines and the total (also in words). */
function checkout_summary(array $b): string
{
    $q = $b['quote'];
    $kinds = ['flight' => ['plane', t('Flight')], 'package' => ['package', t('Flight + Hotel package')], 'hotel' => ['bed', t('Hotel')]];
    [$ic, $label] = $kinds[$b['kind']];
    $care = (int) ($q['care'] ?? 0);
    $offer = travel_care_available($b);
    $carePrice = $offer ? travel_care_price($b) : $care;
    $html = '<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">'
        . '<div class="bg-gradient-to-br from-brand-800 to-brand-600 p-5 text-white"><p class="flex items-center gap-2 text-sm font-medium text-brand-100">' . icon($ic, 16) . ' ' . e($label) . '</p>'
        . '<h2 class="mt-1 text-xl font-bold">' . e($q['title']) . '</h2><p class="mt-1 text-sm text-brand-100">' . e(quote_text($q['subtitle'])) . '</p></div>'
        . '<dl class="space-y-2.5 border-b border-slate-100 p-5 text-sm">';
    if (!empty($q['flight'])) {
        $city = fn(string $code) => airport($code)['city'] ?? $code;
        foreach (['outbound' => 'Depart', 'inbound' => 'Return'] as $k => $l) {
            if (empty($q['flight'][$k])) continue;
            $leg = $q['flight'][$k];
            $html .= summary_row(e(t($l)), e($city($leg['from']) . ' (' . $leg['from'] . ') → ' . $city($leg['to']) . ' (' . $leg['to'] . ')') . '<span class="block text-xs font-normal text-slate-500">' . e(fmt_date($leg['date'])) . '</span>');
        }
    } else {
        $html .= summary_row(e(t('Dates')), e(fmt_date($q['start_date']) . ($q['end_date'] ? ' – ' . fmt_date($q['end_date']) : '')));
    }
    $html .= summary_row(e(t('Travelers')), (string) count($b['travelers']))
        . '</dl><div class="px-5 pt-4"><p class="rounded-lg border border-dashed border-brand-300 bg-brand-50 py-2 text-center text-sm font-semibold text-brand-800">' . e(t('Booking reference')) . ': <span class="font-mono">' . e($b['reference']) . '</span></p></div>'
        . '<dl class="space-y-2 p-5 text-sm">';
    $line = fn(array $l, string $attr = '') => '<div class="flex justify-between"' . $attr . '><dt class="text-slate-600">' . e(quote_text($l['label'])) . '</dt><dd class="text-slate-900">' . money($l['amount']) . '</dd></div>';
    $isExtra = fn(array $l) => (bool) preg_match('/^(Checked bag × \d+|' . TRAVEL_CARE_LABEL . ')$/', $l['label']);
    foreach ($q['lines'] as $l) if (!$isExtra($l)) $html .= $line($l);
    if ($q['discount'] > 0) {
        $html .= '<div class="flex justify-between text-emerald-700"><dt>' . e(t('Member discount ({pct}%)', ['pct' => round($q['discount_rate'] * 100)])) . '</dt><dd>−' . money($q['discount']) . '</dd></div>';
    }
    foreach ($q['lines'] as $l) if ($isExtra($l) && $l['label'] !== TRAVEL_CARE_LABEL) $html .= $line($l);
    if ($offer || $care) $html .= $line(['label' => TRAVEL_CARE_LABEL, 'amount' => $carePrice], ' data-care-on' . ($care ? '' : ' hidden'));
    $base = $b['total'] - $care;
    $words = fn(int $usd, string $attr) => ($w = amount_in_words($usd)) ? '<p class="text-right text-xs text-slate-500"' . $attr . '>' . e($w) . '</p>' : '';
    $html .= '<div class="flex items-end justify-between border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-900">' . e(t('Total')) . '</dt><dd class="text-right">'
        . checkout_total_html($b, 'text-2xl font-extrabold text-slate-900') . '</dd></div>'
        . ($offer ? $words($base, ' data-care-off' . ($care ? ' hidden' : '')) . $words($base + travel_care_price($b), ' data-care-on' . ($care ? '' : ' hidden')) : $words($b['total'], ''))
        . (($hint = price_hint($b['total'])) !== '' && !$offer ? '<p class="text-right text-xs text-slate-500">' . e($hint) . '</p>' : '')
        . '<p class="text-right text-xs text-slate-500">' . e(t('Taxes and fees included')) . '</p>';
    if (!empty($q['at_hotel'])) {
        $html .= '<div class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 ring-1 ring-amber-200"><p class="font-semibold">' . e(t('Due at the hotel')) . '</p>'
            . implode('', array_map(fn($f) => '<p class="flex justify-between"><span>' . e(quote_text($f['label'])) . '</span><span>' . money($f['amount']) . '</span></p>', $q['at_hotel']))
            . '<p class="mt-1 text-amber-800">' . e(t('Paid directly to the hotel at check-in. Not included in the total above.')) . '</p></div>';
    }
    return $html . '</dl></div>';
}
