<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Email confirmation: the link from the email (?t=...), and the "Check your email" page with Resend / fix the email.

// 1. The link from the email
if (isset($_GET['t'])) {
    $raw = (string) $_GET['t'];
    $row = preg_match('/^[a-f0-9]{64}$/', $raw) ? db_one('SELECT *, verify_expires < NOW() AS expired FROM users WHERE verify_token = ?', [hash('sha256', $raw)]) : null;
    if (!$row) {
        $me = current_user();
        if ($me && is_verified($me)) {
            flash('success', 'Your email is already confirmed.');
            redirect('account.php');
        }
        flash('error', 'That confirmation link is not valid any more. Send yourself a new one below.');
        redirect($me ? 'verify.php' : 'login.php?next=verify.php');
    }
    if ((int) $row['expired']) { // compared in the database, so time zones can't get mixed up
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
    $wait = 60 - (int) db_val('SELECT COALESCE(TIMESTAMPDIFF(SECOND, verify_sent_at, NOW()), 999) FROM users WHERE id = ?', [$u['id']]);
    if ($wait > 0) {
        $errors[] = "We just sent an email. Please wait $wait seconds before sending another one.";
    } elseif (rate_limited('verify', 'u' . $u['id'], 6, 86400)) {
        $errors[] = "You've asked for several emails today. Call us or tap Chat, and we'll confirm your account for you.";
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

$inbox = webmail_link((string) $u['email']); // "Open Gmail"-style button for the big email providers
$changeOpen = ($_POST['action'] ?? '') === 'change' && $errors;
$phone = (string) config('contact_phone');

page_header('Confirm your email', '', '', 'page-auth');
?>
<section class="section verify-section">
  <div class="container">
    <ol class="steps-bar" aria-label="Sign-up steps">
      <li class="done"><span><?= icon('check') ?></span>Account created</li>
      <li class="current" aria-current="step"><span>2</span>Confirm email</li>
      <li><span>3</span>Complete profile</li>
    </ol>

    <div class="card verify-card">
      <div class="ss verify-ss">
        <div class="ss-ico" aria-hidden="true"><?= icon('mail') ?></div>
        <h1 class="ss-title">Confirm your email</h1>
        <p class="ss-lead">You’re almost in. Check your inbox for our link.</p>
        <?= ss_chip((string) $u['email']) ?>
        <?= ss_steps([
            ['Open our email', 'From ' . mail_from()],
            ['Tap “Confirm my email”', 'The link works for ' . VERIFY_HOURS . ' hours'],
        ]) ?>
      </div>

      <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

      <?php if ($inbox): ?>
        <a class="btn btn-accent btn-block" href="<?= e($inbox[1]) ?>" target="_blank" rel="noopener"><?= e($inbox[0]) ?> <?= icon('external') ?></a>
      <?php endif; ?>
      <div class="verify-actions<?= $inbox ? '' : ' single' ?>">
        <form method="post" action="<?= e(url('verify.php')) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="resend">
          <button class="btn <?= $inbox ? 'btn-ghost' : 'btn-accent' ?> btn-block" type="submit">Resend email</button>
        </form>
        <button class="btn btn-ghost btn-block" type="button" data-toggle="change-email" aria-expanded="<?= $changeOpen ? 'true' : 'false' ?>" aria-controls="change-email">Change email</button>
      </div>

      <form method="post" action="<?= e(url('verify.php')) ?>" class="verify-change-form" id="change-email"<?= $changeOpen ? '' : ' hidden' ?>>
        <?= csrf_field() ?><input type="hidden" name="action" value="change">
        <label for="email">Your correct email</label>
        <div class="verify-change-row">
          <input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= e(post('email', 190)) ?>" placeholder="you@example.com">
          <button class="btn btn-primary" type="submit">Save &amp; send</button>
        </div>
      </form>

      <p class="verify-note"><?= icon('info') ?> Not in your inbox? Check spam or promotions.</p>
      <p class="verify-help">Still nothing? <?php if ($phone !== ''): ?>Call us at <a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a> or tap Chat<?php else: ?>Tap Chat<?php endif; ?>, and we'll confirm your account for you.</p>
    </div>
  </div>
</section>
<?php page_footer();
