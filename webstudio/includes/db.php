<?php
declare(strict_types=1);
defined('WS_APP') || exit;

/** Shared PDO connection. Creates the database and tables on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $name = (string) config('db_name');
    if (str_contains($name . config('db_user') . config('db_pass'), 'CHANGE-ME')) {
        setup_message('Almost there! Add your database details',
            '<p>Open <code>config.local.php</code> (in hPanel: <b>Files → File Manager</b>, in the website folder) and replace the three <code>CHANGE-ME</code> values with your database name, user and password from <b>Databases → MySQL Databases</b>. Save, then refresh this page.</p>');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        setup_message('Check the database name', '<p>The database name in <code>config.local.php</code> can only contain letters, numbers and _ (for example <code>u123456789_webstudio</code>).</p>');
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
    $code = (int) ($err->errorInfo[1] ?? $err->getCode());
    if (is_local_request() || PHP_SAPI === 'cli') {
        setup_message('Can\'t connect to the database',
            '<p>Start <strong>MySQL</strong> in the XAMPP Control Panel, then refresh this page.</p>'
            . '<p>If MySQL is running, check the database name, user and password in <code>config.local.php</code>.</p>');
    }
    // 1044/1045/1698 = wrong user or password, 1049 = no such database: the settings are wrong, not an outage.
    if (in_array($code, [1044, 1045, 1049, 1698], true)) {
        setup_message('The database login didn\'t work',
            '<p>Check <code>db_name</code>, <code>db_user</code> and <code>db_pass</code> in <code>config.local.php</code> against <b>hPanel → Databases → MySQL Databases</b> '
            . '(names look like <code>u123456789_webstudio</code>, and <code>db_host</code> is usually <code>localhost</code>). '
            . 'Forgot the password? You can change it there, then update the file.</p>');
    }
    setup_message('We\'ll be right back', '<p>The website is taking a short break. Please try again in a few minutes.</p>', 503);
}

/** A plain page for setup problems (no database or styles needed). */
function setup_message(string $title, string $html, int $status = 500): never
{
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . htmlspecialchars($title) . '</title><body style="font-family:system-ui,sans-serif;padding:40px 20px;max-width:640px;margin:auto;color:#0b1433;line-height:1.6">'
        . '<h1 style="color:#2350d8">' . htmlspecialchars($title) . '</h1>' . $html . '</body>';
    exit;
}

/** Tables are created on first run, so no SQL import is needed. */
function migrate(PDO $pdo): void
{
    // Only run the schema when it changed (marker file in storage/), not on every request.
    $schema = (string) file_get_contents(dirname(__DIR__) . '/database.sql');
    $marker = dirname(__DIR__) . '/storage/schema-' . substr(sha1($schema . config('db_name')), 0, 12);
    if (is_file($marker)) {
        return;
    }
    $pdo->exec($schema);
    // Sample content (packages, FAQs, design concepts) — added once, so deleting it is permanent.
    $seeded = $pdo->query("SELECT value FROM settings WHERE name = '_seeded'")->fetchColumn();
    if ($seeded === false) {
        require_once __DIR__ . '/seed.php';
        seed_content($pdo);
        $pdo->prepare('INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)')->execute(['_seeded', '1', now_utc()]);
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

function db_value(string $sql, array $args = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($args);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_run(string $sql, array $args = []): int
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
}

function db_insert(string $sql, array $args = []): int
{
    db_run($sql, $args);
    return (int) db()->lastInsertId();
}
