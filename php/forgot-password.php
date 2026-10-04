<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
$error = null;
$email = '';
if (is_post()) {
    verify_csrf();
    $email = post('email');
    if (!valid_email($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Same response whether or not the account exists. Max 3 emails per address per hour.
        $sent = true;
        $key = 'reset:' . normalize_email($email);
        if (!rate_limited($key, 3)) {
            rate_hit($key, 3600);
            if ($row = find_user_by_email($email)) {
                $link = app_url() . '/reset-password.php?token=' . create_reset_token((int) $row['id']);
                $first = explode(' ', $row['name'])[0];
                send_email($row['email'], simple_email('Reset your ' . config('site_name') . ' password', 'Reset your password', $first, [
                    'We received a request to reset your password. This link expires in 1 hour.',
                    "If you didn't ask for this, you can ignore this email — your password won't change.",
                ], [], $link, 'Choose a new password'));
            }
        }
    }
}
$title = 'Forgot password';
require __DIR__ . '/includes/header.php';
echo auth_shell_open('Forgot your password?', "Enter the email you signed up with and we'll send you a link to choose a new one.");
if ($sent): ?>
  <div class="space-y-5">
    <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-500 text-white"><?= icon('check', 16) ?></span>
      <p class="text-sm text-emerald-900">If an account exists for <strong><?= e($email) ?></strong>, we've sent a link to reset your password. It expires in 1 hour — check your spam folder if you don't see it.</p>
    </div>
    <a href="<?= e(url('signin.php')) ?>" class="block text-center text-sm font-semibold text-brand-700 hover:underline">← Back to sign in</a>
  </div>
<?php else: ?>
  <form method="post" class="space-y-5" novalidate data-pending-form>
    <?= csrf_field() ?>
    <?= text_field('email', 'Email', $email, 'email', $error, ['autocomplete' => 'email', 'placeholder' => 'you@example.com']) ?>
    <?= submit_button('Send reset link', 'Sending…') ?>
    <p class="text-center text-sm text-slate-600">Remembered it? <a href="<?= e(url('signin.php')) ?>" class="font-semibold text-brand-700 hover:underline">Sign in</a></p>
  </form>
<?php endif;
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
