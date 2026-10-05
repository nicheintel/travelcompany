<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_user();
$form = post('form');
$errors = [];
$ok = [];
if (is_post()) {
    verify_csrf();
    $row = db_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
    if ($form === 'name') {
        $name = post('name');
        if ($err = name_problem($name)) $errors['name'] = $err;
        else {
            db_run('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]);
            flash('Your name has been updated.');
            redirect(url('settings.php'));
        }
    } elseif ($form === 'email') {
        $email = normalize_email(post('email'));
        if (!valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
        elseif ($email === $user['email']) $errors['email'] = "That's already your email address.";
        elseif ($err = check_password_with_lockout($row, (string) ($_POST['email_current'] ?? ''), 'Your current password is incorrect.')) $errors['email_current'] = $err;
        elseif (find_user_by_email($email)) $errors['email'] = 'Another account already uses this email.';
        else {
            db_run('UPDATE users SET email = ?, email_verified_at = NULL WHERE id = ?', [$email, $user['id']]);
            send_verification_email(['email' => $email] + $user);
            // Tell the old address, so a hijacked account doesn't go unnoticed.
            send_email($user['email'], simple_email('Your ' . config('site_name') . ' email address was changed', 'Your email address was changed', explode(' ', $user['name'])[0], [
                "The email address on your account was changed to $email. Future emails will go there.",
                "If this wasn't you, reset your password right away and contact our support team.",
            ], [], account_link('forgot-password.php'), 'Reset password'));
            flash("Your email is now $email. We've sent a confirmation link there — please open it.");
            redirect(url('settings.php'));
        }
    } elseif ($form === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        if ($err = password_problem($new)) $errors['password'] = $err;
        elseif ($new !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = "Passwords don't match.";
        elseif ($err = check_password_with_lockout($row, (string) ($_POST['current'] ?? ''), 'Your current password is incorrect.')) $errors['current'] = $err;
        else {
            // Bumping the session version signs out every other browser; keep this one signed in.
            db_run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $_SESSION['ver'] = $user['session_version'] + 1;
            session_regenerate_id(true);
            send_email($user['email'], simple_email('Your ' . config('site_name') . ' password was changed', 'Your password was changed', explode(' ', $user['name'])[0], [
                'Your password was just changed and you were signed out on other devices.',
                "If this wasn't you, reset your password right away.",
            ], [], account_link('forgot-password.php'), 'Reset password'));
            flash("Password changed. You've been signed out on all other devices.");
            redirect(url('settings.php'));
        }
    }
}
$title = 'Account settings';
require __DIR__ . '/includes/header.php';
$card = fn(string $h, string $d) => '<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-lg font-semibold text-slate-900">' . e($h) . '</h2><p class="mt-1 text-sm text-slate-500">' . e($d) . '</p><div class="mt-5">';
?>
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url('account.php')) ?>" class="hover:text-brand-700">← My account</a></nav>
  <h1 class="text-3xl font-bold text-slate-900">Account settings</h1>
  <div class="mt-8 space-y-6">
    <?= $card('Profile', 'Your name as it appears on your account.') ?>
      <form method="post" class="space-y-4" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="name">
        <?= text_field('name', 'Full name', $form === 'name' ? post('name') : $user['name'], 'text', $errors['name'] ?? null, ['autocomplete' => 'name']) ?>
        <div class="sm:w-48"><?= submit_button('Save name', 'Saving…') ?></div>
      </form>
    </div></section>

    <?= $card('Email address', "Currently {$user['email']}. We'll notify your old address when it changes.") ?>
      <form method="post" class="space-y-4" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="email">
        <div class="grid gap-4 sm:grid-cols-2">
          <?= text_field('email', 'New email', $form === 'email' ? post('email') : '', 'email', $errors['email'] ?? null, ['autocomplete' => 'email']) ?>
          <?= text_field('email_current', 'Current password', '', 'password', $errors['email_current'] ?? null, ['autocomplete' => 'current-password']) ?>
        </div>
        <div class="sm:w-48"><?= submit_button('Change email', 'Saving…') ?></div>
      </form>
    </div></section>

    <?= $card('Password', 'Changing your password signs you out on all other devices.') ?>
      <form method="post" class="space-y-4" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="password">
        <?= text_field('current', 'Current password', '', 'password', $errors['current'] ?? null, ['autocomplete' => 'current-password']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <?= text_field('password', 'New password', '', 'password', $errors['password'] ?? null, ['autocomplete' => 'new-password'], 'At least 8 characters, with a letter and a number.') ?>
          <?= text_field('confirm', 'Confirm new password', '', 'password', $errors['confirm'] ?? null, ['autocomplete' => 'new-password']) ?>
        </div>
        <div class="sm:w-48"><?= submit_button('Change password', 'Saving…') ?></div>
      </form>
    </div></section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
