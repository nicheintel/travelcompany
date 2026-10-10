<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/** How emails go out: 'resend', 'smtp' (your own mailbox, e.g. Hostinger email) or 'log' (saved to a file). */
function email_method(): string
{
    if ((string) config('resend_api_key') !== '') return 'resend';
    if ((string) config('smtp_user') !== '' && (string) config('smtp_pass') !== '') return 'smtp';
    return 'log';
}

/** Sends an email. Never throws: a failed email must not undo a booking or password change. */
function send_email(string $to, array $mail): void
{
    $error = deliver_email($to, $mail);
    if ($error !== null) error_log('[email] ' . $error);
}

/** Returns null when sent, otherwise what went wrong. */
function deliver_email(string $to, array $mail): ?string
{
    try {
        switch (email_method()) {
            case 'resend':
                $from = (string) config('email_from') ?: config('site_name') . ' <onboarding@resend.dev>';
                $res = http_json('POST', 'https://api.resend.com/emails', ['Authorization: Bearer ' . config('resend_api_key')], [
                    'from' => $from, 'to' => $to, 'subject' => $mail['subject'], 'text' => $mail['text'], 'html' => $mail['html'],
                ] + (!empty($mail['reply_to']) && valid_email($mail['reply_to']) ? ['reply_to' => $mail['reply_to']] : []), 20);
                return $res['status'] < 300 ? null : 'Resend error ' . $res['status'] . ': ' . ($res['json']['message'] ?? 'request failed');
            case 'smtp':
                return smtp_send($to, $mail);
            default:
                $entry = sprintf("[%s] To: %s\n%sSubject: %s\n%s\n\n", gmdate('c'), $to, !empty($mail['reply_to']) ? "Reply-To: {$mail['reply_to']}\n" : '', $mail['subject'], $mail['text']);
                // A .php file starting with exit, so it can't be read from the web even where .htaccess is ignored.
                $file = dirname(__DIR__) . '/storage/emails.log.php';
                clearstatcache(true, $file);
                if (!is_file($file) || filesize($file) === 0) @file_put_contents($file, "<?php exit; ?>\n", LOCK_EX);
                @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
                return null;
        }
    } catch (Throwable $err) {
        return $err->getMessage();
    }
}

// ---------- Sending through your own mailbox (SMTP) ----------

/**
 * Sends one email through a mailbox's SMTP server (e.g. smtp.hostinger.com, port 465).
 * Port 465 uses SSL from the start, 587 switches to TLS (STARTTLS); a plain connection is only
 * allowed to this computer (for tests). The certificate is always checked.
 */
function smtp_send(string $to, array $mail): ?string
{
    $host = (string) config('smtp_host');
    $port = (int) config('smtp_port');
    $user = (string) config('smtp_user');
    $local = in_array($host, ['localhost', '127.0.0.1'], true);
    if ($port !== 465 && $port !== 587 && !$local) return "Port $port isn't supported — use 465 (SSL) or 587 (TLS).";
    if (!valid_email($user) || !valid_email($to) || preg_match('/[\r\n]/', $to . $user)) return 'Invalid email address.';

    $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host];
    if ($ca = ca_bundle()) $ssl['cafile'] = $ca;
    $ctx = stream_context_create(['ssl' => $ssl]);
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "Can't connect to $host:$port — $errstr";
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return [(int) substr($text, 0, 3), trim($text)];
    };
    $cmd = function (string $line, array $ok, string $show = '') use ($fp, $read): void {
        fwrite($fp, $line . "\r\n");
        [$code, $reply] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException('Mail server said: ' . ($reply ?: 'no answer') . ($show ? " (after $show)" : ''));
    };

    try {
        [$code, $hello] = $read();
        if ($code !== 220) throw new RuntimeException("Mail server didn't greet us: $hello");
        $me = parse_url(app_url_or_local(), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $me", [250]);
        if ($port === 587) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Could not start a secure connection.');
            $cmd("EHLO $me", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334], 'the email address');
        $cmd(base64_encode((string) config('smtp_pass')), [235], 'the password — check the email address and password');
        $cmd("MAIL FROM:<$user>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251], 'the recipient');
        $cmd('DATA', [354]);
        fwrite($fp, smtp_message($user, $to, $mail) . "\r\n.\r\n");
        [$code, $reply] = $read();
        if ($code !== 250) throw new RuntimeException("Mail server refused the email: $reply");
        fwrite($fp, "QUIT\r\n");
        return null;
    } catch (Throwable $err) {
        return $err->getMessage();
    } finally {
        fclose($fp);
    }
}

