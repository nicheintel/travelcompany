<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$u = user_by_reset_token($token);
$errors = [];

if ($u && is_post()) {
    csrf_check();
    $new = (string) ($_POST['password'] ?? '');
    $again = (string) ($_POST['password2'] ?? '');
    if (strlen($new) < 8 || strlen($new) > 200) {
        $errors[] = 'Your new password needs at least 8 characters.';
    } elseif (weak_password($new, (string) $u['email'], (string) $u['name'])) {
        $errors[] = 'That password is too easy to guess. Please choose a stronger one.';
    } elseif (!hash_equals($new, $again)) {
        $errors[] = 'The two passwords don’t match. Please type them again.';
    }
    if (!$errors) {
        // New password; the link stops working; every other signed-in device is signed out.
        // Opening the link proves they own the email, so it counts as confirmed too.
        db_run('UPDATE users SET password_hash = ?, session_version = session_version + 1, reset_token = NULL, reset_expires = NULL, must_change_password = 0,
            email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        db_run('DELETE FROM login_attempts WHERE email = ?', [$u['email']]);
        unset($_SESSION['reset_email']);
        $fresh = db_one('SELECT * FROM users WHERE id = ?', [$u['id']]);
        login_user($fresh);
        $first = trim((string) strtok((string) $u['name'], ' ')) ?: 'there';
        [$text, $html] = email_body('Your password was changed', [
            "Hi $first,",
            'The password for your LamazonLoads account was just changed, and you were signed out on your other devices.',
        ], null, null, 'If you didn’t do this, reply to this email or call us right away so we can secure your account.');
        send_mail((string) $u['email'], 'Your LamazonLoads password was changed', $text, $html, support_email());
        flash('success', 'Your password is changed and you’re signed in.');
        redirect('account.php');
    }
}

page_header('Choose a new password', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Almost there.</h2>
    <p>Pick a new password you don't use anywhere else. You'll be signed in right after.</p>
    <p class="quote">Why wait? Let's freight.</p>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <?php if (!$u): ?>
        <div class="ss reset-done">
          <div class="ss-ico is-warn" aria-hidden="true"><?= icon('clock') ?></div>
          <h1 class="ss-title">This link has expired</h1>
          <p class="ss-lead">Reset links work once, for 1 hour. Get a new one and use the newest email.</p>
          <div class="ss-acts">
            <a class="btn btn-primary btn-block" href="<?= e(url('forgot-password.php')) ?>">Send a new link <?= icon('arrow') ?></a>
            <a class="btn btn-ghost btn-block" href="<?= e(url('login.php')) ?>">Back to sign in</a>
          </div>
        </div>
      <?php else: ?>
        <h1>Choose a new password</h1>
        <div class="auth-for"><?= ss_chip((string) $u['email'], 'user') ?></div>
        <?php if ($errors): ?><div class="errors" style="padding-left:16px"><?= e($errors[0]) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('reset-password.php')) ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="t" value="<?= e($token) ?>">
          <input type="email" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
          <label for="password">New password</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" autofocus>
          <p class="hint">At least 8 characters. Avoid easy ones like "password123" or your name.</p>
          <label for="password2" style="margin-top:14px">Type it again</label>
          <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
          <button class="btn btn-primary btn-lg btn-block mt" type="submit">Save new password <?= icon('arrow') ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
