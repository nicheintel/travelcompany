<?php
/* Confirms an email address from the link in the email, and re-sends that link on request. */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$noReferrer = true; // keep the token out of Referer headers

if (is_post()) {
    verify_csrf();
    $user = require_user();
    if ($user['verified']) {
        flash('Your email is already confirmed.');
    } elseif (rate_limited('verify:' . $user['id'], 3) || ip_throttled('verify', 10, 3600)) {
        flash("We've already sent a few links — please check your inbox and spam folder, or try again in an hour.", 'error');
    } else {
        rate_hit('verify:' . $user['id'], 3600);
        send_verification_email($user);
        flash("New confirmation link sent to {$user['email']}. Check your inbox (and spam folder).");
    }
    redirect(safe_next($_POST['next'] ?? null, url('account.php')));
}

$ok = confirm_email_token((string) ($_GET['token'] ?? ''));
if ($ok) {
    flash('Thanks — your email is confirmed. You can now book trips.');
    redirect($user ? url('account.php') : url('signin.php'));
}

$title = 'Confirm your email';
$noindex = true;
require __DIR__ . '/includes/header.php';
echo auth_shell_open('This link has expired', 'Confirmation links work once and expire after 48 hours, or after you change your email address.');
?>
<div class="space-y-4">
  <?php if ($user && !$user['verified']): ?>
    <form method="post"><?= csrf_field() ?>
      <button type="submit" class="w-full rounded-xl bg-brand-600 py-3 font-semibold text-white hover:bg-brand-700">Send me a new link</button>
    </form>
  <?php elseif ($user): ?>
    <p class="text-sm text-slate-600">Your email address is already confirmed.</p>
    <a href="<?= e(url('account.php')) ?>" class="block text-center text-sm font-semibold text-brand-700 hover:underline">Go to my account →</a>
  <?php else: ?>
    <p class="text-sm text-slate-600">Sign in and we'll send you a new link.</p>
    <a href="<?= e(url('signin.php')) ?>" class="block rounded-xl bg-brand-600 py-3 text-center font-semibold text-white hover:bg-brand-700">Sign in</a>
  <?php endif; ?>
</div>
<?php echo auth_shell_close(); require __DIR__ . '/includes/footer.php';
