<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next($_GET['next'] ?? ($_POST['next'] ?? null), '');
if (current_user()) redirect($next ?: url('account.php'));

$message = null;
$errors = [];
$email = '';
if (is_post()) {
    verify_csrf();
    $email = post('email');
    $password = (string) ($_POST['password'] ?? '');
    if (!valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
    if ($password === '') $errors['password'] = 'Please enter your password.';
    if (!$errors && ip_throttled('signin', 30, 900)) {
        $message = 'Too many sign-in attempts from your network. Please wait 15 minutes and try again.';
    } elseif (!$errors) {
        $row = find_user_by_email($email);
        if ($row) {
            $message = check_password_with_lockout($row, $password, 'Incorrect email or password.');
            if ($message === null) {
                login_user(to_user($row));
                redirect($next ?: url('account.php'));
            }
        } else {
            // Same time and message as a wrong password, so this can't reveal who has an account.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            $message = 'Incorrect email or password.';
        }
    }
}
$title = 'Sign in';
require __DIR__ . '/includes/header.php';
echo auth_shell_open('Welcome back', 'Sign in to manage your trips and unlock member prices.', notice_for($next));
?>
<form method="post" class="space-y-5" novalidate data-pending-form>
  <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
  <?= alert_box($message) ?>
  <?= text_field('email', 'Email', $email, 'email', $errors['email'] ?? null, ['autocomplete' => 'email', 'placeholder' => 'you@example.com']) ?>
  <?= text_field('password', 'Password', '', 'password', $errors['password'] ?? null, ['autocomplete' => 'current-password']) ?>
  <div class="-mt-2 text-right"><a href="<?= e(url('forgot-password.php')) ?>" class="text-sm font-medium text-brand-700 hover:underline">Forgot password?</a></div>
  <?= submit_button('Sign in', 'Signing in…') ?>
  <p class="text-center text-sm text-slate-600">New here? <a href="<?= e(url('register.php', ['next' => $next ?: null])) ?>" class="font-semibold text-brand-700 hover:underline">Create a free account</a></p>
</form>
<?php
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
