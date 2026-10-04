<?php
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
$errors = [];
$message = null;
if (is_post()) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if ($err = password_problem($password)) $errors['password'] = $err;
    if ($password !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = "Passwords don't match.";
    if (!$errors) {
        $userId = consume_reset_token($token, $password);
        if ($userId && ($user = find_user($userId))) {
            login_user($user);
            flash("Your password has been changed and you've been signed out on other devices.");
            redirect(url('account.php'));
        }
        $message = 'This reset link is invalid or has expired. Please request a new one.';
    }
}
$valid = find_reset_user($token) !== null;
$title = 'Choose a new password';
$noReferrer = true; // the token is in the URL — don't leak it to other sites
require __DIR__ . '/includes/header.php';
if (!$valid && !$message): ?>
  <?= auth_shell_open('This link has expired', 'Password reset links work once and expire after 1 hour.') ?>
  <a href="<?= e(url('forgot-password.php')) ?>" class="block w-full rounded-xl bg-brand-600 py-3 text-center font-semibold text-white hover:bg-brand-700">Send me a new link</a>
<?php else: ?>
  <?= auth_shell_open('Choose a new password', "For your security, you'll be signed out on all other devices.") ?>
  <form method="post" class="space-y-5" novalidate data-pending-form>
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <?= alert_box($message) ?>
    <?php if ($message): ?><a href="<?= e(url('forgot-password.php')) ?>" class="block text-sm font-semibold text-brand-700 hover:underline">Request a new reset link →</a><?php endif; ?>
    <?= text_field('password', 'New password', '', 'password', $errors['password'] ?? null, ['autocomplete' => 'new-password'], 'At least 8 characters, with a letter and a number.') ?>
    <?= text_field('confirm', 'Confirm new password', '', 'password', $errors['confirm'] ?? null, ['autocomplete' => 'new-password']) ?>
    <?= submit_button('Set new password', 'Saving…') ?>
  </form>
<?php endif;
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
