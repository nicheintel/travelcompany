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
            flash(t('Your name has been updated.'));
            redirect(url('settings.php'));
        }
    } elseif ($form === 'email') {
        $email = normalize_email(post('email'));
        if (!valid_email($email)) $errors['email'] = t('Please enter a valid email address.');
        elseif ($email === $user['email']) $errors['email'] = t("That's already your email address.");
        elseif ($err = check_password_with_lockout($row, (string) ($_POST['email_current'] ?? ''), t('Your current password is incorrect.'))) $errors['email_current'] = $err;
        elseif (find_user_by_email($email)) $errors['email'] = t('Another account already uses this email.');
        else {
            db_run('UPDATE users SET email = ?, email_verified_at = NULL WHERE id = ?', [$email, $user['id']]);
            send_verification_email(['email' => $email] + $user);
            // Tell the old address, so a hijacked account doesn't go unnoticed.
            send_email($user['email'], simple_email('Your ' . config('site_name') . ' email address was changed', 'Your email address was changed', explode(' ', $user['name'])[0], [
                "The email address on your account was changed to $email. Future emails will go there.",
                "If this wasn't you, reset your password right away and contact our support team.",
            ], [], account_link('forgot-password.php'), 'Reset password'));
            flash(t("Your email is now {email}. We've sent a confirmation link there — please open it.", ['email' => $email]));
            redirect(url('settings.php'));
        }
    } elseif ($form === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        if ($err = password_problem($new)) $errors['password'] = $err;
        elseif ($new !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = t("Passwords don't match.");
        elseif ($err = check_password_with_lockout($row, (string) ($_POST['current'] ?? ''), t('Your current password is incorrect.'))) $errors['current'] = $err;
        else {
            // Bumping the session version signs out every other browser; keep this one signed in.
            db_run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $_SESSION['ver'] = $user['session_version'] + 1;
            session_regenerate_id(true);
            send_email($user['email'], simple_email('Your ' . config('site_name') . ' password was changed', 'Your password was changed', explode(' ', $user['name'])[0], [
                'Your password was just changed and you were signed out on other devices.',
                "If this wasn't you, reset your password right away.",
            ], [], account_link('forgot-password.php'), 'Reset password'));
            flash(t("Password changed. You've been signed out on all other devices."));
            redirect(url('settings.php'));
        }
    }
}
$title = 'Account settings';
require __DIR__ . '/includes/header.php';
$initials = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(preg_split('/\s+/', trim($user['name'])), 0, 2)));
$open = fn(string $id, string $icon, string $h, string $d) => '<section id="' . $id . '" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white">'
    . '<div class="flex items-start gap-4 border-b border-slate-100 p-6"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">' . icon($icon, 20) . '</span>'
    . '<div><h2 class="text-lg font-semibold text-slate-900">' . e($h) . '</h2><p class="mt-0.5 text-sm text-slate-500">' . $d . '</p></div></div>';
