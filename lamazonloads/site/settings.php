<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();
$errors = [];

$action = '';
if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'details') {
        $name = post('name', 100);
        $phone = format_phone(post('phone', 25));
        $type = post('account_type', 30);
        $city = post('city', 120);
        [$vehicles, $vehicleOther] = account_drives($type) ? posted_vehicles() : [[], ''];
        if ($name === '') $errors[] = 'Please enter your name.';
        if ($city !== '' && $city !== (string) $u['city']) { // a city saved before stays as it is
            if (($found = us_city($city)) === null) $errors[] = 'Please choose your city and state from the list.';
            else $city = $found;
        }
        if (in_array('other', $vehicles, true) && $vehicleOther === '') $errors[] = 'Please type what your other vehicle is.';
        if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
        if (!isset(ACCOUNT_TYPES[$type])) $errors[] = 'Please choose what describes you best.';
        if (!$errors) {
            db_run('UPDATE users SET name = ?, phone = ?, account_type = ?, city = ?, vehicle = ?, vehicle_other = ? WHERE id = ?', [$name, $phone, $type, $city, implode(',', $vehicles), $vehicleOther, $u['id']]);
            flash('success', 'Your details were saved.');
            redirect('settings.php');
        }
    } elseif ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (login_locked((string) $u['email'])) { // same limit as signing in, so the current password can't be guessed here
            $errors[] = 'Too many attempts. Please wait 15 minutes and try again.';
        } elseif (!password_verify($cur, $u['password_hash'])) {
            record_failed_login((string) $u['email']);
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) $errors[] = 'Your new password needs at least 8 characters.';
        elseif (($pp = password_format_problem($new)) !== '') $errors[] = $pp;
        elseif (weak_password($new, (string) $u['email'], (string) $u['name'])) $errors[] = 'That password is too easy to guess. Please choose a stronger one.';
        if (!$errors) {
            db_run('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1, reset_token = NULL, reset_expires = NULL WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            login_user(db_one('SELECT * FROM users WHERE id = ?', [$u['id']]));
            flash('success', 'Password changed. Other devices were signed out.');
            redirect('settings.php');
        }
    } elseif ($action === 'delete_account' && !$u['is_admin']) {
        // The member deletes their own account: password to confirm, a goodbye email, and a note to staff
        if (login_locked((string) $u['email'])) {
            $errors[] = 'Too many attempts. Please wait 15 minutes and try again.';
        } elseif (!password_verify((string) ($_POST['confirm_password'] ?? ''), $u['password_hash'])) {
            record_failed_login((string) $u['email']);
            $errors[] = 'That password is not correct, so your account was not deleted.';
        } else {
            notify_staff_account_deleted($u);
            send_account_closed($u, 'self');
            delete_member((int) $u['id']);
            logout_user();
            flash('success', 'Your account and information were deleted. We sent you a confirmation email. Thanks for riding with LamazonLoads.');
            redirect('');
        }
    }
}

page_header('Account settings');
dash_open('settings');
$errs = fn (string $section) => $errors && $action === $section ? '<ul class="errors">' . implode('', array_map(fn ($er) => '<li>' . e($er) . '</li>', $errors)) . '</ul>' : '';
?>
<h1>Account settings</h1>
<p class="muted">Manage your profile, sign-in and account.</p>

<form method="post" action="<?= e(url('settings.php')) ?>" class="card set-panel" id="profile">
  <?= csrf_field() ?><input type="hidden" name="action" value="details">
  <div class="set-intro"><h2>Profile</h2><p>Your name, mobile number, the work you do and your city.</p></div>
  <div class="set-body">
    <?= $errs('details') ?>
    <div class="form-grid">
      <div><label for="name">Full name</label><input id="name" name="name" type="text" maxlength="100" autocomplete="name" value="<?= e($action === 'details' ? post('name', 100) : $u['name']) ?>"></div>
      <div><label for="phone">Mobile phone</label><input id="phone" name="phone" type="tel" maxlength="25" autocomplete="tel" value="<?= e($action === 'details' ? post('phone', 25) : $u['phone']) ?>"></div>
      <div class="full"><label for="account_type">I am a…</label><select id="account_type" name="account_type" data-drives="owner_operator,driver"><?php foreach (ACCOUNT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= ($action === 'details' ? post('account_type', 30) : $u['account_type']) === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <?php $posted = $action === 'details'; ?>
      <?= vehicle_picker($posted ? posted_vehicles()[0] : user_vehicles($u), $posted ? post('vehicle_other', 80) : (string) $u['vehicle_other'], 'full', !account_drives($posted ? post('account_type', 30) : (string) $u['account_type'])) ?>
      <div class="full"><?= city_picker('city', $action === 'details' ? post('city', 120) : (string) $u['city']) ?></div>
    </div>
    <div class="set-foot"><button class="btn btn-primary" type="submit">Save profile</button></div>
  </div>
</form>

<section class="card set-panel" id="email">
  <div class="set-intro"><h2>Email</h2><p>Where we send your account and onboarding emails.</p></div>
  <div class="set-body">
    <div class="set-email"><?= ss_chip((string) $u['email']) ?>
      <?php if (is_verified($u)): ?><span class="set-badge is-ok"><?= icon('check') ?>Confirmed</span><?php else: ?><a class="set-badge is-warn" href="<?= e(url('verify.php')) ?>"><?= icon('alert') ?>Not confirmed · Confirm now</a><?php endif; ?></div>
    <p class="set-note">Need to change it? <a href="<?= e(url('contact.php')) ?>" data-open-chat>Message us</a> and we’ll update it for you.</p>
  </div>
</section>

<form method="post" action="<?= e(url('settings.php')) ?>" class="card set-panel" id="password">
  <?= csrf_field() ?><input type="hidden" name="action" value="password">
  <div class="set-intro"><h2>Password</h2><p>Changing it signs you out on your other devices.</p></div>
  <div class="set-body">
    <?= $errs('password') ?>
    <div class="form-grid">
      <div><label for="current">Current password</label><input id="current" name="current" type="password" autocomplete="current-password"></div>
      <div><label for="new">New password</label><input id="new" name="new" type="password" minlength="8" autocomplete="new-password"><p class="hint">At least 8 characters.</p></div>
    </div>
    <div class="set-foot"><button class="btn btn-primary" type="submit">Update password</button></div>
  </div>
</form>

<?php if (!$u['is_admin']): ?>
<details class="card set-panel set-danger"<?= $action === 'delete_account' && $errors ? ' open' : '' ?>>
  <summary><span class="set-intro"><span class="set-h">Delete account</span><span class="set-p">Permanently remove your account and everything in it.</span></span><span class="set-chev" aria-hidden="true"><?= icon('chev-right') ?></span></summary>
  <form method="post" action="<?= e(url('settings.php')) ?>" class="set-body" id="delete" data-confirm-ok="Delete my account" data-confirm="Delete your LamazonLoads account for good? Your profile, documents and applications will be removed. This can’t be undone.">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_account">
    <?= $errs('delete_account') ?>
    <p class="set-note">This removes your account, driver profile, documents, applications and chats. It can’t be undone. We’ll email you to confirm.</p>
    <div class="form-grid">
      <div><label for="confirm_password">Type your password to confirm</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="current-password" required></div>
    </div>
    <div class="set-foot"><button class="btn btn-danger" type="submit"><?= icon('trash') ?> Delete my account</button></div>
  </form>
</details>
<?php endif; ?>
<?php dash_close(); page_footer();
