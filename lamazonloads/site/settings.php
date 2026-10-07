<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();
$errors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'details') {
        $name = post('name', 100);
        $phone = format_phone(post('phone', 25));
        $type = post('account_type', 30);
        if ($name === '') $errors[] = 'Please enter your name.';
        if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
        if (!isset(ACCOUNT_TYPES[$type])) $errors[] = 'Please choose what describes you best.';
        if (!$errors) {
            db_run('UPDATE users SET name = ?, phone = ?, account_type = ? WHERE id = ?', [$name, $phone, $type, $u['id']]);
            flash('success', 'Your details were saved.');
            redirect('settings.php');
        }
    } elseif ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify($cur, $u['password_hash'])) $errors[] = 'Your current password is not correct.';
        if (strlen($new) < 8 || strlen($new) > 200) $errors[] = 'Your new password needs at least 8 characters.';
        elseif (weak_password($new, (string) $u['email'], (string) $u['name'])) $errors[] = 'That password is too easy to guess. Please choose a stronger one.';
        if (!$errors) {
            db_run('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
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
?>
<h1>Account settings</h1>
<?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
<form method="post" action="<?= e(url('settings.php')) ?>" class="card form-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="details">
  <h3 class="mt-0">Your details</h3>
  <div class="form-grid">
    <div><label for="name">Full name</label><input id="name" name="name" type="text" maxlength="100" value="<?= e($u['name']) ?>"></div>
    <div><label for="phone">Mobile phone</label><input id="phone" name="phone" type="tel" maxlength="25" value="<?= e($u['phone']) ?>"></div>
    <div><label>Email</label><input type="email" value="<?= e($u['email']) ?>" disabled><p class="hint">To change your email, contact support.</p></div>
    <div><label for="account_type">I am a…</label><select id="account_type" name="account_type"><?php foreach (ACCOUNT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= $u['account_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  </div>
  <button class="btn btn-primary mt" type="submit">Save details</button>
</form>
<form method="post" action="<?= e(url('settings.php')) ?>" class="card form-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="password">
  <h3 class="mt-0">Change password</h3>
  <div class="form-grid">
    <div><label for="current">Current password</label><input id="current" name="current" type="password" autocomplete="current-password"></div>
    <div><label for="new">New password</label><input id="new" name="new" type="password" minlength="8" autocomplete="new-password"></div>
  </div>
  <button class="btn btn-primary mt" type="submit">Change password</button>
</form>
<?php if (!$u['is_admin']): ?>
<form method="post" action="<?= e(url('settings.php')) ?>" class="card form-card danger-zone" id="delete" data-confirm-ok="Delete my account" data-confirm="Delete your LamazonLoads account for good? Your profile, documents and applications will be removed. This can’t be undone.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_account">
  <h3 class="mt-0"><?= icon('trash') ?> Delete my account</h3>
  <p class="muted">This removes your account, driver profile, uploaded documents, applications and chats. It can’t be undone. We’ll email you to confirm.</p>
  <div class="form-grid">
    <div><label for="confirm_password">Type your password to confirm</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="current-password" required></div>
  </div>
  <button class="btn btn-danger mt" type="submit">Delete my account</button>
</form>
<?php endif; ?>
<?php dash_close(); page_footer();
