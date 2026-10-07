<?php
/**
 * First-time setup: creates the owner's admin account. Only works while there are no admins.
 * On your own computer (XAMPP) it just works; on a live server it needs the setup_key from
 * config.local.php (open setup.php?key=YOUR-KEY), so strangers can't claim your dashboard.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (has_admins()) {
    redirect(url('admin/login.php'));
}
$key = (string) config('setup_key');
$sentKey = param('key') ?: post('key');
$allowed = is_local_request() || ($key !== '' && strlen($key) >= 12 && hash_equals($key, $sentKey));
if (!$allowed && $sentKey !== '' && ip_throttled('setup-key', 10, 3600)) {
    http_response_code(429);
    exit('Too many attempts. Please wait an hour and try again.');
}

if (!$allowed) {
    auth_start('Set up', 'Set up your dashboard', 'Create the owner account for this website.');
    ?>
    <div class="alert alert-info"><?= icon('lock') ?><span>For safety, the owner account can only be created from the computer running the website (XAMPP), or with a setup key.</span></div>
    <ol class="setup-steps">
      <li>Open <code>config.local.php</code> in the website folder (copy it from <code>config.local.example.php</code> if it isn't there).</li>
      <li>Add a line like <code>'setup_key' =&gt; 'pick-a-long-secret-phrase',</code> (at least 12 characters).</li>
      <li>Open <code>admin/setup.php?key=pick-a-long-secret-phrase</code> in your browser.</li>
    </ol>
    <?php
    auth_end();
    exit;
}

$v = ['name' => '', 'email' => ''];
$errors = [];
if (is_post()) {
    verify_csrf();
    $v = ['name' => post('name'), 'email' => normalize_email(post('email'))];
    $password = (string) ($_POST['password'] ?? '');
    if (!len_between($v['name'], 2, 80)) $errors['name'] = 'Please enter your name.';
    if (!valid_email($v['email'])) $errors['email'] = 'Please enter a valid email address.';
    if ($p = password_problem($password)) $errors['password'] = $p;
    elseif ($password !== (string) ($_POST['password2'] ?? '')) $errors['password2'] = 'The two passwords don\'t match.';
    if (!$errors && !has_admins()) {
        $id = db_insert(
            'INSERT INTO admins (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)',
            [$v['name'], $v['email'], password_hash($password, PASSWORD_DEFAULT), now_utc()],
        );
        login_admin(db_one('SELECT * FROM admins WHERE id = ?', [$id]));
        flash('Your dashboard is ready! Start with Site settings to add your brand name and contact details.');
        redirect(url('admin/'));
    }
}

auth_start('Set up', 'Create your owner account', 'You\'re the first one here. This account controls the whole website.');
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<form method="post" class="auth-fields" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="key" value="<?= e($sentKey) ?>">
  <div class="field">
    <label for="name">Your name</label>
    <input id="name" name="name" type="text" autocomplete="name" required maxlength="80" value="<?= e($v['name']) ?>"<?= isset($errors['name']) ? ' aria-invalid="true"' : '' ?> autofocus>
    <?= $err('name') ?>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($v['email']) ?>"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?>>
    <?= $err('email') ?>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <div class="pw-wrap">
      <input id="password" name="password" type="password" autocomplete="new-password" required<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
      <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye') ?></button>
    </div>
    <small class="help">At least 8 characters, with a letter and a number.</small>
    <?= $err('password') ?>
  </div>
  <div class="field">
    <label for="password2">Type the password again</label>
    <input id="password2" name="password2" type="password" autocomplete="new-password" required<?= isset($errors['password2']) ? ' aria-invalid="true"' : '' ?>>
    <?= $err('password2') ?>
  </div>
  <button class="btn btn-orange btn-block btn-lg" type="submit">Create my account <?= icon('arrow-right') ?></button>
</form>
<?php auth_end();
