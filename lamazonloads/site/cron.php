<?php
declare(strict_types=1);

// The scheduled task: runs the automations (follow-up emails, morning summary, job post clean-up) every 30 minutes.
// Set it up once in hPanel → Advanced → Cron Jobs (see HOSTINGER.md). Without it, the same work runs when someone
// opens a page, so it still happens, only less on time.
// Runs only from the server's command line: opened in a browser, it does nothing.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/includes/bootstrap.php';

// Links in the emails: on the command line PHP doesn't know the website's address, so take it from app_url
$_SERVER['SCRIPT_NAME'] = rtrim((string) parse_url((string) config('app_url'), PHP_URL_PATH), '/') . '/cron.php';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

meta_set('cron_ran', (string) time());
meta_set('automations_ran', (string) time()); // page visits leave it to this task
$done = run_automations_locked();
echo date('Y-m-d H:i:s') . ' ' . match (true) {
    !$done => 'another run is still busy, skipped',
    isset($done['error']) => 'stopped with an error: ' . $done['error'],
    default => json_encode($done),
} . PHP_EOL;
exit(isset($done['error']) ? 1 : 0);
