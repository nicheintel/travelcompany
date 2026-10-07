<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('settings.php');
}
$email = strtolower(trim((string) ($_GET['email'] ?? '')));
$error = '';
$sent = isset($_GET['sent']);

if (is_post()) {
    csrf_check();
    $email = strtolower(post('email', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter the email you use to sign in.';
    } elseif (rate_limited('reset', client_ip(), 6, 3600)) {
        $error = 'You asked for several reset links already. Please check your email or wait a while before trying again.';
    } else {
        record_hit('reset', client_ip());
        $u = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        // One email a minute per account. The page says the same thing whether or not the email has an account.
        if ($u && ($u['reset_sent_at'] === null || strtotime((string) $u['reset_sent_at']) < time() - 60)) {
            send_password_reset($u);
        }
        $_SESSION['reset_email'] = $email;
        redirect('forgot-password.php?sent=1');
    }
}
$shown = (string) ($_SESSION['reset_email'] ?? '');

page_header('Reset your password', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Locked out? No problem.</h2>
    <p>We'll email you a secure link to choose a new password. It only takes a minute.</p>
    <p class="quote">Why wait? Let's freight.</p>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <?php if ($sent): ?>
        <div class="ss reset-done">
          <div class="ss-ico" aria-hidden="true"><?= icon('mail') ?></div>
          <h1 class="ss-title">Check your email</h1>
          <p class="ss-lead">If it has an account, a reset link is on its way.</p>
          <?php if ($shown !== ''): ?><?= ss_chip($shown) ?><?php endif; ?>
          <?= ss_steps([
              ['Open our email', 'From ' . mail_from()],
              ['Tap “Choose a new password”', 'The link works for 1 hour'],
              ['Pick a new password', 'You’re signed in right away'],
          ]) ?>
          <?= ss_hint('No email? <a href="' . e(url('forgot-password.php' . ($shown !== '' ? '?email=' . rawurlencode($shown) : ''))) . '">Send it again</a> or check spam.') ?>
          <div class="ss-acts"><a class="btn btn-ghost btn-block" href="<?= e(url('login.php')) ?>">Back to sign in</a></div>
        </div>
      <?php else: ?>
        <h1>Forgot your password?</h1>
        <p class="sub">Enter the email you use to sign in and we'll send you a link to choose a new one.</p>
        <?php if ($error): ?><div class="errors" style="padding-left:16px"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('forgot-password.php')) ?>" novalidate>
          <?= csrf_field() ?>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($email) ?>" autofocus>
          <button class="btn btn-primary btn-lg btn-block mt" type="submit">Send reset link <?= icon('arrow') ?></button>
        </form>
        <p class="hint mt">Remembered it? <a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
