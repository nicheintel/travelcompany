<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? 'account.php'));
if (current_user()) {
    redirect($next);
}
$errors = [];
$val = ['name' => '', 'email' => '', 'phone' => '', 'account_type' => 'owner_operator'];

if (is_post()) {
    csrf_check();
    foreach ($val as $k => $_) {
        $val[$k] = post($k, 190);
    }
    $val['email'] = strtolower($val['email']);
    $pass = (string) ($_POST['password'] ?? '');
    if ($val['name'] === '' || mb_strlen($val['name']) > 100) $errors[] = 'Please enter your full name.';
    if (!filter_var($val['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $val['phone'])) $errors[] = 'Please enter a valid phone number.';
    if (!isset(ACCOUNT_TYPES[$val['account_type']])) $errors[] = 'Please choose what describes you best.';
    if (strlen($pass) < 8) $errors[] = 'Your password needs at least 8 characters.';
    if (strlen($pass) > 200) $errors[] = 'That password is too long.';
    if (empty($_POST['agree'])) $errors[] = 'Please confirm the information is accurate.';
    if (!$errors && db_val('SELECT id FROM users WHERE email = ?', [$val['email']])) {
        $errors[] = 'An account with this email already exists. Please sign in instead.';
    }
    if (!$errors) {
        $admin = should_be_admin($val['email']) ? 1 : 0;
        db_run('INSERT INTO users (name, email, phone, password_hash, account_type, is_admin, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$val['name'], $val['email'], $val['phone'], password_hash($pass, PASSWORD_DEFAULT), $val['account_type'], $admin]);
        $user = db_one('SELECT * FROM users WHERE id = ?', [(int) db()->lastInsertId()]);
        login_user($user);
        flash('success', 'Welcome to LamazonLoads, ' . $val['name'] . '!' . ($admin ? ' You are the site admin: open "Admin" in the menu to manage job posts and applicants.' : ' Complete your driver profile to get matched faster.'));
        redirect($next === 'account.php' ? 'profile.php' : $next);
    }
}

page_header('Create your account', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Join the network that keeps you loaded.</h2>
    <ul class="checklist" style="color:#E3EBFF">
      <li><span class="tick"><?= icon('check') ?></span>Apply to job posts and daily routes in one click</li>
      <li><span class="tick"><?= icon('check') ?></span>Onboard once: profile, insurance, W-9 and license</li>
      <li><span class="tick"><?= icon('check') ?></span>Track every application from your dashboard</li>
      <li><span class="tick"><?= icon('check') ?></span>Get first access to new loads and contracts</li>
    </ul>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Create your free account</h1>
      <p class="sub">Already have one? <a href="<?= e(url('login.php?next=' . rawurlencode($next))) ?>">Sign in</a></p>
      <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url('register.php')) ?>" class="form-grid" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="full"><label for="name">Full name</label><input id="name" name="name" type="text" required maxlength="100" autocomplete="name" value="<?= e($val['name']) ?>"></div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($val['email']) ?>"></div>
        <div><label for="phone">Mobile phone</label><input id="phone" name="phone" type="tel" required maxlength="25" autocomplete="tel" placeholder="(555) 123-4567" value="<?= e($val['phone']) ?>"></div>
        <div class="full"><label for="account_type">I am a…</label>
          <select id="account_type" name="account_type"><?php foreach (ACCOUNT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= $val['account_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><p class="hint">At least 8 characters.</p></div>
        <div class="full"><label class="check"><input type="checkbox" name="agree" value="1"<?= !empty($_POST['agree']) ? ' checked' : '' ?>> The information I provide is accurate, and LamazonLoads may contact me about loads, routes and job openings.</label></div>
        <div class="full"><button class="btn btn-accent btn-lg btn-block" type="submit">Create account <?= icon('arrow') ?></button></div>
      </form>
    </div>
  </div>
</div>
<?php page_footer();
