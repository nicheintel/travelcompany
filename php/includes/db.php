<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/** Shared PDO connection. Creates the database and tables on first use. */
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
    $pdo->exec("SET time_zone = '+00:00'");
    migrate($pdo);
    return $pdo;
}

function db_unavailable(PDOException $err): never
{
    error_log('[db] ' . $err->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
        . '<h1>Can\'t connect to the database</h1>'
        . (is_local_request()
            ? '<p>Start <strong>MySQL</strong> in the XAMPP Control Panel, then refresh this page.</p>'
              . '<p>If MySQL is running, check the database name, user and password in <code>config.local.php</code>.</p>'
            : '<p>The site is being set up. Please try again later.</p>')
        . '</body>';
    exit;
}

/** Tables are created on first run, so no SQL import is needed. Also see database.sql. */
function migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    // Only run the schema when it changed (marker file in storage/), not on every request.
    $schema = file_get_contents(dirname(__DIR__) . '/database.sql');
    $marker = dirname(__DIR__) . '/storage/schema-' . substr(sha1($schema . config('db_name')), 0, 12);
    if (is_file($marker)) {
        return;
    }
    $pdo->exec($schema);
    // Older databases: the payment reference column used to be Stripe-only.
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('stripe_session_id', $cols, true) && !in_array('payment_ref', $cols, true)) {
        $pdo->exec('ALTER TABLE bookings CHANGE stripe_session_id payment_ref VARCHAR(255) NULL');
    }
    // Email confirmation (added later): accounts that existed before count as confirmed.
    $userCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('email_verified_at', $userCols, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER session_version');
        $pdo->exec('UPDATE users SET email_verified_at = created_at');
    }
    // Ticket details, contact name and checked-bag requests (added later).
    foreach (['ticketed_at' => 'DATETIME NULL', 'supplier_ref' => 'VARCHAR(100) NULL', 'ticket_note' => 'TEXT NULL', 'contact_name' => 'VARCHAR(100) NULL', 'bag_status' => 'VARCHAR(10) NULL'] as $col => $type) {
        if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE bookings ADD COLUMN $col $type");
    }
    @touch($marker);
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

function db_run(string $sql, array $args = []): int
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}
