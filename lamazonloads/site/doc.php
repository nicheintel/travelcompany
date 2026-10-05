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
if ($doc['mime'] !== 'application/pdf') {
    // (No CSP on PDFs: some browsers refuse to show a PDF under a strict policy.)
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $doc['original_name']) . '"');
readfile($path);
