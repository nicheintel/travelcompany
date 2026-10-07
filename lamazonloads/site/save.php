<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// The heart on job boxes: save or unsave a job (members). Visitors are asked to sign in.
$json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
// Go back to the page the heart was on (only pages on this site)
$ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$rel = ltrim(substr((string) parse_url($ref, PHP_URL_PATH), strlen(base_path())), '/');
$rel = ($rel === '' ? 'index.php' : $rel) . (($q = parse_url($ref, PHP_URL_QUERY)) ? '?' . $q : '');
$back = safe_next($rel) === $rel ? $rel : 'careers.php';
$u = current_user();
if (!$u) {
    flash('info', 'Sign in or create an account to save jobs.');
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['login' => url('login.php?next=' . rawurlencode($back))]);
        exit;
    }
    redirect('login.php?next=' . rawurlencode($back));
}
if (!is_post()) {
    redirect($back);
}
csrf_check();
$jobId = (int) ($_POST['job'] ?? 0);
$exists = $jobId === 0 || db_val("SELECT id FROM jobs WHERE id = ? AND status = 'open'", [$jobId]);
$saved = false;
if ($exists) {
    if (db_val('SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_id = ?', [$u['id'], $jobId])) {
        db_run('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?', [$u['id'], $jobId]);
    } else {
        db_run('INSERT INTO saved_jobs (user_id, job_id, created_at) VALUES (?, ?, NOW())', [$u['id'], $jobId]);
        $saved = true;
    }
}
if ($json) {
    header('Content-Type: application/json');
    echo json_encode(['saved' => $saved]);
    exit;
}
redirect($back);
