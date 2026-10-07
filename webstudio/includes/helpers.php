<?php
declare(strict_types=1);
defined('WS_APP') || exit;

/** Escape for HTML output. Use for every value printed into a page. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the site, e.g. "/webstudio" under XAMPP, "" at a domain root. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    // Worked out from the page being run, so links work in any folder.
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $script = str_replace('\\', '/', realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '');
    $relative = str_starts_with($script, $appRoot) ? substr($script, strlen($appRoot)) : '';
    $name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($relative !== '' && str_ends_with($name, $relative)) {
        return $base = substr($name, 0, -strlen($relative));
    }
    return $base = rtrim((string) parse_url((string) config('app_url'), PHP_URL_PATH), '/');
}

/** Full address of the site, e.g. "http://localhost/webstudio" (for links people copy). */
function app_url(): string
{
    $configured = rtrim((string) config('app_url'), '/');
    if ($configured !== '') {
        return $configured;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^[a-z0-9.\-\[\]:]+$/', $host)) {
        $host = 'localhost';
    }
    return (request_is_https() ? 'https' : 'http') . '://' . $host . base_path();
}

function request_is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** The visitor's IP address. Forwarded-for headers are ignored because anyone can fake them. */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Visiting from this same computer (XAMPP), not from the internet. */
function is_local_request(): bool
{
    if (PHP_SAPI === 'cli') return false;
    $loopback = ['127.0.0.1', '::1'];
    $host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_REAL_IP', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP'] as $h) {
        if (!empty($_SERVER[$h])) return false; // behind a proxy: treat as the internet
    }
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $loopback, true)
        && in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
}

/** Link to a page in this site: url('admin/request.php', ['id' => 5]). */
function url(string $path = '', array $query = []): string
{
    $q = array_filter($query, fn($v) => $v !== null && $v !== '');
    return base_path() . '/' . ltrim($path, '/') . ($q ? '?' . http_build_query($q) : '');
}

/** Link to a file in assets/, with ?v= so browsers fetch the new copy after a change. */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/assets/' . $path;
    return url('assets/' . $path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Only allow redirects to paths on this site (blocks //evil.com and /\evil.com). */
function safe_next(?string $next, string $fallback): string
{
    if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return $fallback;
    }
    return $next;
}

// ---------- Request input ----------

function param(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : '';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------- CSRF & flash messages ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. Stops forms on other sites from posting here. */
function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Validation ----------

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** http(s) address, or '' when empty. Returns null when it isn't a valid web address. */
function clean_url(string $value): ?string
{
    if ($value === '') return '';
    if (!preg_match('~^https?://~i', $value)) $value = 'https://' . $value;
    if (strlen($value) > 250 || filter_var($value, FILTER_VALIDATE_URL) === false) return null;
    $host = (string) parse_url($value, PHP_URL_HOST);
    return str_contains($host, '.') ? $value : null;
}

function len_between(string $value, int $min, int $max): bool
{
    $len = mb_strlen($value);
    return $len >= $min && $len <= $max;
}

// ---------- Formatting ----------

/** Price with the currency from Settings, e.g. "₱12,999". */
function money(float|int|string|null $amount): string
{
    $amount = (float) $amount;
    $decimals = floor($amount) == $amount ? 0 : 2;
    return setting('currency') . number_format($amount, $decimals);
}

function site_timezone(): DateTimeZone
{
    try {
        return new DateTimeZone(setting('timezone') ?: 'UTC');
    } catch (Exception) {
        return new DateTimeZone('UTC');
    }
}

/** A stored UTC time shown in the site's time zone. */
function fmt_dt(?string $utc, string $format = 'M j, Y · g:i A'): string
{
    if (!$utc) return '';
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(site_timezone())->format($format);
}

/** "just now", "5 min ago", "3 hours ago", "2 days ago", then a date. */
function time_ago(?string $utc): string
{
    if (!$utc) return '';
    $seconds = time() - (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
    if ($seconds < 60) return 'just now';
    if ($seconds < 3600) return intdiv($seconds, 60) . ' min ago';
    if ($seconds < 86400) return plural(intdiv($seconds, 3600), 'hour') . ' ago';
    if ($seconds < 7 * 86400) return plural(intdiv($seconds, 86400), 'day') . ' ago';
    return fmt_dt($utc, 'M j, Y');
}

function plural(int $n, string $word, ?string $pluralWord = null): string
{
    return $n . ' ' . ($n === 1 ? $word : ($pluralWord ?? $word . 's'));
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Text with *starred* words wrapped in <span class="hl"> (already escaped). */
function highlight_stars(string $text): string
{
    return preg_replace('/\*([^*]+)\*/', '<span class="hl">$1</span>', e($text));
}

/** Escaped text with line breaks kept. */
function nl(string $text): string
{
    return nl2br(e($text), false);
}

function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
}

/** Phone number reduced to digits for wa.me / tel: links. */
function phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}
