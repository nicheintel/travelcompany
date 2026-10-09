<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next($_GET['next'] ?? ($_POST['next'] ?? null), '');
if (current_user()) redirect($next ?: url('account.php'));

$errors = [];
$name = $email = '';
if (is_post()) {
    verify_csrf();
    $name = post('name');
    $email = post('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    if ($err = name_problem($name)) $errors['name'] = $err;
    if (!valid_email($email)) $errors['email'] = t('Please enter a valid email address.');
    elseif (($fix = email_typo(normalize_email($email))) !== null) $errors['email'] = t('Check the spelling. Did you mean {email}?', ['email' => $fix]);
    if ($err = password_problem($password)) $errors['password'] = $err;
    if ($password !== $confirm) $errors['confirm'] = t("Passwords don't match.");
    if (!$errors && ip_throttled('register', 10, 3600)) $errors['email'] = t('Too many new accounts from your network. Please try again in an hour.');
    if (!$errors && find_user_by_email($email)) $errors['email'] = t('An account with this email already exists. Try signing in.');
    if (!$errors) {
        try {
            db_run('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)', [$name, normalize_email($email), password_hash($password, PASSWORD_DEFAULT), now_utc()]);
            $newUser = find_user((int) db()->lastInsertId());
            login_user($newUser);
            send_verification_email($newUser);
            redirect(url('verify-email.php', ['next' => $next ?: url('account.php')]));
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            $errors['email'] = t('An account with this email already exists. Try signing in.');
        }
    }
}
$title = 'Create account';
require __DIR__ . '/includes/header.php';
echo auth_shell_open(t('Create your account'), t('Book faster and keep all your trips in one place.'), notice_for($next));
?>
<form method="post" class="space-y-5" novalidate data-pending-form>
  <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
  <?= text_field('name', t('Full name'), $name, 'text', $errors['name'] ?? null, ['autocomplete' => 'name', 'placeholder' => 'Alex Rivera']) ?>
  <?= text_field('email', t('Email'), $email, 'email', $errors['email'] ?? null, ['autocomplete' => 'email', 'placeholder' => 'you@example.com']) ?>
  <div>
    <?= text_field('password', t('Password'), '', 'password', $errors['password'] ?? null, ['autocomplete' => 'new-password', 'data-password-rules' => '']) ?>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs" data-rules>
      <?php foreach (['len' => t('At least 8 characters'), 'letter' => t('A letter'), 'number' => t('A number')] as $k => $label): ?>
        <li class="flex items-center gap-1 text-slate-500 data-[ok]:text-emerald-700" data-rule="<?= $k ?>"><?= icon('check', 12) ?><?= e($label) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?= text_field('confirm', t('Confirm password'), '', 'password', $errors['confirm'] ?? null, ['autocomplete' => 'new-password']) ?>
  <p class="text-xs text-slate-500"><?= th('By creating an account you agree to our {terms} and {privacy}.', [], [
    'terms' => '<a class="font-semibold text-brand-700 hover:underline" href="' . e(url('terms.php')) . '">' . e(t('Terms of use')) . '</a>',
    'privacy' => '<a class="font-semibold text-brand-700 hover:underline" href="' . e(url('privacy.php')) . '">' . e(t('Privacy policy')) . '</a>',
  ]) ?></p>
  <?= submit_button(t('Create account'), t('Creating account…')) ?>
  <p class="text-center text-sm text-slate-600"><?= th('Already have an account? {link}', [], ['link' => '<a href="' . e(url('signin.php', ['next' => $next ?: null])) . '" class="font-semibold text-brand-700 hover:underline">' . e(t('Sign in')) . '</a>']) ?></p>
</form>
<?php
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
