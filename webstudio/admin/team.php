<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
$myId = (int) $me['id'];
$self = url('admin/team.php');
$errors = [];
$old = [];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $current = (string) ($_POST['current_password'] ?? '');
    $myRow = db_one('SELECT * FROM admins WHERE id = ?', [$myId]);

    if ($action === 'profile') {
        $name = post('name');
        $email = normalize_email(post('email'));
        $old = ['name' => $name, 'email' => $email];
        if (!len_between($name, 2, 80)) $errors['name'] = 'Please enter your name.';
        if (!valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
        elseif ($email !== $myRow['email'] && db_value('SELECT 1 FROM admins WHERE email = ?', [$email])) $errors['email'] = 'Another admin already uses that email.';
        elseif ($email !== $myRow['email'] && !password_verify($current, $myRow['password_hash'])) $errors['current_password'] = 'Enter your current password to change your email.';
        if (!$errors) {
            db_run('UPDATE admins SET name = ?, email = ? WHERE id = ?', [$name, $email, $myId]);
            flash('Your details are saved.');
            redirect($self);
        }
    }

    if ($action === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $myRow['password_hash'])) $errors['pw_current'] = 'That isn\'t your current password.';
        elseif ($p = password_problem($new)) $errors['new_password'] = $p;
        elseif ($new !== (string) ($_POST['new_password2'] ?? '')) $errors['new_password2'] = 'The two new passwords don\'t match.';
        if (!$errors) {
            // A new password signs you out everywhere else; this browser stays signed in.
            db_run('UPDATE admins SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $myId]);
            $_SESSION['admin_ver'] = (int) db_value('SELECT session_version FROM admins WHERE id = ?', [$myId]);
            session_regenerate_id(true);
            flash('Password changed. Any other devices were signed out.');
            redirect($self);
        }
    }

    if ($action === 'add') {
        $name = post('name');
        $email = normalize_email(post('email'));
        $pw = (string) ($_POST['password'] ?? '');
        $old = ['add_name' => $name, 'add_email' => $email];
        if (!len_between($name, 2, 80)) $errors['add_name'] = 'Please enter their name.';
        if (!valid_email($email)) $errors['add_email'] = 'Please enter a valid email address.';
        elseif (db_value('SELECT 1 FROM admins WHERE email = ?', [$email])) $errors['add_email'] = 'That email already has access.';
        if ($p = password_problem($pw)) $errors['add_password'] = $p;
        if (!$errors) {
            db_insert('INSERT INTO admins (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), now_utc()]);
            flash("$name can now sign in at " . app_url() . '/admin/ — share the password with them privately.');
            redirect($self);
        }
    }

    $target = (int) post('admin_id');
    if ($action === 'reset' && $target !== $myId && db_value('SELECT 1 FROM admins WHERE id = ?', [$target])) {
        $pw = (string) ($_POST['password'] ?? '');
        if ($p = password_problem($pw)) {
            flash($p, 'error');
        } else {
            db_run('UPDATE admins SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $target]);
            flash('New password set. They were signed out and can sign in with the new password.');
        }
        redirect($self);
    }
    if ($action === 'remove' && $target !== $myId) {
        db_run('DELETE FROM admins WHERE id = ?', [$target]);
        flash('Access removed.');
        redirect($self);
    }
}

$admins = db_all('SELECT id, name, email, created_at, last_login_at FROM admins ORDER BY id');
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';

admin_start('Team & password', 'team');
?>
<div class="team-grid">
  <div>
    <section class="card">
      <h2 class="card-title">Team members</h2>
      <p class="muted small">Everyone here can sign in to this dashboard and see all requests.</p>
      <ul class="team-list">
        <?php foreach ($admins as $a): ?>
          <li>
            <span class="avatar"><?= e(initials($a['name'])) ?></span>
            <div class="tm-text">
              <b><?= e($a['name']) ?><?= (int) $a['id'] === $myId ? ' <span class="badge badge-blue">You</span>' : '' ?></b>
              <small><?= e($a['email']) ?> · <?= $a['last_login_at'] ? 'last signed in ' . e(time_ago($a['last_login_at'])) : 'hasn\'t signed in yet' ?></small>
            </div>
            <?php if ((int) $a['id'] !== $myId): ?>
              <details class="tm-menu">
                <summary class="icon-btn" aria-label="Options for <?= e($a['name']) ?>"><?= icon('sliders') ?></summary>
                <div class="tm-pop">
                  <form method="post" class="stack">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="admin_id" value="<?= (int) $a['id'] ?>">
                    <label class="small" for="rp-<?= (int) $a['id'] ?>">Set a new password for <?= e(first_name($a['name'])) ?></label>
                    <input id="rp-<?= (int) $a['id'] ?>" type="text" name="password" autocomplete="off" required minlength="8">
                    <button class="btn btn-ghost btn-sm" type="submit">Set password</button>
                  </form>
                  <form method="post" data-confirm="Remove <?= e($a['name']) ?>'s access to the dashboard?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="admin_id" value="<?= (int) $a['id'] ?>">
                    <button class="btn btn-danger-ghost btn-sm btn-block" type="submit"><?= icon('trash') ?> Remove access</button>
                  </form>
                </div>
              </details>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">Add a team member</h2>
      <form method="post" class="form-grid" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="field"><label for="add_name">Name</label><input id="add_name" name="name" type="text" maxlength="80" value="<?= e($old['add_name'] ?? '') ?>"<?= $inv('add_name') ?>><?= $err('add_name') ?></div>
        <div class="field"><label for="add_email">Email</label><input id="add_email" name="email" type="email" value="<?= e($old['add_email'] ?? '') ?>"<?= $inv('add_email') ?>><?= $err('add_email') ?></div>
        <div class="field field-full"><label for="add_password">Their password</label><input id="add_password" name="password" type="text" autocomplete="off"<?= $inv('add_password') ?>><small class="help">At least 8 characters with a letter and a number. Share it privately; they can change it after signing in.</small><?= $err('add_password') ?></div>
        <div class="field-full"><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Add team member</button></div>
      </form>
    </section>
  </div>

  <div>
    <section class="card">
      <h2 class="card-title">Your details</h2>
      <form method="post" class="stack" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="field"><label for="p_name">Name</label><input id="p_name" name="name" type="text" maxlength="80" value="<?= e($old['name'] ?? $me['name']) ?>"<?= $inv('name') ?>><?= $err('name') ?></div>
        <div class="field"><label for="p_email">Email (used to sign in)</label><input id="p_email" name="email" type="email" value="<?= e($old['email'] ?? $me['email']) ?>"<?= $inv('email') ?>><?= $err('email') ?></div>
        <div class="field"><label for="p_current">Current password <span class="opt">(only to change your email)</span></label><input id="p_current" name="current_password" type="password" autocomplete="current-password"<?= $inv('current_password') ?>><?= $err('current_password') ?></div>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save details</button>
      </form>
    </section>

    <section class="card">
      <h2 class="card-title">Change your password</h2>
      <form method="post" class="stack" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field"><label for="pw_current">Current password</label><input id="pw_current" name="current_password" type="password" autocomplete="current-password"<?= $inv('pw_current') ?>><?= $err('pw_current') ?></div>
        <div class="field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" autocomplete="new-password"<?= $inv('new_password') ?>><small class="help">At least 8 characters, with a letter and a number.</small><?= $err('new_password') ?></div>
        <div class="field"><label for="new_password2">New password again</label><input id="new_password2" name="new_password2" type="password" autocomplete="new-password"<?= $inv('new_password2') ?>><?= $err('new_password2') ?></div>
        <button class="btn btn-primary" type="submit"><?= icon('lock') ?> Change password</button>
      </form>
    </section>
  </div>
</div>
<?php admin_end();