$actions = fn(string $label) => '<div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4">' . submit_button($label, t('Saving…'), 'w-full px-6 sm:w-auto') . '</div>';
$nav = [['profile', 'user', t('Profile')], ['email', 'mail', t('Email address')], ['password', 'shield', t('Password')], ['help', 'headset', t('Help')]];
$contacts = support_contacts();
if (!$contacts) array_pop($nav);
?>
<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
  <nav class="mb-4 text-sm text-slate-500"><a href="<?= e(url('account.php')) ?>" class="hover:text-brand-700">← <?= e(t('My account')) ?></a></nav>
  <h1 class="text-3xl font-bold text-slate-900"><?= e(t('Account settings')) ?></h1>
  <p class="mt-1 text-slate-600"><?= e(t('Manage your profile, sign-in email and password.')) ?></p>

  <div class="mt-8 grid gap-8 lg:grid-cols-[260px_1fr]">
    <aside class="min-w-0 lg:sticky lg:top-24 lg:h-fit">
      <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-600 font-bold text-white"><?= e($initials) ?></span>
        <div class="min-w-0">
          <p class="truncate font-semibold text-slate-900"><?= e($user['name']) ?></p>
          <p class="truncate text-sm text-slate-500"><?= e($user['email']) ?></p>
          <?= $user['verified']
              ? '<span class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">' . icon('check', 12) . ' ' . e(t('Email confirmed')) . '</span>'
              : '<span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">' . e(t('Email not confirmed')) . '</span>' ?>
        </div>
      </div>
      <ul class="mt-4 flex gap-2 overflow-x-auto lg:flex-col lg:gap-1">
        <?php foreach ($nav as [$id, $ic, $label]): ?>
          <li><a href="#<?= $id ?>" class="flex shrink-0 items-center gap-3 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-white hover:text-brand-700 hover:shadow-sm"><?= icon($ic, 18, 'text-slate-400') ?><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <div class="min-w-0 space-y-6">
      <?= $open('profile', 'user', t('Profile'), e(t('Your name as it appears on your account and in our emails.'))) ?>
        <form method="post" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="name">
          <div class="p-6 sm:max-w-md"><?= text_field('name', t('Full name'), $form === 'name' ? post('name') : $user['name'], 'text', $errors['name'] ?? null, ['autocomplete' => 'name']) ?></div>
          <?= $actions(t('Save name')) ?>
        </form>
      </section>

      <?= $open('email', 'mail', t('Email address'), th('You sign in with this address and receive your booking emails here. Currently {email}.', [], ['email' => '<strong class="text-slate-700">' . e($user['email']) . '</strong>'])) ?>
        <form method="post" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="email">
          <div class="grid gap-4 p-6 sm:grid-cols-2">
            <?= text_field('email', t('New email'), $form === 'email' ? post('email') : '', 'email', $errors['email'] ?? null, ['autocomplete' => 'email']) ?>
            <?= text_field('email_current', t('Current password'), '', 'password', $errors['email_current'] ?? null, ['autocomplete' => 'current-password']) ?>
            <p class="text-xs text-slate-500 sm:col-span-2"><?= e(t("We'll send a confirmation link to the new address and let your old address know about the change.")) ?></p>
          </div>
          <?= $actions(t('Change email')) ?>
        </form>
      </section>

      <?= $open('password', 'shield', t('Password'), e(t('Changing your password signs you out on all other devices.'))) ?>
        <form method="post" novalidate data-pending-form><?= csrf_field() ?><input type="hidden" name="form" value="password">
          <div class="space-y-4 p-6">
            <div class="sm:max-w-md"><?= text_field('current', t('Current password'), '', 'password', $errors['current'] ?? null, ['autocomplete' => 'current-password']) ?></div>
            <div class="grid gap-4 sm:grid-cols-2">
              <?= text_field('password', t('New password'), '', 'password', $errors['password'] ?? null, ['autocomplete' => 'new-password'], t('At least 8 characters, with a letter and a number.')) ?>
              <?= text_field('confirm', t('Confirm new password'), '', 'password', $errors['confirm'] ?? null, ['autocomplete' => 'new-password']) ?>
            </div>
          </div>
          <?= $actions(t('Change password')) ?>
        </form>
      </section>

      <?php if ($contacts): ?>
        <?= $open('help', 'headset', t('Help'), e(t('Questions about your account or a booking? Our travel assistants are happy to help.'))) ?>
          <div class="flex flex-wrap gap-3 p-6">
            <?php if (isset($contacts['email'])): ?><a href="mailto:<?= e($contacts['email']) ?>" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50"><?= icon('mail', 16) ?> <?= e($contacts['email']) ?></a><?php endif; ?>
            <?php if (isset($contacts['whatsapp'])): ?><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $contacts['whatsapp'])) ?>" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50"><?= icon('chat', 16) ?> WhatsApp <?= e($contacts['whatsapp']) ?></a><?php endif; ?>
            <?php if (isset($contacts['phone'])): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $contacts['phone'])) ?>" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"><?= icon('headset', 16) ?> <?= e($contacts['phone']) ?></a><?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
