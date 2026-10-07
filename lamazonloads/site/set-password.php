<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// First sign-in with a password staff generated (Add member, or a staff password reset): the member picks their own.
$u = require_login();
$next = safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? 'account.php'));
if (empty($u['must_change_password'])) {
    redirect($next);
}
$errors = [];

if (is_post()) {
    csrf_check();
    $new = (string) ($_POST['password'] ?? '');
    $again = (string) ($_POST['password2'] ?? '');
    if (strlen($new) < 8) {
        $errors[] = 'Your new password needs at least 8 characters.';
    } elseif (($pp = password_format_problem($new)) !== '') {
        $errors[] = $pp;
    } elseif (weak_password($new, (string) $u['email'], (string) $u['name'])) {
        $errors[] = 'That password is too easy to guess. Please choose a stronger one.';
    } elseif (password_verify($new, (string) $u['password_hash'])) {
        $errors[] = 'Please choose a new password, not the one from the email.';
    } elseif (!hash_equals($new, $again)) {
        $errors[] = 'The two passwords don’t match. Please type them again.';
    }
    if (!$errors) {
        db_run('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1, reset_token = NULL, reset_expires = NULL WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        login_user(db_one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        flash('success', 'Your password is set. Welcome to LamazonLoads!');
        redirect($next);
    }
}

$first = trim((string) strtok((string) $u['name'], ' ')) ?: 'driver';
page_header('Choose your password', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Welcome aboard, <?= e($first) ?>.</h2>
    <p>Your account is ready. Pick a password only you know, and you're in.</p>
    <p class="quote">Why wait? Let's freight.</p>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Choose your password</h1>
      <p class="sub sub-tight">The password in your welcome email works only once. Choose your own to continue.</p>
      <div class="auth-for"><?= ss_chip((string) $u['email'], 'user') ?></div>
      <?php if ($errors): ?><div class="errors" style="padding-left:16px"><?= e($errors[0]) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('set-password.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <input type="email" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" autofocus>
        <p class="hint">At least 8 characters. Avoid easy ones like "password123" or your name.</p>
        <label for="password2" style="margin-top:14px">Type it again</label>
        <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
        <button class="btn btn-primary btn-lg btn-block mt" type="submit">Save my password <?= icon('arrow') ?></button>
      </form>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="mt" style="text-align:center"><?= csrf_field() ?><button class="link-btn" type="submit">Not you? Sign out</button></form>
    </div>
  </div>
</div>
<?php page_footer();
