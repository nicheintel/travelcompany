<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? 'account.php'));
if (current_user()) {
    redirect($next);
}
$error = '';
$email = strtolower(substr(trim((string) ($_GET['email'] ?? '')), 0, 190));

if (is_post()) {
    csrf_check();
    $email = strtolower(post('email', 190));
    $pass = (string) ($_POST['password'] ?? '');
    if (login_locked($email)) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && password_verify($pass, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user($user);
            redirect(!empty($user['must_change_password']) ? 'set-password.php?next=' . rawurlencode($next) : $next);
        }
        if (!$user) {
            password_verify($pass, '$2y$10$kqYpP4Np/p9Z.kb4cGH3WOaFyA1zfZr26Qe3UXuJ0Uf844QpE7hZW'); // same delay as a real check (a random password nobody knows)
        }
        record_failed_login($email);
        $error = 'That email and password don\'t match. Please try again.';
    }
}

page_header('Sign in', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Welcome back, driver.</h2>
    <p>Check your applications, update your availability and keep your documents current, so we can keep you loaded.</p>
    <p class="quote">Why wait? Let's freight.</p>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Sign in</h1>
      <p class="sub">New to LamazonLoads? <a href="<?= e(url('register.php?next=' . rawurlencode($next))) ?>">Create an account</a></p>
      <?php if ($error): ?><div class="errors" style="padding-left:16px"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($email) ?>"<?= $email === '' ? ' autofocus' : '' ?>>
        <label for="password" style="margin-top:16px">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"<?= $email !== '' ? ' autofocus' : '' ?>>
        <p class="hint forgot-link"><a href="<?= e(url('forgot-password.php' . ($email !== '' ? '?email=' . rawurlencode($email) : ''))) ?>">Forgot your password?</a></p>
        <button class="btn btn-primary btn-lg btn-block mt" type="submit">Sign in <?= icon('arrow') ?></button>
      </form>
    </div>
  </div>
</div>
<?php page_footer();
