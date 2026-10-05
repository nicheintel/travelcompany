<?php
declare(strict_types=1);
defined('LL_APP') || exit;

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path of the site folder, e.g. "/lamazonloads" on XAMPP or "" at a domain root. */
function base_path(): string
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $dir = realpath(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) ?: $root;
    $depth = 0;
    while (strlen($dir) > strlen($root) && $depth < 10) {
        $dir = dirname($dir);
        $depth++;
    }
    $p = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    for ($i = 0; $i < $depth; $i++) {
        $p = str_replace('\\', '/', dirname($p));
    }
    return $b = rtrim($p, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/assets/' . $path;
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . $path) . $v;
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_local_request(): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function post(string $key, int $max = 2000): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Every form posts the session token; anything else is refused. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        flash('error', 'Your session expired. Please try again.');
        redirect(ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/') ?: '');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function fmt_date(?string $dt, string $format = 'M j, Y'): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date($format, $t) : '';
}

/** "next" redirect targets must stay on this site. */
function safe_next(string $next): string
{
    $next = ltrim($next, '/');
    return preg_match('#^[a-z0-9_\-/]+(\.php|/)?(\?[A-Za-z0-9_=&%+.\-]*)?$#i', $next) ? $next : 'account.php';
}

const EQUIPMENT = [
    'cargo_van' => 'Cargo Van',
    'sprinter'  => 'Sprinter Van',
    'box_16'    => 'Box Truck (16 ft)',
    'box_26'    => 'Box Truck (20–26 ft)',
    'hotshot'   => 'Hotshot / Pickup + Trailer',
    'semi'      => 'Tractor-Trailer',
    'other'     => 'Other qualified equipment',
];

const ACCOUNT_TYPES = [
    'owner_operator' => 'Owner-operator',
    'driver'         => 'Driver (looking for routes or a truck to drive)',
    'dispatcher'     => 'Dispatcher / support',
    'other'          => 'Entrepreneur / other',
];

const AVAILABILITY = [
    'full_time' => 'Full-time (5+ days a week)',
    'part_time' => 'Part-time',
    'weekdays'  => 'Weekdays only',
    'weekends'  => 'Weekends only',
    'on_call'   => 'On call / as loads come',
];

const DOC_KINDS = [
    'w9'           => 'W-9',
    'insurance'    => 'Certificate of insurance (COI)',
    'license'      => "Driver's license",
    'registration' => 'Vehicle registration',
    'authority'    => 'MC / DOT authority',
    'other'        => 'Other document',
];

const JOB_CATEGORIES = [
    'owner_operator' => 'Owner-Operator',
    'daily_route'    => 'Daily Route',
    'dispatch'       => 'Dispatch Team',
    'support'        => 'Driver Support',
    'other'          => 'Other',
];

const APP_STATUSES = [
    'new'          => 'Received',
    'reviewing'    => 'In review',
    'approved'     => 'Approved',
    'not_selected' => 'Not selected',
];
