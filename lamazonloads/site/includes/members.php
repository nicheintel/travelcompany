<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Members added by staff (Admin → Drivers & members → Add member): the account is created and confirmed straight away,
 * the member gets their sign-in details by email, and chooses their own password the first time they sign in.
 * Staff can also fill in a member's details and add the documents they emailed in (marked "Added by LamazonLoads staff").
 */

/** Driver profile fields and their maximum lengths (the member's Driver profile page and the admin edit form). */
const PROFILE_FIELDS = ['company_name' => 120, 'mc_number' => 20, 'dot_number' => 20, 'equipment' => 30, 'vehicle' => 120, 'home_zip' => 10,
    'service_radius' => 40, 'availability' => 30, 'years_experience' => 10, 'insurance_provider' => 120, 'insurance_expires' => 10, 'about' => 2000];

function profile_row(int $userId): array
{
    return db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$userId]) ?? array_fill_keys(array_keys(PROFILE_FIELDS), '');
}

/** Reads the profile fields from the posted form into $p and checks them. Returns a list of problems. */
function profile_from_post(array &$p): array
{
    foreach (PROFILE_FIELDS as $k => $max) {
        $p[$k] = post($k, $max);
    }
    $errors = [];
    if ($p['equipment'] !== '' && !isset(EQUIPMENT[$p['equipment']])) $errors[] = 'Please choose the equipment from the list.';
    if ($p['availability'] !== '' && !isset(AVAILABILITY[$p['availability']])) $errors[] = 'Please choose the availability from the list.';
    if ($p['home_zip'] !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $p['home_zip'])) $errors[] = 'Please enter a 5-digit ZIP code.';
    if ($p['mc_number'] !== '' && !preg_match('/^(MC-?)?\d{1,8}$/i', $p['mc_number'])) $errors[] = 'MC number should be digits only (e.g. 123456).';
    if ($p['dot_number'] !== '' && !preg_match('/^\d{1,9}$/', $p['dot_number'])) $errors[] = 'USDOT number should be digits only.';
    if ($p['insurance_expires'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['insurance_expires'])) $errors[] = 'Please pick a valid insurance expiry date.';
    return $errors;
}

function profile_save(int $userId, array $p): void
{
    $keys = array_keys(PROFILE_FIELDS);
    $vals = array_map(fn ($k) => $k === 'insurance_expires' && $p[$k] === '' ? null : $p[$k], $keys);
    $cols = implode(', ', $keys);
    $marks = implode(', ', array_fill(0, count($keys), '?'));
    $upd = implode(', ', array_map(fn ($k) => "$k = VALUES($k)", $keys));
    db_run("INSERT INTO driver_profiles (user_id, $cols, updated_at) VALUES (?, $marks, NOW()) ON DUPLICATE KEY UPDATE $upd, updated_at = NOW()",
        array_merge([$userId], $vals));
    auto_review_user($userId);
}