/** The site address if known, else localhost (only used to say hello to the mail server). */
function app_url_or_local(): string
{
    try {
        return app_url();
    } catch (Throwable) {
        return 'http://localhost';
    }
}

/** Builds the email: plain text + HTML versions, UTF-8, lines dot-stuffed for SMTP. */
function smtp_message(string $from, string $to, array $mail): string
{
    $boundary = 'b' . bin2hex(random_bytes(12));
    $name = str_replace(['"', "\r", "\n"], '', (string) config('site_name'));
    $domain = substr(strrchr($from, '@'), 1);
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>",
        "To: <$to>",
        'Subject: =?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $mail['subject'])) . '?=',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . "@$domain>",
        ...(!empty($mail['reply_to']) && valid_email($mail['reply_to']) ? ['Reply-To: <' . $mail['reply_to'] . '>'] : []),
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    ];
    $part = fn(string $type, string $body) => "--$boundary\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n";
    return implode("\r\n", $headers) . "\r\n\r\n" . $part('text/plain', $mail['text']) . $part('text/html', $mail['html']) . "--$boundary--";
}

/** An image that ships with the website for emails (assets/email/), as a full web address. */
function email_image(string $file): string
{
    $path = dirname(__DIR__) . '/assets/email/' . $file;
    return app_url_or_local() . '/assets/email/' . $file . (is_file($path) ? '?v=' . filemtime($path) : '');
}

/**
 * Every email: navy header with the logo, an optional big photo (e.g. the trip's destination),
 * the message, then our contact details. Email apps don't show modern image formats, so these are JPG/PNG.
 */
function email_layout(string $heading, string $body, ?array $photo = null): string
{
    $site = e(config('site_name'));
    $home = e(app_url_or_local() . '/');
    $c = support_contacts();
    $contact = implode(' &nbsp;·&nbsp; ', array_filter([
        isset($c['email']) ? '<a href="mailto:' . e($c['email']) . '" style="color:#1c54f0;text-decoration:none">' . e($c['email']) . '</a>' : null,
        isset($c['whatsapp']) ? '<a href="https://wa.me/' . e(preg_replace('/\D/', '', $c['whatsapp'])) . '" style="color:#1c54f0;text-decoration:none">WhatsApp ' . e($c['whatsapp']) . '</a>' : null,
        isset($c['phone']) ? e($c['phone']) : null,
    ]));
    return '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><style>@media (max-width:480px){.ff-tag{display:none!important}}</style></head>'
        . '<body style="margin:0;background:#eef2f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f9"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,29,79,0.08)">'
        . '<tr><td bgcolor="#0f1d4f" style="background:#0f1d4f;padding:20px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td><a href="' . $home . '" style="text-decoration:none"><img src="' . e(email_image('logo-light.png')) . '" width="182" height="40" alt="' . $site . '" style="display:block;border:0;width:182px;height:40px;color:#ffffff;font-size:22px;font-weight:bold"></a></td>'
        . '<td class="ff-tag" align="right" style="color:#e9c46a;font-size:12px;font-weight:bold;letter-spacing:0.04em;white-space:nowrap">FLIGHTS · HOTELS · PACKAGES</td></tr></table></td></tr>'
        . ($photo ? '<tr><td style="line-height:0;font-size:0"><img src="' . e($photo['url']) . '" width="600" alt="' . e($photo['alt']) . '" style="display:block;width:100%;max-width:600px;height:auto;border:0"></td></tr>'
            . '<tr><td bgcolor="#d4af37" style="background:#d4af37;height:4px;line-height:4px;font-size:0">&nbsp;</td></tr>' : '')
        . '<tr><td style="padding:28px"><h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#0f1d4f">' . e($heading) . "</h1>$body</td></tr>"
        . '<tr><td style="padding:20px 28px;background:#f6f8fc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#64748b">'
        . '<a href="' . $home . '" style="color:#0f1d4f;font-weight:bold;text-decoration:none">' . $site . '</a>: your travel assistant for affordable flights, hotels and holiday packages.'
        . ($contact !== '' ? '<br>' . $contact : '')
        . "<br>You're receiving this because you have an account with $site.</td></tr>"
        . '</table></td></tr></table></body></html>';
}

