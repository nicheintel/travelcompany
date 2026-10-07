<?php
/** All requests as a CSV file (opens in Excel or Google Sheets). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

/** Spreadsheet apps run cells that start with = + - @ as formulas; keep them as plain text. */
function csv_cell(mixed $v): string
{
    $v = (string) $v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

$rows = db_all('SELECT * FROM requests ORDER BY created_at DESC');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="website-requests-' . gmdate('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // so Excel reads accents and ₱ correctly
fputcsv($out, ['Reference', 'Received', 'Status', 'Name', 'Email', 'Phone', 'Business', 'Type of business', 'Package', 'Budget', 'Timeline', 'Current website', 'Quote', 'Message'], ',', '"', '');
foreach ($rows as $r) {
    fputcsv($out, array_map('csv_cell', [
        $r['ref'], fmt_dt($r['created_at'], 'Y-m-d H:i'), status_label($r['status']), $r['name'], $r['email'], $r['phone'],
        $r['business_name'], $r['business_type'], $r['package_name'], $r['budget'], $r['timeline'], $r['website_url'],
        $r['quoted_amount'] ?? '', $r['message'],
    ]), ',', '"', '');
}
fclose($out);
