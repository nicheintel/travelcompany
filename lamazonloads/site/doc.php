<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Opens an uploaded document for its owner or for staff. Files are never served directly.
$u = require_login();
$doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$path = $doc ? __DIR__ . '/uploads/' . basename($doc['stored_name']) : '';
if (!$doc || ((int) $doc['user_id'] !== (int) $u['id'] && !$u['is_admin']) || !is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}
$download = isset($_GET['download']) || $doc['mime'] !== 'application/pdf' && !str_starts_with($doc['mime'], 'image/');
header('Content-Type: ' . $doc['mime']);
header('Content-Length: ' . filesize($path));
header_remove('Content-Security-Policy');
// Only this site's own document viewer (a pop-up) may show the file inside a frame.
header('X-Frame-Options: SAMEORIGIN');
if ($doc['mime'] !== 'application/pdf') {
    // (No strict CSP on PDFs: some browsers refuse to show a PDF under a strict policy.)
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox; frame-ancestors 'self'");
} else {
    header("Content-Security-Policy: frame-ancestors 'self'");
}
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
// The file name keeps the uploader's wording but always ends in the file's real type (never .exe, .hta, .cmd…)
$fname = trim((string) preg_replace(['/[^\w .()\-]+/u', '/\.(pdf|jpe?g|png|docx?)$/i'], ['_', ''], trim((string) pathinfo((string) $doc['original_name'], PATHINFO_FILENAME), ' .')), ' .') ?: 'document';
$fname .= '.' . pathinfo((string) $doc['stored_name'], PATHINFO_EXTENSION);
$ascii = (string) preg_replace('/[^A-Za-z0-9 ._()\-]+/', '_', $fname);
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($fname));
readfile($path);