/**
 * The trip's destination photo for booking emails (assets/email/dest-<airport>.jpg), or a photo for the kind of
 * trip, plus the destination city when we know it.
 */
function trip_photo(array $b): array
{
    $q = $b['quote'];
    $code = match ($b['kind']) {
        'flight' => $q['flight']['outbound']['to'] ?? null,
        'hotel' => (function () use ($q) { parse_str((string) ($q['query'] ?? ''), $p); return $p['to'] ?? null; })(),
        default => ($pkg = find_package((string) ($q['package']['slug'] ?? ''), false)) ? $pkg['to_code'] : null,
    };
    $code = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $code));
    $city = $code !== '' ? (airport($code)['city'] ?? null) : null;
    $file = $code !== '' && is_file(dirname(__DIR__) . "/assets/email/dest-$code.jpg") ? "dest-$code.jpg" : "trip-{$b['kind']}.jpg";
    return ['url' => email_image($file), 'alt' => $city ?? $q['title'], 'city' => $city];
}

function email_button(string $href, string $label): string
{
    return '<p style="margin:24px 0"><a href="' . e($href) . '" style="display:inline-block;padding:14px 24px;background:#d4af37;color:#ffffff;border-radius:12px;text-decoration:none;font-weight:bold;font-size:16px;box-shadow:0 4px 12px rgba(212,175,55,0.35)">' . e($label) . '</a></p>';
}

function email_p(string $html): string
{
    return '<p style="margin:0 0 12px;line-height:1.5">' . $html . '</p>';
}

function trip_rows(array $b): array
{
    $q = $b['quote'];
    return [
        'Reference' => $b['reference'],
        'Trip' => $q['title'],
        'Dates' => fmt_date($q['start_date']) . ($q['end_date'] ? ' to ' . fmt_date($q['end_date']) : ''),
        'Travelers' => implode(', ', array_map(fn($t) => trim("{$t['first']} {$t['last']}"), $b['travelers'])),
        'Total' => money($b['total']),
    ] + (($tax = array_sum(array_map(fn($l) => $l['label'] === 'Taxes & fees' ? $l['amount'] : 0, $q['lines']))) > 0 ? ['Includes taxes & fees' => money($tax)] : [])
      + (!empty($q['care']) ? ['Travel Care Protection' => 'Included (' . money((int) $q['care']) . ')'] : [])
      + (!empty($q['tip']) ? ['Tip for your travel assistant' => money((int) $q['tip']) . ' — thank you!'] : [])
      + (!empty($q['at_hotel']) ? ['Due at the hotel (not included)' => implode(', ', array_map(fn($f) => $f['label'] . ' ' . money($f['amount']), $q['at_hotel']))] : []);
}

function rows_html(array $rows): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-top:1px solid #e2e8f0">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td style="padding:8px 0;color:#64748b;border-bottom:1px solid #e2e8f0">' . e($k) . '</td><td align="right" style="padding:8px 0;font-weight:bold;border-bottom:1px solid #e2e8f0">' . e($v) . '</td></tr>';
    }
    return $html . '</table>';
}

function rows_text(array $rows): string
{
    return implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($rows), $rows));
}

