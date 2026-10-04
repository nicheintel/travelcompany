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
    if (!valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
    if ($err = password_problem($password)) $errors['password'] = $err;
    if ($password !== $confirm) $errors['confirm'] = "Passwords don't match.";
    if (!$errors && find_user_by_email($email)) $errors['email'] = 'An account with this email already exists. Try signing in.';
    if (!$errors) {
        try {
            db_run('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)', [$name, normalize_email($email), password_hash($password, PASSWORD_DEFAULT), now_utc()]);
            login_user(find_user((int) db()->lastInsertId()));
            redirect($next ?: url('account.php'));
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            $errors['email'] = 'An account with this email already exists. Try signing in.';
        }
    }
}
$title = 'Create account';
require __DIR__ . '/includes/header.php';
echo auth_shell_open('Create your free account', 'It takes less than a minute. No credit card needed.', notice_for($next));
?>
<form method="post" class="space-y-5" novalidate data-pending-form>
  <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
  <?= text_field('name', 'Full name', $name, 'text', $errors['name'] ?? null, ['autocomplete' => 'name', 'placeholder' => 'Alex Rivera']) ?>
  <?= text_field('email', 'Email', $email, 'email', $errors['email'] ?? null, ['autocomplete' => 'email', 'placeholder' => 'you@example.com']) ?>
  <div>
    <?= text_field('password', 'Password', '', 'password', $errors['password'] ?? null, ['autocomplete' => 'new-password', 'data-password-rules' => '']) ?>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs" data-rules>
      <?php foreach (['len' => 'At least 8 characters', 'letter' => 'A letter', 'number' => 'A number'] as $k => $label): ?>
        <li class="flex items-center gap-1 text-slate-500 data-[ok]:text-emerald-600" data-rule="<?= $k ?>"><?= icon('check', 12) ?><?= $label ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?= text_field('confirm', 'Confirm password', '', 'password', $errors['confirm'] ?? null, ['autocomplete' => 'new-password']) ?>
  <?= submit_button('Create account', 'Creating account…') ?>
  <p class="text-center text-xs text-slate-500">By creating an account you agree to our Terms of Service and Privacy Policy.</p>
  <p class="text-center text-sm text-slate-600">Already have an account? <a href="<?= e(url('signin.php', ['next' => $next ?: null])) ?>" class="font-semibold text-brand-700 hover:underline">Sign in</a></p>
</form>
<?php
echo auth_shell_close();
require __DIR__ . '/includes/footer.php';
