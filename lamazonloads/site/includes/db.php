<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/** Shared PDO connection. Creates the tables and starter job posts on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $name = (string) config('db_name');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name) || str_starts_with($name, 'PUT_')) {
        setup_needed();
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', config('db_host'), (int) config('db_port'), $name);
    try {
        $pdo = new PDO($dsn, (string) config('db_user'), (string) config('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $err) {
        error_log('[db] ' . $err->getMessage());
        setup_needed();
    }
    // Same clock as PHP (America/New_York, with daylight saving), so NOW() and date() always agree
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    migrate($pdo);
    return $pdo;
}

/** Shown until config.local.php has working database details. */
function setup_needed(): never
{
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>LamazonLoads</title>'
        . '<body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
        . '<h1>Can\'t connect to the database</h1>'
        . '<p>Open <code>config.local.php</code> (Hostinger File Manager, in <code>public_html</code>) and check the database name, user and password from hPanel &rarr; Databases.</p>'
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
    // Columns added after the first version (CREATE TABLE IF NOT EXISTS doesn't add them to existing tables)
    add_missing_columns($pdo, 'jobs', [
        'hires_needed' => "VARCHAR(10) NOT NULL DEFAULT '1'", 'country' => "VARCHAR(40) NOT NULL DEFAULT 'United States'",
        'language' => "VARCHAR(20) NOT NULL DEFAULT 'English'", 'job_types' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'apply_method' => "VARCHAR(10) NOT NULL DEFAULT 'site'", 'apply_url' => "VARCHAR(300) NOT NULL DEFAULT ''",
        'resume' => "VARCHAR(10) NOT NULL DEFAULT 'optional'", 'notify_on' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'notify_emails' => "VARCHAR(300) NOT NULL DEFAULT ''", 'contact_by_email' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'contact_email' => "VARCHAR(190) NOT NULL DEFAULT ''", 'fair_chance' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'background_check' => 'TINYINT(1) NOT NULL DEFAULT 0', 'hiring_timeline' => "VARCHAR(10) NOT NULL DEFAULT ''",
        'auto_welcome' => 'TINYINT(1) NOT NULL DEFAULT 0', 'welcome_message' => 'TEXT NULL',
        'auto_review' => 'TINYINT(1) NOT NULL DEFAULT 0', 'auto_remind' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'remind_days' => 'TINYINT UNSIGNED NOT NULL DEFAULT 2', 'auto_decline' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'decline_days' => 'TINYINT UNSIGNED NOT NULL DEFAULT 5', 'auto_close' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ]);
    // Room for ".docx" names and Word's long file type
    $pdo->exec('ALTER TABLE documents MODIFY stored_name VARCHAR(40) NOT NULL, MODIFY mime VARCHAR(100) NOT NULL');
    // Email confirmation (added later): accounts that already existed count as confirmed
    $userCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('email_verified_at', $userCols, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER session_version');
        $pdo->exec('UPDATE users SET email_verified_at = created_at');
    }
    add_missing_columns($pdo, 'users', ['verify_token' => 'CHAR(64) NULL', 'verify_expires' => 'DATETIME NULL', 'verify_sent_at' => 'DATETIME NULL',
        // Forgot password: one-time link (only a hash of it is stored)
        'reset_token' => 'CHAR(64) NULL', 'reset_expires' => 'DATETIME NULL', 'reset_sent_at' => 'DATETIME NULL',
        // Members added by staff: who added them, and a generated password they must replace at first sign-in
        'must_change_password' => 'TINYINT(1) NOT NULL DEFAULT 0', 'added_by' => 'INT UNSIGNED NULL',
        // Chosen at sign-up: city ("Atlanta, GA", from the US city list) and vehicle types (comma-separated, plus a typed "Other")
        'city' => "VARCHAR(120) NOT NULL DEFAULT ''", 'vehicle' => "VARCHAR(120) NOT NULL DEFAULT ''", 'vehicle_other' => "VARCHAR(80) NOT NULL DEFAULT ''"]);
    $pdo->exec("ALTER TABLE users MODIFY vehicle VARCHAR(120) NOT NULL DEFAULT ''"); // was one type (20 characters)
    add_missing_columns($pdo, 'documents', ['added_by' => 'INT UNSIGNED NULL']); // staff member who added it for the driver
    // Payment details drivers add during onboarding (Zelle preferred)
    add_missing_columns($pdo, 'driver_profiles', ['payout_method' => "VARCHAR(20) NOT NULL DEFAULT ''", 'payout_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'payout_handle' => "VARCHAR(190) NOT NULL DEFAULT ''", 'payout_updated_at' => 'DATETIME NULL']);
    // Onboarding opens from the link in the email: each driver gets a personal link; drivers who already started stay unlocked
    $onbCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onboarding'")->fetchAll(PDO::FETCH_COLUMN);
    add_missing_columns($pdo, 'onboarding', ['access_token' => 'CHAR(32) NULL', 'opened_at' => 'DATETIME NULL',
        'marked_at' => 'DATETIME NULL', 'marked_by' => 'INT UNSIGNED NULL']); // marked as onboarded by staff (onboarded outside the website)
    if ($onbCols && !in_array('opened_at', $onbCols, true)) {
        $pdo->exec("UPDATE onboarding o SET o.opened_at = NOW() WHERE o.stage <> 'documents'
            OR EXISTS (SELECT 1 FROM documents d WHERE d.user_id = o.user_id AND d.kind IN ('vehicle_photo', 'w9', 'insurance', 'license'))
            OR EXISTS (SELECT 1 FROM driver_profiles p WHERE p.user_id = o.user_id AND p.payout_method <> '')");
    }
    // Drivers who already got the Dispatch or Walmart email before website onboarding existed start at "upload documents"
    $pdo->exec("INSERT IGNORE INTO onboarding (user_id, track, stage, created_at, updated_at)
        SELECT user_id, IF(SUM(email_sent = 'dispatch') > 0, 'dispatch', 'walmart'), 'documents', MIN(COALESCE(email_sent_at, created_at)), NOW()
        FROM applications WHERE email_sent IN ('dispatch', 'walmart') GROUP BY user_id");
    add_missing_columns($pdo, 'applications', [
        'resume_doc_id' => 'INT UNSIGNED NULL', 'reminded_at' => 'DATETIME NULL', 'auto_note' => "VARCHAR(255) NOT NULL DEFAULT ''",
        // Driver application form (vehicles, Walmart daily route) and the onboarding email that went out
        'first_name' => "VARCHAR(60) NOT NULL DEFAULT ''", 'last_name' => "VARCHAR(60) NOT NULL DEFAULT ''",
        'phone' => "VARCHAR(30) NOT NULL DEFAULT ''", 'location' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'vehicles' => "VARCHAR(120) NOT NULL DEFAULT ''", 'vehicle_other' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'ownership' => "VARCHAR(20) NOT NULL DEFAULT ''", 'ownership_other' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'walmart' => 'TINYINT(1) NOT NULL DEFAULT 0', 'walmart_city' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'rate_requested' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'email_sent' => "VARCHAR(20) NOT NULL DEFAULT ''", 'email_sent_at' => 'DATETIME NULL',
    ]);
    // Walmart daily route program: starting cities
    if (!$pdo->query("SELECT v FROM meta WHERE k = 'walmart_seeded'")->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO walmart_routes (city, active, created_at) VALUES (?, 1, NOW())');
        foreach (['Apopka, FL', 'Oldsmar, FL', 'Novi, MI', 'California, MD', 'Huntersville, NC', 'Marietta, OH', 'Huntington, WV',
            'Kendall, FL', 'Fort Wayne, IN', 'Jacksonville, NC', 'Grand Rapids, MI', 'Morgantown, WV'] as $city) {
            $st->execute([$city]);
        }
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('walmart_seeded', '1'), ('walmart_start', 'November'), ('walmart_rate', '$275 per day'), ('walmart_on', '1')
            ON DUPLICATE KEY UPDATE v = v");
    }
    $seeded = $pdo->query("SELECT v FROM meta WHERE k = 'seeded'")->fetchColumn();
    if (!$seeded) {
        seed_jobs($pdo);
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('seeded', '1')");
    }
    @touch($marker);
}

function add_missing_columns(PDO $pdo, string $table, array $columns): void
{
    $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    $have = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $col => $def) {
        if (!in_array($col, $have, true)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        }
    }
}

/** Starter job posts so the Careers page isn't empty. Edit or close them in Admin → Job posts. */
function seed_jobs(PDO $pdo): void
{
    // title, category, location, equipment, pay, job types, hires, timeline, description, requirements
    $jobs = [
        ['Box Truck Owner-Operators: Freight Dispatch', 'owner_operator', 'Nationwide (USA)', 'Box Truck (16–26 ft)', 'Paid per load, rate confirmation before you roll', 'full_time,contract', 'ongoing', '1-2w',
         "We search, negotiate and book loads for your box truck so you can spend your time driving, not refreshing load boards.\n\nOur dispatch team works the load boards and broker relationships for you. We negotiate rates, handle the paperwork, send you pickup and drop-off details, and follow up on payment so you stay loaded and profitable.\n\nYou keep control: we discuss lanes, home time and minimum rates with you before booking.",
         "Box truck (16 to 26 ft) in good working condition\nActive MC / DOT authority, or willing to lease on\nCommercial auto and cargo insurance\nW-9\nSmartphone for updates and rate confirmations"],
        ['Cargo Van & Sprinter Drivers: Expedited Loads', 'light_truck', 'Nationwide (USA)', 'Cargo Van / Sprinter Van', 'Paid per load', 'full_time,part_time,contract', 'ongoing', '1-2w',
         "Expedited and last-mile freight for cargo vans and Sprinter vans. We find the loads, you keep the wheels turning.\n\nHot-shot style expedited freight, same-day runs and multi-stop deliveries for van operators. We book, coordinate pickup and drop-off, and keep in touch on the road.",
         "Cargo van or Sprinter van (high roof preferred)\nValid driver's license and clean driving record\nInsurance that covers hauling freight\nW-9\nSmartphone with GPS"],
        ['Local Daily Route Driver (Dedicated Contract)', 'light_truck', 'Depends on contract location', 'Cargo Van / Sprinter / Box Truck', 'Per route, shared when a contract opens', 'full_time,part_time,contract', '5', '2-4w',
         "Dedicated and local delivery routes when contracts are available. Apply now and we will match you by ZIP code and equipment.\n\nWhen a dedicated or local delivery contract opens, we fill it from drivers who already completed onboarding. Applying now puts you first in line in your area.\n\nTell us your home ZIP code, equipment and availability in your profile so we can match you quickly.",
         "Reliable vehicle that fits the route (van or box truck)\nAvailable on the route's schedule\nValid driver's license and insurance\nW-9 and onboarding documents on file"],
        ['Freight Dispatcher (Remote)', 'dispatch', 'Remote', 'Not applicable', 'Discussed in the interview', 'full_time,part_time', '2', '2-4w',
         "Join the LamazonLoads dispatch team: search, negotiate and book freight for our drivers, and keep them loaded.\n\nYou will work load boards, negotiate with brokers, build lanes for our drivers and coordinate pickup, delivery and paperwork. Drivers come first here: you are their advocate.",
         "Dispatch or logistics experience (box truck / van freight is a plus)\nStrong negotiation and communication skills\nComfortable with load boards and rate confirmations\nReliable internet and phone"],
    ];
    $st = $pdo->prepare("INSERT INTO jobs (title, category, location, equipment, pay, job_types, hires_needed, hiring_timeline, description, requirements, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'open',NOW(),NOW())");
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