function select_html(string $name, array $opts, string $cur, string $empty = 'Choose…'): string
{
    $h = '<select id="' . e($name) . '" name="' . e($name) . '"><option value="">' . e($empty) . '</option>';
    foreach ($opts as $k => $l) {
        $h .= '<option value="' . e((string) $k) . '"' . ($cur === (string) $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h . '</select>';
}

/** A password that's easy to read out and type: three groups of four, no look-alike letters (e.g. "Kp7m-Tx4q-Wr9z"). */
function temp_password(): string
{
    $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $chunk = '';
        for ($i = 0; $i < 4; $i++) {
            $set = $i === 2 ? $digits : $letters;
            $chunk .= $set[random_int(0, strlen($set) - 1)];
        }
        $groups[] = $chunk;
    }
    return implode('-', $groups);
}

/**
 * Creates a member for staff: email already confirmed, a generated password they must change at first sign-in.
 * $v: first_name, last_name, email, phone, account_type. Returns [user id, password].
 */
function create_member(array $v, int $staffId): array
{
    $pass = temp_password();
    db_run('INSERT INTO users (name, email, phone, password_hash, account_type, is_admin, email_verified_at, must_change_password, added_by, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW(), 1, ?, NOW())',
        [trim($v['first_name'] . ' ' . $v['last_name']), $v['email'], $v['phone'], password_hash($pass, PASSWORD_DEFAULT), $v['account_type'], $staffId]);
    $id = (int) db()->lastInsertId();
    onboarding_apply_invite(db_one('SELECT * FROM users WHERE id = ?', [$id]) ?? []); // an onboarding email went out before the account existed
    return [$id, $pass];
}

/** "Your LamazonLoads account is ready": sign-in email and the generated password. */
function send_member_welcome(array $u, string $pass): bool
{
    $first = first_name((string) $u['name']);
    $intro = !empty($u['is_admin'])
        ? 'Welcome to the LamazonLoads team! We created your staff account (' . (STAFF_ROLES[staff_role($u)] ?? 'Staff') . '). After you sign in, open Admin from the menu to get to your tools.'
        : 'Welcome to LamazonLoads! We created your driver account so you can check your onboarding, documents and applications in one place.';
    [$text, $html] = email_body('Your LamazonLoads account is ready', [
        "Hi $first,",
        $intro,
        'The first time you sign in, we’ll ask you to choose your own password.',
        "Your sign-in details:\n\nEmail: {$u['email']}\nPassword: $pass",
    ], 'Sign in to LamazonLoads', abs_url('login.php?email=' . rawurlencode((string) $u['email'])),
        'For your security, don’t share this email. If you weren’t expecting it, reply and let us know.');
    return send_mail((string) $u['email'], 'Your LamazonLoads account is ready', $text, $html, support_email());
}

/** Removes a member for good: their uploaded files, chats, profile, documents and applications. */
function delete_member(int $userId): void
{
    foreach (db_all('SELECT stored_name FROM documents WHERE user_id = ?', [$userId]) as $d) {
        @unlink(dirname(__DIR__) . '/uploads/' . basename((string) $d['stored_name']));
    }
    chat_delete_for_user($userId);
    db_run('DELETE FROM users WHERE id = ?', [$userId]); // profile, documents, applications and saved jobs go with it
}

/**
 * The goodbye email. $how: 'staff' (we closed it) or 'self' (they deleted it in Account settings).
 * Sent before the account is removed, so it still has their name and email.
 */
function send_account_closed(array $u, string $how): bool
{
    $first = first_name((string) $u['name']);
    $self = $how === 'self';
    [$text, $html] = email_body($self ? 'Your account was deleted' : 'Your account has been closed', [
        "Hi $first,",
        $self
            ? 'As you asked, we deleted your LamazonLoads account, along with your driver profile, uploaded documents and applications.'
            : 'Your LamazonLoads account has been closed, and your driver profile, uploaded documents and applications were deleted.',
        $self
            ? 'Thanks for riding with us. You’re always welcome back: just create a new account any time.'
            : 'If this was a mistake, or you have questions, just reply to this email and we’ll help.',
    ], null, null, $self ? 'Didn’t do this? Reply to this email or call us right away.' : '');
    return send_mail((string) $u['email'], $self ? 'Your LamazonLoads account was deleted' : 'Your LamazonLoads account has been closed',
        $text, $html, support_email());
}

/** Lets staff know a member deleted their own account. */
function notify_staff_account_deleted(array $u): void
{
    $docs = (int) db_val('SELECT COUNT(*) FROM documents WHERE user_id = ?', [$u['id']]);
    $apps = (int) db_val('SELECT COUNT(*) FROM applications WHERE user_id = ?', [$u['id']]);
    [$text, $html] = email_body('A member deleted their account', [
        $u['name'] . ' deleted their LamazonLoads account.',
        'Email: ' . $u['email'] . ($u['phone'] !== '' ? ' · Phone: ' . $u['phone'] : '') . "\nMember since " . fmt_date((string) $u['created_at'])
            . " · $docs document" . ($docs === 1 ? '' : 's') . " · $apps application" . ($apps === 1 ? '' : 's') . ' (all deleted)',
    ]);
    foreach (emails_in(support_email()) as $addr) {
        send_mail($addr, 'Account deleted: ' . $u['name'], $text, $html, (string) $u['email']);
    }
}
