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
                ], 20);
                return $res['status'] < 300 ? null : 'Resend error ' . $res['status'] . ': ' . ($res['json']['message'] ?? 'request failed');
            case 'smtp':
                return smtp_send($to, $mail);
            default:
                $entry = sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", gmdate('c'), $to, $mail['subject'], $mail['text']);
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
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    ];
    $part = fn(string $type, string $body) => "--$boundary\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n";
    return implode("\r\n", $headers) . "\r\n\r\n" . $part('text/plain', $mail['text']) . $part('text/html', $mail['html']) . "--$boundary--";
}

function email_layout(string $heading, string $body): string
{
    $site = e(config('site_name'));
    return '<!doctype html><html><body style="margin:0;background:#f6f8fc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden">'
        . "<tr><td style=\"background:#1c54f0;padding:20px 28px;color:#fff;font-size:20px;font-weight:bold\">$site</td></tr>"
        . '<tr><td style="padding:28px"><h1 style="margin:0 0 16px;font-size:22px">' . e($heading) . "</h1>$body</td></tr>"
        . "<tr><td style=\"padding:16px 28px;background:#f1f5f9;color:#64748b;font-size:12px\">You're receiving this because you have an account with $site.</td></tr>"
        . '</table></td></tr></table></body></html>';
}

function email_button(string $href, string $label): string
{
    return '<p style="margin:24px 0"><a href="' . e($href) . '" style="display:inline-block;padding:12px 20px;background:#1c54f0;color:#fff;border-radius:10px;text-decoration:none;font-weight:bold">' . e($label) . '</a></p>';
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
        'Dates' => fmt_date($q['start_date']) . ($q['end_date'] ? ' – ' . fmt_date($q['end_date']) : ''),
        'Travelers' => implode(', ', array_map(fn($t) => "{$t['first']} {$t['last']}", $b['travelers'])),
        'Total' => money($b['total']),
    ];
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

function simple_email(string $subject, string $heading, string $firstName, array $paragraphs, array $rows, string $link, string $button): array
{
    $site = config('site_name');
    $text = "Hi $firstName,\n\n" . implode("\n\n", $paragraphs) . ($rows ? "\n\n" . rows_text($rows) : '') . "\n\n$button: $link\n\n— The $site team";
    $html = email_p('Hi ' . e($firstName) . ',') . implode('', array_map(fn($p) => email_p(nl2br(e($p))), $paragraphs)) . ($rows ? rows_html($rows) : '') . email_button($link, $button);
    return ['subject' => $subject, 'text' => $text, 'html' => email_layout($heading, $html)];
}

/** Emails the booking contact about a status change. */
function notify_booking(string $event, string $reference): void
{
    try {
        $r = db_one('SELECT * FROM bookings WHERE reference = ?', [$reference]);
        if (!$r) return;
        $b = to_booking($r);
        $first = $b['travelers'][0]['first'] ?? 'there';
        $link = app_url() . '/trip.php?ref=' . rawurlencode($reference);
        $rows = trip_rows($b);
        $title = "{$b['quote']['title']} ({$reference})";
        $mail = match ($event) {
            'reserved' => payments_enabled()
                ? simple_email("Trip reserved: $title", 'Your trip is reserved!', $first, [
                    'You can pay securely online now to confirm it — or a travel assistant will contact you within 24 hours.',
                ], $rows, pay_link($reference), 'Pay now — ' . money($b['total']))
                : simple_email("Trip reserved: $title", 'Your trip is reserved!', $first, [
                    'A travel assistant will contact you within 24 hours to confirm availability and arrange payment.',
                ], $rows, $link, 'View my trip'),
            'paid' => simple_email("Payment received: $title", 'Payment received — thank you!', $first, [
                'Thanks — we\'ve received your payment of ' . money($b['total']) . '.',
                'A travel assistant is now ' . ($b['kind'] === 'hotel' ? 'booking your room' : 'issuing your tickets') . '. You\'ll get another email with your confirmation code, usually within a few hours.',
            ], $rows, $link, 'View my trip'),
            'ticketed' => simple_email(
                ($b['kind'] === 'hotel' ? 'Room booked' : 'Ticket issued') . ": $title — code {$b['supplier_ref']}",
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

/** Link that opens the trip page at the payment box (after signing in if needed). */
function pay_link(string $reference): string
{
    return app_url() . '/trip.php?ref=' . rawurlencode($reference) . '&pay=1';
}

/** Staff-sent payment reminder with a Pay now button and an optional personal message. */
function send_payment_link(array $b, string $message): void
{
    $first = $b['travelers'][0]['first'] ?? 'there';
    $paragraphs = ["Your trip {$b['reference']} is reserved and waiting for payment. You can pay securely with PayPal or a debit/credit card — it only takes a minute."];
    if ($message !== '') array_unshift($paragraphs, $message);
    send_email($b['contact_email'], simple_email(
        "Payment for your trip {$b['reference']} (" . money($b['total']) . ')',
        'Ready to confirm your trip?',
        $first,
        $paragraphs,
        trip_rows($b),
        pay_link($b['reference']),
        'Pay now — ' . money($b['total']),
    ));
}
