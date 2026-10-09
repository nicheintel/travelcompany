<?php
/* The "Check your email" screen new members see until they confirm their address (with new links on request and
   a way to fix a mistyped address), and the confirmation link from the email itself. */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$noReferrer = true; // keep the token out of Referer headers
$next = safe_next($_GET['next'] ?? ($_POST['next'] ?? null), url('account.php'));
$errors = [];

// The waiting screen asks every few seconds whether the link was opened (in another tab or on a phone).
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['verified' => (bool) ($user['verified'] ?? false)]);
    exit;
}

if (is_post()) {
    verify_csrf();
    $user = require_user();
    if (post('form') === 'email') {
        $errors = change_email($user, post('email'), (string) ($_POST['password'] ?? ''));
        if (!$errors) {
            flash(t("Your email is now {email}. We've sent a confirmation link there — please open it.", ['email' => normalize_email(post('email'))]));
            redirect(url('verify-email.php', ['next' => $next]));
        }
    } else {
        if ($user['verified']) {
            flash(t('Your email is already confirmed.'));
        } elseif (rate_limited('verify:' . $user['id'], 3) || ip_throttled('verify', 10, 3600)) {
            flash(t("We've already sent a few links — please check your inbox and spam folder, or try again in an hour."), 'error');
        } else {
            rate_hit('verify:' . $user['id'], 3600);
            send_verification_email($user);
            flash(t('New confirmation link sent to {email}. Check your inbox (and spam folder).', ['email' => $user['email']]));
        }
        redirect($next);
    }
}

$token = (string) ($_GET['token'] ?? '');
if ($token !== '') {
    $ok = confirm_email_token($token);
    if ($ok) {
        flash(t('Thanks — your email is confirmed. You can now book trips.'));
        // Same browser that signed up: carry on where they were going (e.g. the trip they were booking).
        $to = $user && $user['id'] === $ok ? safe_next($_SESSION['verify_next'] ?? null, url('account.php')) : url('account.php');
        unset($_SESSION['verify_next']);
        redirect($user ? $to : url('signin.php'));
    }
} elseif (!$user) {
    redirect(url('signin.php'));
} elseif ($user['verified']) {
    redirect($next);
}

$title = 'Confirm your email';
$noindex = true;
require __DIR__ . '/includes/header.php';

if ($token !== ''):
    echo auth_shell_open(t('This link has expired'), t('Confirmation links work once and expire after 48 hours, or after you change your email address.'));
?>
<div class="space-y-4">
  <?php if ($user && !$user['verified']): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e(url('verify-email.php')) ?>">
      <button type="submit" class="w-full rounded-xl bg-brand-600 py-3 font-semibold text-white hover:bg-brand-700"><?= e(t('Send me a new link')) ?></button>
    </form>
  <?php elseif ($user): ?>
    <p class="text-sm text-slate-600"><?= e(t('Your email address is already confirmed.')) ?></p>
    <a href="<?= e(url('account.php')) ?>" class="block text-center text-sm font-semibold text-brand-700 hover:underline"><?= e(t('Go to my account →')) ?></a>
  <?php else: ?>
    <p class="text-sm text-slate-600"><?= e(t("Sign in and we'll send you a new link.")) ?></p>
    <a href="<?= e(url('signin.php')) ?>" class="block rounded-xl bg-brand-600 py-3 text-center font-semibold text-white hover:bg-brand-700"><?= e(t('Sign in')) ?></a>
  <?php endif; ?>
</div>
<?php
else:
    $_SESSION['verify_next'] = $next;
    $here = url('verify-email.php', ['next' => $next]);
    $typo = email_typo($user['email']);
    echo auth_shell_open(t('Check your email'), t('One last step before your account is ready.'));
?>
<div class="space-y-5" data-verify-wait data-poll="<?= e(url('verify-email.php', ['check' => 1])) ?>" data-next="<?= e($next) ?>">
  <div class="flex items-start gap-4 rounded-2xl bg-brand-50 p-5 ring-1 ring-brand-100">
    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-white text-brand-600 shadow-sm"><?= icon('mail', 24) ?></span>
    <div class="min-w-0">
      <p class="text-slate-700"><?= th('We sent a confirmation link to {email}. Open it to finish setting up your account.', [], ['email' => '<strong class="wrap-anywhere text-slate-900">' . e($user['email']) . '</strong>']) ?></p>
      <p class="mt-2 flex items-center gap-2 text-sm text-slate-500"><span class="relative flex h-2 w-2 shrink-0"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-accent-500"></span></span><?= e(t('This page continues by itself once you open the link.')) ?></p>
    </div>
  </div>

  <?php if ($typo): ?>
    <div role="alert" class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
      <p class="font-semibold"><?= e(t('This email address looks mistyped.')) ?></p>
      <p class="mt-1"><?= th("Did you mean {email}? Change it below and we'll send the link there.", [], ['email' => '<strong class="wrap-anywhere">' . e($typo) . '</strong>']) ?></p>
    </div>
  <?php endif; ?>

  <form method="post"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($here) ?>">
    <button type="submit" class="w-full rounded-xl bg-brand-600 py-3 font-semibold text-white hover:bg-brand-700"><?= e(t('Send me a new link')) ?></button>
  </form>
  <p class="text-sm text-slate-500"><?= e(t("Can't find it? Check your spam or Promotions folder. The link works for 48 hours.")) ?></p>

  <details class="rounded-2xl border border-slate-200 bg-white"<?= $typo || $errors ? ' open' : '' ?>>
    <summary class="cursor-pointer select-none px-5 py-4 font-semibold text-slate-900"><?= e(t('Wrong email address? Change it')) ?></summary>
    <form method="post" novalidate data-pending-form class="space-y-4 border-t border-slate-100 p-5"><?= csrf_field() ?>
      <input type="hidden" name="form" value="email"><input type="hidden" name="next" value="<?= e($next) ?>">
      <?= text_field('email', t('New email'), $errors ? post('email') : (string) $typo, 'email', $errors['email'] ?? null, ['autocomplete' => 'email', 'placeholder' => 'you@example.com']) ?>
      <?= text_field('password', t('Current password'), '', 'password', $errors['password'] ?? null, ['autocomplete' => 'current-password']) ?>
      <?= submit_button(t('Change email'), t('Saving…')) ?>
    </form>
  </details>

  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5 text-sm">
    <a href="<?= e($next) ?>" class="font-semibold text-brand-700 hover:underline"><?= e(t("I've confirmed it — continue →")) ?></a>
    <form method="post" action="<?= e(url('signout.php')) ?>"><?= csrf_field() ?>
      <button type="submit" class="font-medium text-slate-500 hover:text-slate-800"><?= e(t('Sign out')) ?></button>
    </form>
  </div>
</div>
<?php
endif;
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