/** $formal: "Dear …" and a "Kind regards" sign-off instead of "Hi …". */
function simple_email(string $subject, string $heading, string $firstName, array $paragraphs, array $rows, string $link, string $button, bool $formal = false, ?array $photo = null): array
{
    $site = config('site_name');
    $hello = ($formal ? 'Dear ' : 'Hi ') . $firstName . ',';
    $text = "$hello\n\n" . implode("\n\n", $paragraphs) . ($rows ? "\n\n" . rows_text($rows) : '') . "\n\n$button: $link\n\n"
        . ($formal ? "Kind regards,\nThe $site Team" : "The $site team");
    $html = email_p(e($hello)) . implode('', array_map(fn($p) => email_p(nl2br(e($p))), $paragraphs)) . ($rows ? rows_html($rows) : '') . email_button($link, $button)
        . ($formal ? email_p('Kind regards,<br>The ' . e($site) . ' Team') : '');
    return ['subject' => $subject, 'text' => $text, 'html' => email_layout($heading, $html, $photo)];
}

/** Emails the booking contact about a status change. */
/** Emails are always written in English (dates, labels), whatever language the visitor uses. */
function notify_booking(string $event, string $reference): void
{
    in_english(fn() => notify_booking_now($event, $reference));
}

function notify_booking_now(string $event, string $reference): void
{
    try {
        $r = db_one('SELECT * FROM bookings WHERE reference = ?', [$reference]);
        if (!$r) return;
        $b = to_booking($r);
        $first = $b['travelers'][0]['first'] ?? 'there';
        $link = trip_link($b);
        $rows = trip_rows($b);
        $title = "{$b['quote']['title']} ({$reference})";
        $photo = trip_photo($b);
        $excited = $photo['city']
            ? ($b['kind'] === 'hotel' ? "Thank you for choosing {site}! We're excited to help you plan your stay in {$photo['city']}." : "Thank you for choosing {site}! We're excited to help you get to {$photo['city']}.")
            : 'Thank you for choosing {site}! We have received your reservation.';
        $excited = str_replace('{site}', (string) config('site_name'), $excited);
        $mail = match ($event) {
            // A checked bag was requested: payment waits until the bag price is added (no "Pay now" yet).
            'reserved' => ($b['bag_status'] ?? null) === 'pending'
                ? simple_email("Trip reserved: $title", trip_heading($b, 'reserved'), $first, [
                    $excited,
                    'Since you added a checked bag, our team is confirming the baggage fee with the airline. You will receive an email shortly with your final total and a secure link to complete your payment.',
                    'You will only be charged once you have seen and approved the final price.',
                ], array_diff_key($rows, ['Total' => true]) + ['Checked bag' => 'Requested (price to be confirmed)', 'Total so far' => money($b['total']) . ' + checked bag'], $link, 'View my trip', true, $photo)
                : (payments_enabled() || gcash_enabled()
                ? checklist_email($b, "Trip reserved: $title", trip_heading($b, 'reserved'), [
                    $excited,
                    payments_enabled()
                        ? 'You can pay securely online now to confirm it' . (gcash_enabled() ? ' (PayPal, card or GCash)' : '') . '. If you prefer, a travel assistant will contact you within 24 hours.'
                        : 'You can pay with GCash on your trip page to confirm it. If you prefer, a travel assistant will contact you within 24 hours.',
                ])
                : simple_email("Trip reserved: $title", trip_heading($b, 'reserved'), $first, [
                    $excited,
                    'A travel assistant will contact you within 24 hours to confirm availability and arrange payment.',
                ], $rows, $link, 'View my trip', false, $photo)),
            'paid' => simple_email("Payment received: $title", 'Payment received. Thank you!', $first, [
                'Thank you, we\'ve received your payment of ' . money($b['total']) . '.',
                'A travel assistant is now ' . ($b['kind'] === 'hotel' ? 'booking your room' : 'issuing your tickets') . '. You\'ll get another email with your confirmation code, usually within a few hours.',
            ], $rows, $link, 'View my trip', false, $photo),
            'ticketed' => simple_email(
                ($b['kind'] === 'hotel' ? 'Room booked' : 'Ticket issued') . ": $title, code {$b['supplier_ref']}",
                $b['kind'] === 'hotel' ? 'Your room is booked!' : 'Your ticket is issued!',
                $first,
                array_values(array_filter([
                    ['flight' => 'Your airline booking code is ', 'hotel' => 'Your hotel confirmation number is ', 'package' => 'Your booking code is '][$b['kind']] . $b['supplier_ref'] . '.',
                    $b['kind'] === 'hotel' ? 'Show it at check-in.' : 'Use it to check in and manage your booking on the airline\'s website.',
                    (string) $b['ticket_note'],
                ])),
                ['Confirmation' => (string) $b['supplier_ref']] + $rows,
                $link,
                'View my trip',
                false,
                $photo,
            ),
            'cancelled' => simple_email("Reservation cancelled: $title", 'Reservation cancelled', $first, [
                'Your reservation has been cancelled. ' . ($b['paid_at']
                    ? 'Our team will contact you about your refund, which depends on the airline and hotel rules.'
                    : "You haven't been charged."),
            ], $rows, $link, 'View details'),
        };
        send_email($b['contact_email'], $mail);
    } catch (Throwable $err) {
        error_log("[email] $event email for $reference failed: " . $err->getMessage());
    }
}

