<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// The current form token, so pages left open (or shown again with Back) can refresh theirs before posting.
header('Content-Type: application/json');
header('Cache-Control: no-store, private');
echo json_encode(['csrf' => csrf_token()]);
