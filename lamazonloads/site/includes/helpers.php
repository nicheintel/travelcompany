<?php
declare(strict_types=1);
defined('LL_APP') || exit;

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path of the site folder: "" at the domain root, or e.g. "/lamazonloads" in a subfolder. */
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

/** Sends the visitor to a page on this site (never to another website). */
function redirect(string $path): never
{
    header('Location: ' . url(ltrim(str_replace(["\r", "\n", '\\'], '', $path), '/')));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Runs $fn after the page has been sent, so slow work (like sending an email) doesn't change how long the page takes. */
function after_response(callable $fn): void
{
    register_shutdown_function(function () use ($fn): void {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
        $fn();
    });
}

/** The key for rate limits: the visitor's address, or their whole IPv6 /64 network (one home or phone gets a whole /64). */
function limit_ip(): string
{
    $ip = client_ip();
    if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false && strlen($bin) === 16) {
        return (string) inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
    return $ip;
}

/** A plain email address (letters, digits and . _ + - before the @), stricter than PHP's own check, so nothing
 *  unusual (quotes, ?bcc=, extra addresses) can end up in "Reply by email" links or email headers. */
function email_valid(string $e): bool
{
    return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[A-Za-z0-9._+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/', $e) === 1;
}

/** The first name for greetings ("Hi Maria,"). Only plain names are used, so nobody can put a link or a message
 *  into an email we send; anything else gets the fallback. */
function first_name(string $name, string $fallback = 'there'): string
{
    $f = trim((string) strtok(trim($name), ' '));
    return preg_match("/^\p{L}[\p{L}'’\-]{0,29}$/u", $f) ? $f : $fallback;
}

/** A request value as text ('' when someone sends a list like ?t[]=1 instead of a single value). */
function as_str(mixed $v): string
{
    return is_scalar($v) ? (string) $v : '';
}

/** A one-line form field (names, companies…): line breaks and control characters become plain spaces. */
function post_line(string $key, int $max = 190): string
{
    return trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', post($key, $max)));
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
        flash('error', 'For your security, this page needed a quick refresh. Please try again.');
        redirect(ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/') ?: '');
    }
}

/** A message shown at the top of the next page. $keep: stays until closed (e.g. it shows a password to copy). */
function flash(string $type, string $msg, bool $keep = false): void
{
    $_SESSION['flash'][] = [$type, $msg, $keep];
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

/** US numbers as (555) 123-4567 (a leading 1 / +1 is dropped); anything else is kept as typed. */
function format_phone(string $phone): string
{
    $phone = trim($phone);
    $d = (string) preg_replace('/\D+/', '', $phone);
    if (strlen($d) === 11 && $d[0] === '1') {
        $d = substr($d, 1);
    }
    if (strlen($d) === 10 && !(str_starts_with($phone, '+') && !str_starts_with($phone, '+1'))) {
        return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6);
    }
    return $phone;
}

const ACCOUNT_TYPES = [
    'owner_operator' => 'Owner-operator',
    'driver'         => 'Driver (looking for routes or a truck to drive)',
    'dispatcher'     => 'Dispatcher / support',
    'recruiter'      => 'Driver recruiter',
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
    'resume'       => 'Resume',
    'w9'           => 'W-9',
    'insurance'    => 'Certificate of insurance (COI)',
    'license'      => "Driver's license",
    'vehicle_photo' => 'Vehicle photos',
    'registration' => 'Vehicle registration',
    'authority'    => 'MC / DOT authority',
    'other'        => 'Other document',
];

const JOB_CATEGORIES = [
    'light_truck'    => 'Light Truck & Delivery Drivers (Transportation)',
    'owner_operator' => 'Owner-Operator',
    'daily_route'    => 'Daily Route',
    'dispatch'       => 'Dispatch Team',
    'support'        => 'Driver Support',
    'other'          => 'Other',
];

const JOB_TYPES = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'temporary' => 'Temporary', 'seasonal' => 'Seasonal'];

// "Number of people to hire in the next 30 days"
const HIRE_COUNTS = ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
    '10+' => 'More than 10', 'ongoing' => 'I have an ongoing need to fill this role'];

const HIRING_TIMELINES = ['1-3d' => '1 to 3 days', '3-7d' => '3 to 7 days', '1-2w' => '1 to 2 weeks', '2-4w' => '2 to 4 weeks', '4w+' => 'More than 4 weeks'];

const RESUME_OPTIONS = ['required' => 'Yes, require a resume', 'optional' => 'Optional (candidates can add one)', 'no' => "No, don't ask for a resume"];

const APP_STATUSES = [
    'new'          => 'Received',
    'reviewing'    => 'In review',
    'approved'     => 'Approved',
    'not_selected' => 'Not selected',
];
