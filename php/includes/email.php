<?php
declare(strict_types=1);

/**
 * Sends through Resend (https://resend.com) when resend_api_key is set. Otherwise the email
 * is written to storage/emails.log — handy on XAMPP to see reset links. Never throws.
 */
function send_email(string $to, array $mail): void
{
    $key = (string) config('resend_api_key');
    if ($key === '') {
        $entry = sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", gmdate('c'), $to, $mail['subject'], $mail['text']);
        @file_put_contents(dirname(__DIR__) . '/storage/emails.log', $entry, FILE_APPEND | LOCK_EX);
        return;
    }
    try {
        $from = (string) config('email_from') ?: config('site_name') . ' <onboarding@resend.dev>';
        $res = http_json('POST', 'https://api.resend.com/emails', ['Authorization: Bearer ' . $key], [
            'from' => $from, 'to' => $to, 'subject' => $mail['subject'], 'text' => $mail['text'], 'html' => $mail['html'],
        ], 20);
        if ($res['status'] >= 300) error_log('[email] Resend error ' . $res['status']);
    } catch (Throwable $err) {
        error_log('[email] ' . $err->getMessage());
    }
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
    $html = email_p('Hi ' . e($firstName) . ',') . implode('', array_map(fn($p) => email_p(e($p)), $paragraphs)) . ($rows ? rows_html($rows) : '') . email_button($link, $button);
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
            'reserved' => simple_email("Trip reserved: $title", 'Your trip is reserved!', $first, [
                payments_enabled()
                    ? 'You can pay securely online from your trip page, or a travel assistant will contact you within 24 hours.'
                    : 'A travel assistant will contact you within 24 hours to confirm availability and arrange payment.',
            ], $rows, $link, 'View my trip'),
            'paid' => simple_email("Payment received: $title", "Payment received — you're all set", $first, [
                'Thanks — we\'ve received your payment of ' . money($b['total']) . '. Your trip is confirmed.',
            ], $rows, $link, 'View my trip'),
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
