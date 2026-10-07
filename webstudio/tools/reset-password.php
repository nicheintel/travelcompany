<?php
/**
 * Locked out? Sets a new password for an admin from the command line (never from the web).
 *
 *   XAMPP on Windows:  C:\xampp\php\php.exe C:\xampp\htdocs\webstudio\tools\reset-password.php you@example.com NewPassword123
 *   Mac / Linux:       php tools/reset-password.php you@example.com NewPassword123
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

[$script, $email, $password] = $argv + [null, '', ''];
if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/reset-password.php EMAIL NEW-PASSWORD\n");
    exit(1);
}
if ($problem = password_problem($password)) {
    fwrite(STDERR, $problem . "\n");
    exit(1);
}
$changed = db_run('UPDATE admins SET password_hash = ?, session_version = session_version + 1 WHERE email = ?', [password_hash($password, PASSWORD_DEFAULT), normalize_email($email)]);
if (!$changed) {
    $emails = array_column(db_all('SELECT email FROM admins ORDER BY id'), 'email');
    fwrite(STDERR, "No admin with that email. Admins: " . ($emails ? implode(', ', $emails) : '(none yet — open /admin/ to create one)') . "\n");
    exit(1);
}
rate_clear('signin:' . normalize_email($email));
echo "Done. Sign in with the new password.\n";
