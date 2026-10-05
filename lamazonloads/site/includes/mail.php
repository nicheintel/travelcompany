<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Sending email. Through your Hostinger mailbox (SMTP) when smtp_pass is set in config.local.php,
 * otherwise with PHP's mail(). mail_transport 'file' saves emails to storage/mail/ instead (testing).
 * Returns false when it couldn't send; the reason goes to the PHP error log.
 */
function support_email(): string
{
    return (string) (config('support_email') ?: config('contact_email'));
}

function mail_from(): string
{
    $from = (string) (config('mail_from') ?: config('smtp_user') ?: config('contact_email'));
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'info@lamazonloads.com';
}

function send_mail(string $to, string $subject, string $text, string $html, string $replyTo = ''): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : '';
    $pass = (string) config('smtp_pass');
    $transport = (string) config('mail_transport') ?: ($pass !== '' && !str_starts_with($pass, 'PUT_') ? 'smtp' : 'mail');
    try {
        [$headers, $body] = build_message($to, $subject, $text, $html, $replyTo);
        if ($transport === 'file') {
            $dir = dirname(__DIR__) . '/storage/mail';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml',
                'To: <' . $to . ">\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body, LOCK_EX);
        } elseif ($transport === 'smtp') {
            smtp_send($to, 'To: <' . $to . ">\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
        } else {
            if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . mail_from())) {
                throw new RuntimeException('mail() returned false');
            }
        }
        return true;
    } catch (Throwable $ex) {
        error_log('[mail] could not send to ' . $to . ': ' . $ex->getMessage());
        return false;
    }
}

/** Headers (without To/Subject) and a text + HTML body. */
function build_message(string $to, string $subject, string $text, string $html, string $replyTo): array
{
    $b = 'll-' . bin2hex(random_bytes(12));
    $from = mail_from();
    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode('LamazonLoads') . '?= <' . $from . '>',
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (substr((string) strrchr($from, '@'), 1) ?: 'lamazonloads.com') . '>',
        'MIME-Version: 1.0',
        'Auto-Submitted: auto-generated',
        'Content-Type: multipart/alternative; boundary="' . $b . '"',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: <' . $replyTo . '>';
    }
    $part = fn (string $type, string $body) => "--$b\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode((string) preg_replace('/\R/', "\r\n", $body)) . "\r\n";
    return [$headers, $part('text/plain', $text) . $part('text/html', $html) . "--$b--\r\n"];
}

/* A small SMTP client: SSL (port 465) or STARTTLS (587), with AUTH LOGIN. */
function smtp_send(string $to, string $message): void
{
    $host = (string) config('smtp_host');
    $port = (int) config('smtp_port');
    $secure = (string) config('smtp_secure');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl' : 'tcp') . "://$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("can't connect to $host:$port ($errstr)");
    }
    stream_set_timeout($fp, 20);
    $read = function (array $expect) use ($fp): string {
        $reply = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if (!in_array((int) substr($reply, 0, 3), $expect, true)) {
            throw new RuntimeException('mail server said: ' . trim($reply));
        }
        return $reply;
    };
    $cmd = function (string $line, array $expect) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        return $read($expect);
    };
    try {
        $me = parse_url((string) config('app_url'), PHP_URL_HOST) ?: 'lamazonloads.com';
        $read([220]);
        $cmd("EHLO $me", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $cmd("EHLO $me", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) config('smtp_user')), [334]);
        $cmd(base64_encode((string) config('smtp_pass')), [235]);
        $cmd('MAIL FROM:<' . mail_from() . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $cmd(rtrim((string) preg_replace('/^\./m', '..', $message), "\r\n") . "\r\n.", [250]);
        $cmd('QUIT', [221]);
    } finally {
        fclose($fp);
    }
}

/** Full link for emails, e.g. https://lamazonloads.com/account.php?chat=1 */
function abs_url(string $path): string
{
    return rtrim((string) config('app_url'), '/') . url($path);
}

/** The look of every LamazonLoads email: logo, heading, text, blue button. Returns [text, html]. */
function email_body(string $heading, array $paragraphs, ?string $button = null, ?string $link = null, string $footnote = ''): array
{
    $logo = rtrim((string) config('app_url'), '/') . url('assets/brand/logo.png');
    $domain = parse_url((string) config('app_url'), PHP_URL_HOST) ?: 'lamazonloads.com';
    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#F5F7FB;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FB;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;border:1px solid #E1E7F0;font-family:Arial,Helvetica,sans-serif;color:#0B1733;">'
        . '<tr><td style="padding:26px 32px 6px;"><img src="' . e($logo) . '" width="120" height="67" alt="LamazonLoads" style="display:block;"></td></tr>'
        . '<tr><td style="padding:8px 32px 0;"><h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#0A2463;">' . e($heading) . '</h1>';
    foreach ($paragraphs as $i => $p) {
        $quote = $i === count($paragraphs) - 1 && count($paragraphs) > 1;
        $html .= $quote
            ? '<p style="margin:0 0 14px;padding:12px 14px;border-left:4px solid #1E63E9;background:#EEF4FF;border-radius:8px;font-size:15px;line-height:1.55;color:#24304A;white-space:pre-wrap;">' . e($p) . '</p>'
            : '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#24304A;">' . e($p) . '</p>';
    }
    if ($button && $link) {
        $html .= '<p style="margin:22px 0 10px;"><a href="' . e($link) . '" style="display:inline-block;background:#1E63E9;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 22px;border-radius:999px;">' . e($button) . '</a></p>'
            . '<p style="margin:0 0 14px;font-size:12.5px;line-height:1.5;color:#5E6B85;">Or copy this link into your browser:<br><a href="' . e($link) . '" style="color:#1E63E9;word-break:break-all;">' . e($link) . '</a></p>';
    }
    if ($footnote !== '') {
        $html .= '<p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#5E6B85;">' . e($footnote) . '</p>';
    }
    $html .= '<p style="margin:22px 0 0;font-size:15px;line-height:1.55;color:#0A2463;">The LamazonLoads Team<br><span style="color:#1E63E9;font-weight:bold;font-style:italic;">Why wait? Let\'s freight.</span></p>'
        . '</td></tr><tr><td style="padding:24px 32px 26px;font-size:12px;color:#8A96AD;">&copy; ' . date('Y') . ' LamazonLoads &middot; ' . e($domain) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
    $text = $heading . "\n\n" . implode("\n\n", $paragraphs)
        . ($button && $link ? "\n\n$button: $link" : '')
        . ($footnote !== '' ? "\n\n$footnote" : '')
        . "\n\nThe LamazonLoads Team\nWhy wait? Let's freight.\n" . $domain . "\n";
    return [$text, $html];
}
