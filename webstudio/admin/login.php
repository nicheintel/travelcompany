<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!has_admins()) {
    redirect(url('admin/setup.php'));
}
$next = safe_next(param('next') ?: post('next'), url('admin/'));
if (current_admin()) {
    redirect($next);
}

$email = '';
if (is_post()) {
    verify_csrf();
    $email = normalize_email(post('email'));
    if (ip_throttled('login', 30, 900)) {
        flash('Too many sign-in attempts from your connection. Please wait 15 minutes.', 'error');
    } else {
        $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
        if (is_array($result)) {
            login_admin($result);
            flash('Welcome back, ' . first_name($result['name']) . '!');
            redirect($next);
        }
        flash($result, 'error');
    }
}

auth_start('Sign in', 'Welcome back', 'Sign in to manage your website and requests.');
?>
<form method="post" class="auth-fields" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>" autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <div class="pw-wrap">
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye') ?></button>
    </div>
  </div>
  <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in <?= icon('arrow-right') ?></button>
  <p class="auth-note"><?= icon('lock') ?> Forgot your password? Another admin can set a new one for you on Team &amp; password, or see the README.</p>
</form>
<?php auth_end();
