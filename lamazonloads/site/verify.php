<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Email confirmation: the link from the email (?t=...), and the "Check your email" page with Resend / fix the email.

// 1. The link from the email
if (isset($_GET['t'])) {
    $raw = (string) $_GET['t'];
    $row = preg_match('/^[a-f0-9]{64}$/', $raw) ? db_one('SELECT * FROM users WHERE verify_token = ?', [hash('sha256', $raw)]) : null;
    if (!$row) {
        $me = current_user();
        if ($me && is_verified($me)) {
            flash('success', 'Your email is already confirmed.');
            redirect('account.php');
        }
        flash('error', 'That confirmation link is not valid any more. Send yourself a new one below.');
        redirect($me ? 'verify.php' : 'login.php?next=verify.php');
    }
    if (strtotime((string) $row['verify_expires']) < time()) {
        flash('error', 'That confirmation link has expired. Send yourself a new one below.');
        redirect(current_user() ? 'verify.php' : 'login.php?next=verify.php');
    }
    db_run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), verify_token = NULL, verify_expires = NULL WHERE id = ?', [$row['id']]);
    $me = current_user();
    if ($me && (int) $me['id'] === (int) $row['id']) {
        $back = (string) ($_SESSION['after_verify'] ?? '');
        unset($_SESSION['after_verify']);
        if ($back !== '') {
            flash('success', 'Thanks, your email is confirmed! You can apply now.');
            redirect(safe_next($back));
        }
        flash('success', 'Thanks, your email is confirmed! Next: tell us about your truck so we can match you.');
        redirect('profile.php');
    }
    flash('success', 'Your email is confirmed. Please sign in.');
    redirect('login.php?next=profile.php');
}

// 2. The "Check your email" page (signed in, not confirmed yet)
$u = require_login();
if (is_verified($u)) {
    redirect('account.php');
}
$errors = [];
if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? 'resend';
    $wait = $u['verify_sent_at'] ? 60 - (time() - (int) strtotime((string) $u['verify_sent_at'])) : 0;
    if ($wait > 0) {
        $errors[] = "We just sent an email. Please wait $wait seconds before sending another one.";
    } elseif (rate_limited('verify', 'u' . $u['id'], 6, 86400)) {
        $errors[] = "You've asked for several emails today. Please check your spam folder, or contact us and we'll confirm your account.";
    } elseif ($action === 'change') {
        $email = strtolower(post('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (($problem = email_signup_problem($email)) !== '') {
            $errors[] = $problem;
        } elseif (db_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
            $errors[] = 'Another account already uses that email.';
        } else {
            db_run('UPDATE users SET email = ? WHERE id = ?', [$email, $u['id']]);
            $u['email'] = $email;
        }
    }
    if (!$errors) {
        record_hit('verify', 'u' . $u['id']);
        if (send_verification($u)) {
            flash('success', 'We sent a new confirmation link to ' . $u['email'] . '.');
        } else {
            flash('error', "We couldn't send the email right now. Please try again in a few minutes, or contact us.");
        }
        redirect('verify.php');
    }
}

page_header('Confirm your email', '', '', 'page-auth');
?>
<section class="section">
  <div class="container narrow">
    <div class="card pad verify-card">
      <div class="verify-ico"><?= icon('mail') ?></div>
      <h1>Check your email</h1>
      <p class="lead">We sent a confirmation link to <b><?= e($u['email']) ?></b>. Click it to activate your account.</p>
      <p class="muted">You need to confirm your email before you can apply for jobs, upload documents or fill in your driver profile. The link works for <?= VERIFY_HOURS ?> hours.</p>
      <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <div class="verify-tips">
        <b>Didn't get it?</b>
        <ul>
          <li>Check your spam or junk folder, and "Promotions" in Gmail.</li>
          <li>It can take a few minutes to arrive.</li>
        </ul>
      </div>
      <form method="post" action="<?= e(url('verify.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <button class="btn btn-accent btn-block" type="submit">Send the email again</button>
      </form>
      <details class="verify-change">
        <summary>Wrong email address? Change it</summary>
        <form method="post" action="<?= e(url('verify.php')) ?>" class="verify-change-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="change">
          <label for="email">Your correct email</label>
          <input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= e(post('email', 190)) ?>">
          <button class="btn btn-primary" type="submit">Save and send the link</button>
        </form>
      </details>
      <p class="hint center mt">Still stuck? Use the Chat button or call <?= e((string) config('contact_phone')) ?> and we'll confirm your account for you.</p>
    </div>
  </div>
</section>
<?php page_footer();
