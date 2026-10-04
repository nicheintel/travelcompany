<?php
declare(strict_types=1);

/** Escape for HTML output. Use for every value printed into a page. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the site, e.g. "/travelcompany" under XAMPP, "" at a domain root. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = rtrim((string) config('app_url'), '/');
    if ($configured !== '') {
        return $base = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
    }
    $appRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $relative = str_starts_with($script, $appRoot) ? substr($script, strlen($appRoot)) : '';
    $name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $relative = str_replace('\\', '/', $relative);
    return $base = ($relative !== '' && str_ends_with($name, $relative)) ? substr($name, 0, -strlen($relative)) : '';
}

/** Absolute site URL for emails and Stripe, e.g. "http://localhost/travelcompany". */
function app_url(): string
{
    $configured = rtrim((string) config('app_url'), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
}

/** Link to a page in this site: url('flights.php', ['to' => 'CDG']). */
function url(string $path = '', array $query = []): string
{
    $q = array_filter($query, fn($v) => $v !== null && $v !== '');
    return base_path() . '/' . ltrim($path, '/') . ($q ? '?' . http_build_query($q) : '');
}

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

function not_found(): never
{
    http_response_code(404);
    $title = 'Page not found';
    require __DIR__ . '/header.php';
    echo '<section class="mx-auto max-w-xl px-4 py-24 text-center"><h1 class="text-3xl font-bold text-slate-900">Page not found</h1>'
        . '<p class="mt-2 text-slate-600">The page you\'re looking for doesn\'t exist.</p>'
        . '<a href="' . e(url()) . '" class="mt-6 inline-block rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white">Back to home</a></section>';
    require __DIR__ . '/footer.php';
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

function current_path_with_query(): string
{
    return (string) ($_SERVER['REQUEST_URI'] ?? url());
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
    return is_string($v) ? trim($v) : '';
}

function int_param(string $key, int $default, int $min, int $max): int
{
    $v = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v === null ? $default : max($min, min($max, $v));
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------- CSRF & flash ----------

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

/** Call at the top of every POST handler. Stops cross-site form submissions. */
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

// ---------- Formatting ----------

function money(float|int $amount): string
{
    return '$' . number_format((float) $amount, 0);
}

function fmt_date(string $iso): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone('UTC'));
    return $d ? $d->format('D, M j') : $iso;
}

function fmt_dob(string $iso): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone('UTC'));
    return $d ? $d->format('M j, Y') : $iso;
}

function fmt_time(int $minutes): string
{
    $m = (($minutes % 1440) + 1440) % 1440;
    $h = intdiv($m, 60);
    return sprintf('%d:%02d %s', $h % 12 === 0 ? 12 : $h % 12, $m % 60, $h >= 12 ? 'PM' : 'AM');
}

function fmt_duration(int $minutes): string
{
    return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
}

/** <time> showing a stored UTC timestamp; app.js converts it to the viewer's local time. */
function local_time(?string $utc, bool $dateOnly = false): string
{
    if (!$utc) {
        return '';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    $text = $dateOnly ? $d->format('M j, Y') : $d->format('M j, Y, g:i A') . ' UTC';
    return '<time datetime="' . e($d->format('c')) . '"' . ($dateOnly ? ' data-date-only' : '') . ' data-local>' . e($text) . '</time>';
}

function add_days(string $iso, int $days): string
{
    return (new DateTimeImmutable($iso, new DateTimeZone('UTC')))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function today(): string
{
    return gmdate('Y-m-d');
}

/** A real YYYY-MM-DD date on or after $min, else null. */
function date_param(string $value, string $min): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        return null;
    }
    return $value >= $min ? $value : null;
}

/** Earliest bookable date: yesterday in UTC, so visitors in any timezone can choose "today". */
function earliest_date(): string
{
    return add_days(today(), -1);
}

function plural(int $n, string $word, ?string $pluralWord = null): string
{
    return $n . ' ' . ($n === 1 ? $word : ($pluralWord ?? $word . 's'));
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
