<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * The trip email (reservation and payment link), laid out like a travel agency's booking confirmation: a big
 * destination or hotel photo, where the booking stands, the flights or stay, travelers, the price, what to check
 * before paying, Travel Care, and who to ask for help. Email apps only understand tables and inline styles, and
 * only JPG/PNG pictures, so that's all this uses. Always English, like every email.
 */

function trip_email(array $b, string $subject, string $kicker, string $heading, array $intro): array
{
    $site = (string) config('site_name');
    $q = $b['quote'];
    $first = $b['travelers'][0]['first'] ?? 'there';
    $canPay = payments_enabled() || gcash_enabled();
    $link = pay_link($b);
    $button = $canPay ? 'Review & pay securely' : 'Review my trip';
    $photo = trip_photo($b);
    $offerCare = travel_care_available($b) && empty($q['care']);
    $kind = $b['kind'];
    $dates = fmt_date($q['start_date']) . ($q['end_date'] ? ' – ' . fmt_date($q['end_date']) : '');

    $html = email_p(e("Dear $first,")) . implode('', array_map(fn($p) => email_p(e($p)), $intro))
        . email_progress($b)
        . email_pay_box($b, $link, $button, $canPay)
        . email_section($kind === 'hotel' ? 'bed' : ($kind === 'package' ? 'package' : 'plane'), $kind === 'hotel' ? 'Your stay' : ($kind === 'package' ? 'Your package' : 'Your flights'))
        . email_itinerary($b)
        . email_section('users', $kind === 'hotel' ? 'Guest' : 'Travelers') . email_travelers($b)
        . email_section('receipt', 'Price summary') . email_price($b)
        . email_section('clipboard', 'Before you pay') . email_tips($b)
        . ($offerCare ? email_care($b) : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 0 8px">' . email_cta($link, $button) . '</td></tr></table>'
        . email_help()
        . email_trust()
        . '<p style="margin:20px 0 0;font-size:12px;line-height:1.5;color:#94a3b8">If the button doesn\'t work, copy this link into your browser:<br><a href="' . e($link) . '" style="color:#64748b;word-break:break-all">' . e($link) . '</a></p>';

    $text = "Dear $first,\n\n" . implode("\n\n", $intro) . "\n\n" . rows_text(trip_rows($b)) . "\n\n$button: $link\n";
    foreach (checklist_steps($b, true) as [, , $title, $body]) {
        $text .= "\n$title\n" . html_entity_decode(strip_tags(str_replace('<br>', "\n", $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
    }
    $text .= "\nIf you have any questions, just reply to this email. We're always happy to help.\n\n" . email_signoff(true);

    $preheader = "Booking {$b['reference']} · {$q['title']} · $dates. " . ($canPay ? 'Review your details and pay securely.' : 'Review your trip details.');
    $reply = (string) config('support_email') ?: (string) config('smtp_user');
    return ['subject' => $subject, 'text' => $text,
        'html' => email_layout($heading, $html, $photo, ['kicker' => $kicker, 'sub' => "Booking reference {$b['reference']}", 'preheader' => $preheader])]
        + (valid_email($reply) ? ['reply_to' => $reply] : []);
}

/** One of the pictures made for emails (assets/email/icon-*.png), shown at $size pixels. */
function email_icon(string $name, int $size): string
{
    return '<img src="' . e(email_image("icon-$name.png")) . '" width="' . $size . '" height="' . $size . '" alt="" style="display:block;border:0;width:' . $size . 'px;height:' . $size . 'px">';
}

/** A section title with its gold icon. Icons: plane, bed, package, users, receipt, clipboard. */
function email_section(string $icon, string $title): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0 14px"><tr>'
        . '<td width="36" valign="middle">' . email_icon("sec-$icon", 36) . '</td>'
        . '<td valign="middle" style="padding-left:12px;font-size:19px;font-weight:bold;color:#0f1d4f">' . e($title) . '</td></tr></table>';
}

function email_cta(string $href, string $label): string
{
    return '<a href="' . e($href) . '" style="display:inline-block;padding:15px 26px;white-space:nowrap;background:#d4af37;color:#ffffff;border-radius:12px;text-decoration:none;font-weight:bold;font-size:16px;box-shadow:0 4px 12px rgba(212,175,55,0.35)">' . e($label) . ' &rarr;</a>';
}

/** Reserved → Review & pay → Ticket issued, as three bars with labels. */
function email_progress(array $b): string
{
    $last = ['flight' => 'Tickets issued', 'hotel' => 'Room booked', 'package' => 'Trip confirmed'][$b['kind']];
    $steps = [['&#10003; Reserved', '#059669', '#059669'], ['2 &nbsp;Review &amp; pay', '#d4af37', '#a8841f'], ['3 &nbsp;' . e($last), '#e2e8f0', '#94a3b8']];
    $bars = $labels = '';
    foreach ($steps as $i => [$label, $bar, $color]) {
        $gap = $i < 2 ? 'padding-right:6px' : '';
        $bars .= '<td width="33%" style="' . $gap . '"><div style="height:6px;line-height:6px;font-size:0;border-radius:3px;background:' . $bar . '">&nbsp;</div></td>';
        $labels .= '<td width="33%" valign="top" style="padding-top:8px;font-size:12px;font-weight:bold;color:' . $color . '">' . $label . '</td>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 4px"><tr>' . $bars . '</tr><tr>' . $labels . '</tr></table>';
}

/** The total, how to pay and the main button, in a soft gold box. */
function email_pay_box(array $b, string $link, string $button, bool $canPay): string
{
    $q = $b['quote'];
    $nights = (int) round((strtotime((string) $q['end_date']) - strtotime((string) $q['start_date'])) / 86400);
    $guests = (int) (array_column($q['facts'], 1, 0)['Guests'] ?? 0);
    $who = $b['kind'] === 'hotel'
        ? implode(' · ', array_filter([$nights > 0 ? plural($nights, 'night') : null, $guests > 0 ? plural($guests, 'guest') : null]))
        : plural(count($b['travelers']), 'traveler');
    $ways = array_values(array_filter([
        payments_enabled() ? (payment_provider() === 'paypal' ? 'PayPal or card' : 'card') : null,
        gcash_enabled() ? 'GCash' : null,
    ]));
    $note = $canPay ? 'Secure payment with ' . implode(' or ', $ways) . '. Nothing is charged until you pay.' : 'A travel assistant will contact you within 24 hours to confirm availability and arrange payment.';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 0;background:#fbf6e6;border:1px solid #ecdca6;border-radius:16px;border-collapse:separate"><tr><td style="padding:20px 22px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td class="ff-stack" valign="middle"><p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:0.06em;color:#a8841f;text-transform:uppercase">' . ($canPay ? 'Total to pay' : 'Trip total') . '</p>'
        . '<p style="margin:2px 0 0;font-size:32px;line-height:1.1;font-weight:bold;color:#0f1d4f">' . money($b['total']) . '</p>'
        . '<p style="margin:4px 0 0;font-size:13px;color:#64748b">Taxes and fees included' . ($who !== '' ? ' · ' . e($who) : '') . '</p></td>'
        . '<td class="ff-stack ff-pt" align="right" valign="middle">' . email_cta($link, $button) . '</td></tr></table>'
        . '<p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#475569">&#128274; ' . e($note) . '</p></td></tr></table>';
}

/** The flights (one card per direction), or the hotel stay, or the package. */
function email_itinerary(array $b): string
{
    $q = $b['quote'];
    $html = '';
    if (!empty($q['flight'])) {
        $airline = $q['flight']['airline'] ?? [];
        $html .= email_leg($q['flight']['outbound'], 'Depart', $airline);
        if (!empty($q['flight']['inbound'])) $html .= email_leg($q['flight']['inbound'], 'Return', $airline);
        $bag = $q['baggage'] ?? null;
        $facts = array_column($q['facts'], 1, 0);
        $lines = array_values(array_filter([
            $bag ? '<strong>Carry-on bag:</strong> ' . ($bag['carry_on'] ? 'included' : 'not included') . ' &nbsp;·&nbsp; <strong>Checked bag:</strong> '
                . (!empty($bag['checked']) ? 'included' : 'not included' . (isset($bag['checked_from']) ? ' (from ' . money((int) ceil($bag['checked_from'])) . ')' : '') . '. You can request one on your review page.') : null,
            isset($facts['Cabin']) || isset($facts['Fare']) ? '<strong>Fare:</strong> ' . e(implode(' · ', array_filter([$facts['Cabin'] ?? null, $facts['Fare'] ?? null]))) : null,
        ]));
        if ($lines) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 0"><tr><td width="32" valign="top" style="padding-top:2px">' . email_icon('tip-luggage', 28) . '</td>'
                . '<td style="padding-left:10px;font-size:13px;line-height:1.6;color:#475569">' . implode('<br>', $lines) . '</td></tr></table>';
        }
    }
    if ($b['kind'] === 'hotel') return email_stay($b);
    if ($b['kind'] === 'package') {
        $html .= email_facts_card($q['title'], quote_text($q['subtitle']), array_values(array_filter($q['facts'], fn($f) => !in_array($f[0], ['Depart', 'Return', 'Travelers'], true))));
    }
    return $html;
}

function email_leg(array $leg, string $label, array $airline): string
{
    $city = fn(string $code) => airport($code)['city'] ?? $code;
    // The airline's code in its colour (a supplier's logo address can break, and email apps show broken pictures).
    $badge = '<span style="display:inline-block;vertical-align:middle;padding:2px 5px;border-radius:4px;background:' . e((string) ($airline['color'] ?? '#1c54f0')) . ';color:#ffffff;font-size:10px;font-weight:bold">' . e((string) ($airline['code'] ?? '')) . '</span>';
    $stops = $leg['stops'] === 0 ? '<span style="color:#047857">Nonstop</span>' : '<span style="color:#a8841f">' . plural((int) $leg['stops'], 'stop') . ' · ' . e(implode(', ', $leg['stop_cities'])) . '</span>';
    $end = fn(string $time, string $code, string $align, string $sup = '') => '<td width="36%" valign="top" align="' . $align . '">'
        . '<p style="margin:0;font-size:22px;line-height:1.2;font-weight:bold;color:#0f172a">' . $time . $sup . '</p>'
        . '<p style="margin:2px 0 0;font-size:15px;font-weight:bold;color:#0f1d4f">' . e($code) . '</p>'
        . '<p style="margin:0;font-size:12px;color:#64748b">' . e($city($code)) . '</p></td>';
    $line = '<td style="border-top:2px dashed #cbd5e1;font-size:0;line-height:0">&nbsp;</td>';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid #e2e8f0;border-radius:14px;border-collapse:separate">'
        . '<tr><td style="padding:10px 16px;background:#f6f8fc;border-bottom:1px solid #e2e8f0;border-radius:14px 14px 0 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td class="ff-stack" style="font-size:12px;font-weight:bold;letter-spacing:0.06em;color:#0f1d4f;text-transform:uppercase">' . e($label) . ' · ' . e(fmt_date($leg['date'])) . '</td>'
        . '<td class="ff-stack ff-pt4" align="right" style="font-size:12px;color:#475569">' . $badge . ' &nbsp;' . e((string) ($airline['name'] ?? '')) . ' · ' . e($leg['flight_number']) . '</td></tr></table></td></tr>'
        . '<tr><td style="padding:16px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . $end(fmt_time($leg['depart']), $leg['from'], 'left')
        . '<td width="28%" align="center" valign="middle"><p style="margin:0 0 4px;font-size:12px;color:#64748b">' . fmt_duration($leg['duration']) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . $line . '<td width="22" align="center">' . email_icon('leg-plane', 20) . '</td>' . $line . '</tr></table>'
        . '<p style="margin:4px 0 0;font-size:12px;font-weight:bold">' . $stops . '</p></td>'
        . $end(fmt_time($leg['arrive']), $leg['to'], 'right', $leg['day_offset'] > 0 ? '<sup style="font-size:11px;color:#a8841f">+' . $leg['day_offset'] . '</sup>' : '')
        . '</tr></table></td></tr></table>';
}

/** The hotel: name, stars and area, then check-in / check-out and the room details. */
function email_stay(array $b): string
{
    $q = $b['quote'];
    $facts = array_column($q['facts'], 1, 0);
    $nights = (int) round((strtotime((string) $q['end_date']) - strtotime((string) $q['start_date'])) / 86400);
    $date = fn(string $label, string $value) => '<td width="50%" valign="top" style="padding:12px 14px;background:#f6f8fc;border-radius:12px">'
        . '<p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:0.06em;color:#64748b;text-transform:uppercase">' . e($label) . '</p>'
        . '<p style="margin:2px 0 0;font-size:18px;font-weight:bold;color:#0f1d4f">' . e($value) . '</p></td>';
    $rows = array_filter(['Room' => $facts['Room'] ?? null, 'Guests' => $facts['Guests'] ?? null, 'Nights' => $nights > 0 ? (string) $nights : null, 'Cancellation' => $facts['Cancellation'] ?? null]);
    $list = '';
    foreach ($rows as $k => $v) {
        $color = $k === 'Cancellation' ? ($v === 'Free cancellation' ? '#047857' : '#dc2626') : '#0f172a';
        $list .= '<tr><td style="padding:8px 0;color:#64748b;border-bottom:1px solid #eef2f7">' . e($k) . '</td><td align="right" style="padding:8px 0;font-weight:bold;color:' . $color . ';border-bottom:1px solid #eef2f7">' . e($v) . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;border-collapse:separate"><tr><td style="padding:18px">'
        . '<p style="margin:0;font-size:18px;font-weight:bold;color:#0f172a">' . e($q['title']) . '</p>'
        . '<p style="margin:2px 0 14px;font-size:13px;color:#64748b">' . e(quote_text($q['subtitle'])) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . $date('Check-in', (string) ($facts['Check-in'] ?? fmt_date($q['start_date'])))
        . '<td width="10" style="width:10px;min-width:10px;font-size:0;line-height:0">&nbsp;</td>' . $date('Check-out', (string) ($facts['Check-out'] ?? fmt_date((string) $q['end_date']))) . '</tr></table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;font-size:14px">' . $list . '</table></td></tr></table>';
}

/** A titled card with label/value rows (packages). */
function email_facts_card(string $title, string $subtitle, array $facts): string
{
    $list = '';
    foreach ($facts as [$k, $v]) {
        $list .= '<tr><td style="padding:8px 0;color:#64748b;border-bottom:1px solid #eef2f7">' . e(quote_text($k)) . '</td><td align="right" style="padding:8px 0;font-weight:bold;color:#0f172a;border-bottom:1px solid #eef2f7">' . e(quote_text((string) $v)) . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:4px;border:1px solid #e2e8f0;border-radius:14px;border-collapse:separate"><tr><td style="padding:18px">'
        . '<p style="margin:0;font-size:18px;font-weight:bold;color:#0f172a">' . e($title) . '</p><p style="margin:2px 0 8px;font-size:13px;color:#64748b">' . e($subtitle) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">' . $list . '</table></td></tr></table>';
}

/** Each traveler's name as it will be on the ticket, and the reminder that it must match their ID. */
function email_travelers(array $b): string
{
    $rows = '';
    foreach ($b['travelers'] as $i => $t) {
        $name = mb_strtoupper(trim("{$t['first']} {$t['last']}"));
        $type = preg_replace('/\s*\d+$/', '', (string) ($b['quote']['slots'][$i]['label'] ?? ''));
        $initial = mb_substr(trim((string) $t['first']) ?: $name, 0, 1);
        $rows .= '<tr><td width="40" style="padding:10px 0;border-bottom:1px solid #eef2f7"><div style="width:32px;height:32px;line-height:32px;border-radius:16px;background:#e8eefc;color:#1c54f0;font-weight:bold;text-align:center;font-size:14px">' . e(mb_strtoupper($initial)) . '</div></td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eef2f7;font-size:15px;font-weight:bold;color:#0f172a;letter-spacing:0.02em">' . e($name) . '</td>'
            . '<td align="right" style="padding:10px 0;border-bottom:1px solid #eef2f7;font-size:13px;color:#64748b">' . e($type) . '</td></tr>';
    }
    $rule = $b['kind'] === 'hotel'
        ? "The guest's name must match their ID at check-in."
        : "Names must match each traveler's passport or government ID exactly. Airlines don't allow name changes once tickets are issued.";
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;background:#fdf2f2;border-radius:12px"><tr>'
        . '<td width="36" valign="top" style="padding:12px 0 12px 12px">' . email_icon('tip-id', 32) . '</td>'
        . '<td style="padding:12px 14px;font-size:13px;line-height:1.55;color:#7f1d1d"><strong style="color:#dc2626">' . e($rule) . '</strong><br>Something wrong? Don\'t pay yet. Reply to this email and we\'ll correct it first.</td></tr></table>';
}

/** The price lines, member discount, extras and the total; hotel charges paid at check-in in their own box. */
function email_price(array $b): string
{
    $q = $b['quote'];
    $row = fn(string $label, string $value, string $color = '#0f172a') => '<tr><td style="padding:7px 0;font-size:14px;color:#475569">' . $label . '</td><td align="right" style="padding:7px 0;font-size:14px;color:' . $color . '">' . $value . '</td></tr>';
    $rows = '';
    foreach ($q['lines'] as $l) if (!is_extra_line($l)) $rows .= $row(e($l['label']), money($l['amount']));
    if ($q['discount'] > 0) $rows .= $row('Member discount (' . round($q['discount_rate'] * 100) . '%)', '&minus;' . money($q['discount']), '#047857');
    foreach ($q['lines'] as $l) if (is_extra_line($l)) $rows .= $row(e($l['label']), money($l['amount']));
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:14px;border-collapse:separate"><tr><td style="padding:12px 18px 16px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows
        . '<tr><td style="padding:12px 0 0;border-top:2px solid #0f1d4f;font-size:16px;font-weight:bold;color:#0f1d4f">Total</td>'
        . '<td align="right" style="padding:12px 0 0;border-top:2px solid #0f1d4f;font-size:22px;font-weight:bold;color:#0f1d4f">' . money($b['total']) . '</td></tr>'
        . '<tr><td colspan="2" align="right" style="padding-top:2px;font-size:12px;color:#64748b">US dollars · taxes and fees included</td></tr></table></td></tr></table>';
    if (!empty($q['at_hotel'])) {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;background:#fff8e6;border:1px solid #f6dfa0;border-radius:12px;border-collapse:separate"><tr><td style="padding:12px 16px;font-size:13px;line-height:1.55;color:#78350f">'
            . '<strong>Due at the hotel (not included):</strong> ' . e(implode(', ', array_map(fn($f) => $f['label'] . ' ' . money($f['amount']), $q['at_hotel'])))
            . '<br>Paid directly to the hotel at check-in.</td></tr></table>';
    }
    return $html;
}

/** Passports & visas, payment limit and the fare rules, each with its picture. */
function email_tips(array $b): string
{
    $icons = ['documents' => 'tip-globe', 'limit' => 'tip-card', 'important' => 'tip-alert'];
    $rows = '';
    foreach (checklist_steps($b, true) as [$key, , $title, $body]) {
        if (!isset($icons[$key])) continue;
        $rows .= '<tr><td width="44" valign="top" style="padding:14px 0 14px 14px">' . email_icon($icons[$key], 36) . '</td>'
            . '<td style="padding:14px 16px 14px 12px;border-bottom:1px solid #eef2f7"><p style="margin:0 0 3px;font-size:15px;font-weight:bold;color:#0f1d4f">' . e($title) . '</p>'
            . '<p style="margin:0;font-size:13px;line-height:1.55;color:#334155">' . $body . '</p></td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:14px">' . $rows . '</table>';
}

/** The optional Travel Care add-on (flights), offered like a travel agent would. */
function email_care(array $b): string
{
    $fee = change_service_fee();
    $site = (string) config('site_name');
    $perks = array_values(array_filter([
        $fee > 0 ? "No $site service fee when you change dates or cancel (normally " . money($fee) . ' each time)' : null,
        'Priority help: your change and cancellation requests are handled first',
        "You still pay the airline's own penalty and any fare difference",
    ]));
    $list = implode('', array_map(fn($p) => '<tr><td width="20" valign="top" style="color:#e9c46a;font-weight:bold;font-size:14px;padding:3px 0">&#10003;</td><td style="padding:3px 0;font-size:13px;line-height:1.5;color:#dbe4ff">' . e($p) . '</td></tr>', $perks));
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;background:#0f1d4f;border-radius:16px"><tr><td style="padding:22px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="56" valign="top">' . email_icon('care-shield', 48) . '</td><td valign="top" style="padding-left:12px">'
        . '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#d4af37;color:#ffffff;font-size:11px;font-weight:bold;letter-spacing:0.06em">RECOMMENDED</span>'
        . '<p style="margin:8px 0 2px;font-size:19px;font-weight:bold;color:#ffffff">Travel Care Protection</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.5;color:#dbe4ff">Extra flexibility if your plans change, for just <strong style="color:#e9c46a">' . money(travel_care_price($b)) . '</strong> (' . round(travel_care_rate() * 100) . '% of your ticket price).</p>'
        . '</td></tr></table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px">' . $list . '</table>'
        . '<p style="margin:12px 0 0;font-size:12px;line-height:1.5;color:#a9b6dd">Add it on your review page before you pay. Travel Care is a ' . e($site) . ' service, not insurance, and can\'t be added after you pay.</p>'
        . '</td></tr></table>';
}

/** "Warm regards, Angel" with the ways to reach us. */
function email_help(): string
{
    $site = (string) config('site_name');
    $name = trim((string) config('chat_name')) ?: "The $site team";
    $c = support_contacts();
    $btn = fn(string $href, string $label) => '<a href="' . e($href) . '" style="display:inline-block;margin:8px 8px 0 0;padding:8px 14px;border:1px solid #c7d2fe;border-radius:10px;color:#1c54f0;font-size:13px;font-weight:bold;text-decoration:none">' . e($label) . '</a>';
    $buttons = (isset($c['email']) ? $btn('mailto:' . $c['email'], 'Email ' . $c['email']) : '')
        . (isset($c['whatsapp']) ? $btn('https://wa.me/' . preg_replace('/\D/', '', $c['whatsapp']), 'WhatsApp ' . $c['whatsapp']) : '')
        . (isset($c['phone']) ? $btn('tel:' . preg_replace('/[^\d+]/', '', $c['phone']), 'Call ' . $c['phone']) : '');
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;border:1px solid #e2e8f0;border-radius:16px;border-collapse:separate"><tr><td style="padding:18px 20px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="52" valign="top"><div style="width:48px;height:48px;border-radius:24px;background:#0f1d4f;color:#e9c46a;font-weight:bold;font-size:20px;line-height:48px;text-align:center">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</div></td>'
        . '<td valign="top" style="padding-left:12px;font-size:14px;line-height:1.5;color:#334155">Questions about your trip? Just reply to this email. We\'re always happy to help.'
        . '<br><span style="color:#64748b">Warm regards,</span><br><strong style="color:#0f1d4f">' . e($name) . '</strong><span style="color:#64748b">, your ' . e($site) . ' travel assistant</span>'
        . ($buttons !== '' ? '<br>' . $buttons : '') . '</td></tr></table></td></tr></table>';
}

/** Three reasons to book with us, with pictures. */
function email_trust(): string
{
    $pct = (int) round((float) config('member_discount_rate') * 100);
    $items = [
        ['trust-lock', 'Secure payment', 'Encrypted checkout'],
        ['trust-headset', 'Real travel assistants', 'Before and after you fly'],
        $pct > 0 ? ['trust-tag', 'Member prices', "$pct% off for members"] : ['trust-tag', 'Flights, hotels, packages', 'All in one place'],
    ];
    $cells = implode('', array_map(fn($i) => '<td width="33%" align="center" valign="top" style="padding:0 4px">' . '<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr><td>' . email_icon($i[0], 40) . '</td></tr></table>'
        . '<p style="margin:8px 0 0;font-size:13px;font-weight:bold;color:#0f1d4f">' . e($i[1]) . '</p><p style="margin:2px 0 0;font-size:12px;color:#64748b">' . e($i[2]) . '</p></td>', $items));
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;padding-top:24px;border-top:1px solid #e2e8f0"><tr>' . $cells . '</tr></table>';
}
