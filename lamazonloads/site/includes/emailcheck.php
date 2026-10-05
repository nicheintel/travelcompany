<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Sign-up email check: no temporary or disposable addresses (10 Minute Mail, Temp Mail, Mailinator…),
 * and the email's domain must be able to receive email (catches typos like gmial.com).
 * The list is includes/disposable_domains.txt; the site downloads a fresh copy to storage/ once a week.
 * Settings (config.local.php): block_disposable_emails, allowed_email_domains, blocked_email_domains.
 */
const DISPOSABLE_LIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

function disposable_fresh_file(): string
{
    return dirname(__DIR__) . '/storage/disposable_domains.txt';
}

function email_domain(string $email): string
{
    $at = strrpos($email, '@');
    return $at === false ? '' : rtrim(strtolower(trim(substr($email, $at + 1))), '.');
}

/** Domains from a comma/space separated setting. */
function email_domain_setting(string $value): array
{
    return array_values(array_filter(array_map(fn ($d) => trim(strtolower($d), " .\t\n\r"), preg_split('/[\s,]+/', $value) ?: [])));
}

function disposable_domains(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $fresh = disposable_fresh_file();
    $file = is_file($fresh) && filesize($fresh) > 20000 ? $fresh : __DIR__ . '/disposable_domains.txt';
    $set = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = strtolower(trim($line));
        if ($line !== '' && $line[0] !== '#') {
            $set[$line] = true;
        }
    }
    foreach (email_domain_setting((string) config('blocked_email_domains')) as $d) {
        $set[$d] = true;
    }
    return $set;
}

/** Is this domain, or a domain it belongs to (x.mailinator.com), on the list? */
function domain_is_disposable(string $domain): bool
{
    $domain = rtrim(strtolower($domain), '.');
    if ($domain === '') {
        return false;
    }
    $allowed = email_domain_setting((string) config('allowed_email_domains'));
    $list = disposable_domains();
    $parts = explode('.', $domain);
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $d = implode('.', array_slice($parts, $i));
        if (in_array($d, $allowed, true)) {
            return false;
        }
        if (isset($list[$d])) {
            return true;
        }
    }
    return false;
}

/** Mail servers for a domain: list of hosts, [] when it has none, null when DNS can't be asked. */
function email_mx_hosts(string $domain): ?array
{
    if (!function_exists('dns_get_record')) {
        return null;
    }
    $mx = @dns_get_record($domain . '.', DNS_MX);
    if ($mx === false) {
        return null;
    }
    $hosts = [];
    foreach ($mx as $r) {
        if (isset($r['target'])) {
            $hosts[] = rtrim(strtolower((string) $r['target']), '.');
        }
    }
    return $hosts;
}

/** '' when the address is fine for an account, otherwise the message to show. */
function email_signup_problem(string $email): string
{
    if (!config('block_disposable_emails')) {
        return '';
    }
    $domain = email_domain($email);
    if (in_array($domain, email_domain_setting((string) config('allowed_email_domains')), true)) {
        return '';
    }
    $temp = "Please use your real, permanent email address. Temporary or disposable emails (like 10 Minute Mail, Temp Mail or Mailinator) can't be used for a LamazonLoads account.";
    if (domain_is_disposable($domain)) {
        return $temp;
    }
    $hosts = email_mx_hosts($domain);
    if ($hosts === null) {
        return ''; // DNS not available: don't block real people
    }
    $noMail = 'The email domain "' . $domain . '" can\'t receive email. Check it for typos (for example gmail.com, not gmial.com).';
    if ($hosts === ['']) {
        return $noMail; // "null MX": the domain never accepts email
    }
    if ($hosts === []) {
        $a = @dns_get_record($domain . '.', DNS_A); // no mail servers: email can still go to the domain's own address
        if ($a === false) {
            return '';
        }
        if ($a === []) {
            return $noMail;
        }
    }
    foreach ($hosts as $h) {
        if ($h !== '' && domain_is_disposable($h)) {
            return $temp; // new throwaway domains of the same services
        }
    }
    return '';
}

/** Weekly: download a fresh copy of the list (keeps the old one if anything looks wrong). */
function refresh_disposable_list(): string
{
    $fresh = disposable_fresh_file();
    if (is_file($fresh) && filemtime($fresh) > time() - 7 * 86400) {
        return 'up to date';
    }
    @touch($fresh); // try again in a week even if this fails
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'LamazonLoads']]);
    $body = @file_get_contents(DISPOSABLE_LIST_URL, false, $ctx);
    $lines = array_filter(array_map('trim', explode("\n", (string) $body)), fn ($l) => $l !== '' && $l[0] !== '#' && preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/i', $l));
    $bad = array_intersect(array_map('strtolower', $lines), ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com', 'proton.me', 'aol.com']);
    if (count($lines) < 1000 || $bad) {
        return 'kept the old list';
    }
    file_put_contents($fresh . '.tmp', implode("\n", $lines) . "\n");
    rename($fresh . '.tmp', $fresh);
    return 'updated, ' . count($lines) . ' domains';
}
