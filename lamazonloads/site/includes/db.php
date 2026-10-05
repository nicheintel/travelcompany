<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/** Shared PDO connection. Creates the database, tables and starter job posts on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $name = (string) config('db_name');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Invalid db_name in config.');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', config('db_host'), (int) config('db_port'));
    try {
        $pdo = new PDO($dsn . ';dbname=' . $name, (string) config('db_user'), (string) config('db_pass'), $options);
    } catch (PDOException $err) {
        // 1049 = unknown database: create it (XAMPP's root user is allowed to).
        if ((int) ($err->errorInfo[1] ?? 0) !== 1049) {
            db_unavailable($err);
        }
        try {
            $pdo = new PDO($dsn, (string) config('db_user'), (string) config('db_pass'), $options);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
        } catch (PDOException $err2) {
            db_unavailable($err2);
        }
    }
    migrate($pdo);
    return $pdo;
}

function db_unavailable(PDOException $err): never
{
    error_log('[db] ' . $err->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Database not running</title>'
        . '<body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
        . '<h1>Can\'t connect to the database</h1>'
        . (is_local_request()
            ? '<p>Start <strong>MySQL</strong> in the XAMPP Control Panel, then refresh this page.</p>'
              . '<p>If MySQL is running, check the database settings in <code>config.local.php</code>.</p>'
            : (str_starts_with((string) config('db_name'), 'PUT_')
                ? '<p>Almost there: open <code>config.local.php</code> in Hostinger\'s File Manager and fill in your database name, user and password.</p>'
                : '<p>The site is being set up. Please try again in a few minutes.</p>'))
        . '</body>';
    exit;
}

/** Runs database.sql when it changed (no manual import needed), then adds the starter job posts once. */
function migrate(PDO $pdo): void
{
    $schema = (string) file_get_contents(dirname(__DIR__) . '/database.sql');
    $marker = dirname(__DIR__) . '/storage/schema-' . substr(sha1($schema . config('db_name')), 0, 12);
    if (is_file($marker)) {
        return;
    }
    $pdo->exec($schema);
    $seeded = $pdo->query("SELECT v FROM meta WHERE k = 'seeded'")->fetchColumn();
    if (!$seeded) {
        seed_jobs($pdo);
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('seeded', '1')");
    }
    @touch($marker);
}

/** Starter job posts so the Careers page isn't empty. Edit or close them in Admin → Job posts. */
function seed_jobs(PDO $pdo): void
{
    $jobs = [
        ['Box Truck Owner-Operators: Freight Dispatch', 'owner_operator', 'Nationwide (USA)', 'Box Truck (16–26 ft)', 'Paid per load, rate confirmation before you roll', 'Flexible: you choose your lanes and home time',
         'We search, negotiate and book loads for your box truck so you can spend your time driving, not refreshing load boards.',
         "Our dispatch team works the load boards and broker relationships for you. We negotiate rates, handle the paperwork, send you pickup and drop-off details, and follow up on payment so you stay loaded and profitable.\n\nYou keep control: we discuss lanes, home time and minimum rates with you before booking.",
         "Box truck (16 to 26 ft) in good working condition\nActive MC / DOT authority, or willing to lease on\nCommercial auto and cargo insurance\nW-9\nSmartphone for updates and rate confirmations"],
        ['Cargo Van & Sprinter Drivers: Expedited Loads', 'owner_operator', 'Nationwide (USA)', 'Cargo Van / Sprinter Van', 'Paid per load', 'Full-time or part-time',
         'Expedited and last-mile freight for cargo vans and Sprinter vans. We find the loads, you keep the wheels turning.',
         "Hot-shot style expedited freight, same-day runs and multi-stop deliveries for van operators. We book, coordinate pickup and drop-off, and keep in touch on the road.",
         "Cargo van or Sprinter van (high roof preferred)\nValid driver's license and clean driving record\nInsurance that covers hauling freight\nW-9\nSmartphone with GPS"],
        ['Local Daily Route Driver (Dedicated Contract)', 'daily_route', 'Depends on contract location', 'Cargo Van / Sprinter / Box Truck', 'Per route, shared when a contract opens', 'Daily routes, set schedule',
         'Dedicated and local delivery routes when contracts are available. Apply now and we will match you by ZIP code and equipment.',
         "When a dedicated or local delivery contract opens, we fill it from drivers who already completed onboarding. Applying now puts you first in line in your area.\n\nTell us your home ZIP code, equipment and availability in your profile so we can match you quickly.",
         "Reliable vehicle that fits the route (van or box truck)\nAvailable on the route's schedule\nValid driver's license and insurance\nW-9 and onboarding documents on file"],
        ['Freight Dispatcher (Remote)', 'dispatch', 'Remote', 'Not applicable', 'Discussed in the interview', 'Full-time or part-time',
         'Join the LamazonLoads dispatch team: search, negotiate and book freight for our drivers, and keep them loaded.',
         "You will work load boards, negotiate with brokers, build lanes for our drivers and coordinate pickup, delivery and paperwork. Drivers come first here: you are their advocate.",
         "Dispatch or logistics experience (box truck / van freight is a plus)\nStrong negotiation and communication skills\nComfortable with load boards and rate confirmations\nReliable internet and phone"],
    ];
    $st = $pdo->prepare('INSERT INTO jobs (title, category, location, equipment, pay, schedule, summary, description, requirements, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,\'open\',NOW(),NOW())');
    foreach ($jobs as $j) {
        $st->execute($j);
    }
}

function db_one(string $sql, array $args = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($args);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $args = []): array
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function db_val(string $sql, array $args = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

function db_run(string $sql, array $args = []): int
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
}