/** Full link to a page for use in emails (falls back to a plain path if the site address isn't set). */
function account_link(string $page): string
{
    try {
        return app_url() . '/' . $page;
    } catch (RuntimeException $err) {
        error_log('[email] ' . $err->getMessage());
        return url($page);
    }
}

/**
 * Link to the trip page (after signing in if needed). While the trip is unpaid it carries the booking's private key,
 * which opens "Review details and confirm your trip" (see review_locked). $pay: straight to the payment box.
 */
function trip_link(array $b, bool $pay = false): string
{
    $key = $b['status'] === 'reserved' ? (string) ($b['quote']['review_key'] ?? '') : '';
    return app_url() . '/trip.php?' . http_build_query(['ref' => $b['reference']] + ($pay ? ['pay' => 1] : []) + ($key !== '' ? ['k' => $key] : []));
}

function pay_link(array $b): string
{
    return trip_link($b, true);
}

/**
 * An email for a trip that can be paid now: the trip details and the same numbered "Before you pay" steps as the
 * trip page (like a travel agent's authorization form), then a button to the checklist and payment.
 */
function checklist_email(array $b, string $subject, string $heading, array $intro): array
{
    $site = (string) config('site_name');
    $first = $b['travelers'][0]['first'] ?? 'there';
    $link = pay_link($b);
    $button = 'Review & pay securely: ' . money($b['total']);
    $rows = trip_rows($b);
    $photo = trip_photo($b);
    $emoji = ['itinerary' => '✈️', 'stay' => '🏨', 'names' => '👤', 'documents' => '🛂', 'total' => '💵', 'limit' => '💳', 'important' => '⚠️', 'care' => '🛡️', 'final' => '✅', 'more' => '🌴'];
    $html = email_p(e("Dear $first,")) . implode('', array_map(fn($p) => email_p(nl2br(e($p))), $intro))
        . email_p('Please take a few moments and <strong style="color:#dc2626">read the steps below</strong> before you pay.')
        . rows_html($rows) . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
    $text = "Dear $first,\n\n" . implode("\n\n", $intro) . "\n\nPlease take a few moments and read the steps below before you pay.\n\n" . rows_text($rows) . "\n";
    foreach (checklist_steps($b, true) as $i => [$key, , $title, $body]) {
        $n = $i + 1;
        $html .= '<tr><td valign="top" width="44" style="padding:14px 0"><div style="width:32px;height:32px;border-radius:16px;background:#d4af37;color:#ffffff;font-weight:bold;font-size:15px;line-height:32px;text-align:center">' . $n . '</div></td>'
            . '<td valign="top" style="padding:14px 0 14px 8px;border-bottom:1px solid #e2e8f0"><p style="margin:0 0 4px;font-weight:bold;font-size:15px;color:#0f1d4f">' . ($emoji[$key] ?? '') . ' ' . e($title) . '</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.55;color:#334155">' . $body . '</p></td></tr>';
        $text .= "\n$n. $title\n" . html_entity_decode(strip_tags(str_replace('<br>', "\n", $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
    }
    $html .= '</table>' . email_button($link, $button)
        . email_p('<span style="font-size:13px;color:#64748b">If the button doesn\'t work, copy this link into your browser:<br><a href="' . e($link) . '" style="color:#1c54f0;word-break:break-all">' . e($link) . '</a></span>')
        . email_p('If you have any questions, just reply to this email. We\'re always happy to help.')
        . email_signoff();
    $text .= "\n$button: $link\n\nIf you have any questions, just reply to this email. We're always happy to help.\n\n" . email_signoff(true);
    $reply = (string) config('support_email') ?: (string) config('smtp_user');
    return ['subject' => $subject, 'text' => $text, 'html' => email_layout($heading, $html, $photo)] + (valid_email($reply) ? ['reply_to' => $reply] : []);
}

/** "Warm regards, Angel, your FareFinders travel assistant" (the name customers see in the chat). */
function email_signoff(bool $text = false): string
{
    $site = (string) config('site_name');
    $name = trim((string) config('chat_name')) ?: "The $site team";
    return $text
        ? "Warm regards,\n$name\nYour $site travel assistant"
        : '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 4px"><tr>'
            . '<td valign="middle" style="padding-right:12px"><div style="width:44px;height:44px;border-radius:22px;background:#0f1d4f;color:#e9c46a;font-weight:bold;font-size:18px;line-height:44px;text-align:center">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</div></td>'
            . '<td valign="middle" style="font-size:14px;line-height:1.45;color:#334155">Warm regards,<br><strong style="color:#0f1d4f">' . e($name) . '</strong><br>Your ' . e($site) . ' travel assistant</td></tr></table>';
}

/** "Your trip to Tokyo is reserved!" / "Ready to confirm your stay in Bangkok?" when we know the destination. */
function trip_heading(array $b, string $moment): string
{
    $city = trip_photo($b)['city'];
    $what = $b['kind'] === 'hotel' ? ($city ? "your stay in $city" : 'your stay') : ($city ? "your trip to $city" : 'your trip');
    return match ($moment) {
        'reserved' => ucfirst($what) . ' is reserved!',
        default => "Ready to confirm $what?",
    };
}

/** Staff-sent payment reminder with a Pay now button and an optional personal message. */
/** Emails are always written in English (dates, labels), whatever language the visitor uses. */
function send_payment_link(array $b, string $message): void
{
    in_english(fn() => send_payment_link_now($b, $message));
}

function send_payment_link_now(array $b, string $message): void
{
    $paragraphs = ["Your trip {$b['reference']} is reserved and waiting for payment. You can pay securely with PayPal or a debit/credit card. It only takes a minute."];
    if ($message !== '') array_unshift($paragraphs, $message);
    send_email($b['contact_email'], checklist_email($b, "Payment for your trip {$b['reference']} (" . money($b['total']) . ')', trip_heading($b, 'pay'), $paragraphs));
}

/** Emails every admin that a customer asked for checked bags on the trip page (payment waits for the bag price). */
function notify_admins_bag(string $ref, int $bags): void
{
    in_english(function () use ($ref, $bags) {
        $admins = array_unique(array_merge(configured_admins(), array_column(db_all("SELECT email FROM users WHERE role = 'admin'"), 'email')));
        if (!$admins) return;
        try {
            $link = app_url() . '/admin/booking.php?ref=' . rawurlencode($ref);
        } catch (RuntimeException $e) {
            $link = url('admin/booking.php', ['ref' => $ref]);
        }
        $mail = simple_email("Checked bag to price: $ref", 'Add the checked-bag price', 'there', [
            'A customer asked for ' . plural($bags, 'checked bag') . " on booking $ref before paying.",
            "Check the airline's bag price, then open the booking and enter what the customer pays. They can't pay until you do.",
        ], [], $link, 'Open the booking');
        foreach ($admins as $to) {
            try { send_email($to, $mail); } catch (Throwable $e) { error_log('[bags] admin email failed: ' . $e->getMessage()); }
        }
    });
}
