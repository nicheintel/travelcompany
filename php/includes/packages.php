<?php
declare(strict_types=1);
defined('TC_APP') || exit;

/*
 * Promo packages created by staff on Admin → Packages, and uploaded photos.
 * Prices are fixed by the travel company (per person, USD); nothing here is invented.
 */

const PACKAGE_GRADIENTS = [
    'from-cyan-400 via-sky-500 to-blue-700', 'from-emerald-400 via-teal-500 to-cyan-700',
    'from-rose-400 via-pink-500 to-purple-700', 'from-amber-300 via-orange-400 to-rose-600',
    'from-fuchsia-400 via-rose-500 to-red-600', 'from-teal-300 via-emerald-500 to-green-700',
];

function to_package(array $r): array
{
    $to = airport($r['to_code']);
    $from = airport($r['from_code']);
    return [
        'id' => (int) $r['id'], 'slug' => $r['slug'], 'title' => $r['title'],
        'from_code' => $r['from_code'], 'to_code' => $r['to_code'],
        'from_city' => $from['city'] ?? $r['from_code'], 'destination' => $to['city'] ?? $r['to_code'], 'country' => $to['country'] ?? '',
        'nights' => (int) $r['nights'], 'hotel' => $r['hotel'], 'stars' => $r['stars'] !== null ? (int) $r['stars'] : null,
        'car' => (bool) $r['includes_car'],
        'highlights' => array_values(array_filter(array_map('trim', explode("\n", (string) $r['highlights'])))),
        'price' => (int) $r['price'], 'was_price' => $r['was_price'] !== null ? (int) $r['was_price'] : null,
        'badge' => $r['badge'], 'valid_from' => $r['valid_from'], 'valid_to' => $r['valid_to'],
        'image' => $r['image'], 'active' => (bool) $r['active'], 'sort' => (int) $r['sort'],
        'gradient' => PACKAGE_GRADIENTS[crc32($r['slug']) % count(PACKAGE_GRADIENTS)],
    ];
}

/** Packages customers can book now: switched on and not past their last travel date. */
function active_packages(?string $toCode = null): array
{
    $sql = 'SELECT * FROM packages WHERE active = 1 AND (valid_to IS NULL OR valid_to >= ?)';
    $args = [earliest_date()];
    if ($toCode) {
        $sql .= ' AND to_code = ?';
        $args[] = $toCode;
    }
    return array_map('to_package', db_all($sql . ' ORDER BY sort ASC, id DESC', $args));
}

function all_packages(): array
{
    return array_map('to_package', db_all('SELECT * FROM packages ORDER BY active DESC, sort ASC, id DESC'));
}

function find_package(string $slug, bool $activeOnly = true): ?array
{
    $row = db_one('SELECT * FROM packages WHERE slug = ?' . ($activeOnly ? ' AND active = 1' : ''), [$slug]);
    return $row ? to_package($row) : null;
}

function find_package_by_id(int $id): ?array
{
    $row = db_one('SELECT * FROM packages WHERE id = ?', [$id]);
    return $row ? to_package($row) : null;
}

/** First and last departure date a customer can choose for this package. */
function package_dates(array $p): array
{
    $first = max(earliest_date(), (string) ($p['valid_from'] ?? ''));
    return [$first, $p['valid_to'] ?: add_days(earliest_date(), 330)];
}

/** Unique URL name from the title, e.g. "Bali Villa Escape" -> "bali-villa-escape". */
function package_slug(string $title, int $exceptId = 0): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title)), '-') ?: 'package';
    $base = substr($base, 0, 70);
    $slug = $base;
    for ($n = 2; db_one('SELECT 1 FROM packages WHERE slug = ? AND id <> ?', [$slug, $exceptId]); $n++) {
        $slug = "$base-$n";
    }
    return $slug;
}

// ---------- Photos ----------

const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;

function upload_dir(): string
{
    return dirname(__DIR__) . '/uploads';
}

function upload_url(?string $file): ?string
{
    if (!$file || !preg_match('/^[a-f0-9]{32}\.jpg$/', $file) || !is_file(upload_dir() . '/' . $file)) return null;
    return url('uploads/' . $file);
}

/**
 * Saves an uploaded photo as a new JPEG (max 1600 px wide). Re-saving the picture removes
 * anything hidden in the file, so only a real image ever reaches the uploads folder.
 * Returns [file name, null] or [null, error message].
 */
function save_photo(array $f): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > UPLOAD_MAX_BYTES) return [null, 'That photo is too big — please use one under 8 MB.'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return [null, 'The upload failed. Please try again.'];
    $info = @getimagesize($f['tmp_name']);
    $type = $info[2] ?? 0;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return [null, 'Please upload a JPG, PNG or WebP photo.'];
    if ($info[0] < 400 || $info[1] < 250) return [null, 'That photo is too small — use one at least 800 pixels wide.'];
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $dest = upload_dir() . '/' . $name;
    if (!is_dir(upload_dir())) @mkdir(upload_dir(), 0755, true);

    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if (!$src) return [null, "We couldn't read that photo. Please try another one."];
        [$w, $h] = [imagesx($src), imagesy($src)];
        $nw = min($w, 1600);
        $nh = (int) round($h * $nw / $w);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // transparent PNGs get a white background
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagejpeg($out, $dest, 82);
        imagedestroy($src);
        imagedestroy($out);
        if (!$ok) return [null, "We couldn't save the photo. Check that the uploads folder can be written to."];
    } else {
        // Without the GD extension the photo can't be re-saved, so only plain JPEGs are accepted.
        if ($type !== IMAGETYPE_JPEG) return [null, 'Please upload a JPG photo (or turn on the PHP "gd" extension for PNG/WebP).'];
        if (!move_uploaded_file($f['tmp_name'], $dest)) return [null, "We couldn't save the photo. Check that the uploads folder can be written to."];
    }
    return [$name, null];
}

function delete_photo(?string $file): void
{
    if ($file && preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) @unlink(upload_dir() . '/' . $file);
}

function site_image(string $name): ?string
{
    static $all = null;
    $all ??= array_column(db_all('SELECT name, path FROM site_images'), 'path', 'name');
    return upload_url($all[$name] ?? null);
}

function set_site_image(string $name, ?string $file): void
{
    delete_photo(db_one('SELECT path FROM site_images WHERE name = ?', [$name])['path'] ?? null);
    if ($file === null) {
        db_run('DELETE FROM site_images WHERE name = ?', [$name]);
    } else {
        db_run('INSERT INTO site_images (name, path, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE path = VALUES(path), updated_at = VALUES(updated_at)', [$name, $file, now_utc()]);
    }
}
