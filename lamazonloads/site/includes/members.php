<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Admin → Drivers & members:
 *  - Add a record: a driver your team knows who has no account yet. Nothing is created or emailed; when they sign up
 *    with that email and confirm it, the record joins their account (record_merge()).
 *  - Add staff member: the staff account is created and confirmed straight away, they get their sign-in details by
 *    email and choose their own password the first time they sign in.
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
    if ($p['insurance_expires'] !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $p['insurance_expires'], $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])
        || $d[1] < 2000 || $d[1] > 2099)) $errors[] = 'Please pick a valid insurance expiry date.'; // a real day (not Feb 31), or the database refuses it
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
 * Creates a staff account (Drivers & members → Add staff member): email already confirmed, and a generated password they
 * must change at first sign-in. $v: first_name, last_name, email, role (moderator / admin). Returns [user id, password].
 */
function create_staff(array $v, int $staffId): array
{
    $pass = temp_password();
    db_run("INSERT INTO users (name, email, phone, password_hash, account_type, is_admin, staff_role, email_verified_at, must_change_password, added_by, created_at)
        VALUES (?, ?, '', ?, 'other', 1, ?, NOW(), 1, ?, NOW())",
        [trim($v['first_name'] . ' ' . $v['last_name']), $v['email'], password_hash($pass, PASSWORD_DEFAULT), $v['role'], $staffId]);
    $id = (int) db()->lastInsertId();
    record_merge($id); // a record with this email (see below) moves onto the account
    return [$id, $pass];
}

/* ---------- Records: drivers your team knows who don't have an account yet ---------- */

/** What the record says about onboarding: '' = not yet, or the program they finished with our team. */
function record_onboarded_label(string $track): string
{
    return isset(ONB_TRACKS[$track]) ? 'Onboarded with our team: ' . ONB_TRACKS[$track] : 'Not onboarded yet';
}

/** A record that hasn't joined an account yet, by email. */
function record_by_email(string $email): ?array
{
    return db_one('SELECT * FROM member_records WHERE email = ? AND merged_user_id IS NULL', [strtolower(trim($email))]);
}

/**
 * The "Add a record" / "Edit record" form, checked. Fills $v (first_name, last_name, email, phone, city, account_type,
 * vehicle, vehicle_other, onboarded, notes) and returns [errors, the member or record that already has this email].
 */
function record_from_post(array &$v, int $recordId = 0): array
{
    foreach (['first_name' => 50, 'last_name' => 50, 'email' => 190, 'phone' => 25, 'account_type' => 30, 'onboarded' => 20] as $k => $max) {
        $v[$k] = post_line($k, $max);
    }
    $v['email'] = strtolower($v['email']);
    $v['phone'] = $v['phone'] !== '' ? format_phone($v['phone']) : '';
    $v['notes'] = post('notes', 2000);
    [$vehicles, $v['vehicle_other']] = posted_vehicles();
    $v['vehicle'] = implode(',', $vehicles);
    $city = post('city', 120);
    $errors = [];
    $dup = null;
    if ($v['first_name'] === '') $errors[] = 'Please enter their first name.';
    if (!email_valid($v['email'])) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($u = db_one('SELECT id, name, is_admin FROM users WHERE email = ?', [$v['email']])) {
        $dup = ['type' => 'member', 'id' => (int) $u['id'], 'name' => (string) $u['name'], 'staff' => (bool) $u['is_admin']];
    } elseif ($r = db_one('SELECT id, first_name, last_name FROM member_records WHERE email = ? AND id <> ?', [$v['email'], $recordId])) {
        $dup = ['type' => 'record', 'id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name'])];
    }
    if ($v['phone'] !== '' && !preg_match('/^[0-9+()\-. ]{7,25}$/', $v['phone'])) $errors[] = 'Please enter a valid phone number, or leave it empty.';
    if (!isset(ACCOUNT_TYPES[$v['account_type']])) $errors[] = 'Please choose what describes them best.';
    if ($city === '' || $city === $v['city']) { // a city saved before stays as it is
        $v['city'] = $city;
    } elseif (($found = us_city($city)) === null) {
        $errors[] = 'Please choose their city from the list, or leave it empty.';
        $v['city'] = $city;
    } else {
        $v['city'] = $found;
    }
    if (in_array('other', $vehicles, true) && $v['vehicle_other'] === '') $errors[] = 'Please type what their other vehicle is.';
    if (!isset(ONB_TRACKS[$v['onboarded']])) $v['onboarded'] = '';
    return [$errors, $dup];
}

/** "This email is already…" message for the record form, with a link to that member or record. */
function record_dup_html(array $dup): string
{
    $link = url($dup['type'] === 'member' ? 'admin/driver.php?id=' . $dup['id'] : 'admin/record.php?id=' . $dup['id']);
    return $dup['type'] === 'member'
        ? 'This email is already ' . ($dup['staff'] ? 'a staff account' : 'a member') . ': <a href="' . e($link) . '">' . e($dup['name']) . '</a>. Open their page to add information.'
        : 'You already have a record for this email: <a href="' . e($link) . '">' . e($dup['name']) . '</a>.';
}

/** The fields of the "Add a record" and "Edit record" forms. $p keeps the field ids unique on the page. */
function record_fields_html(array $v, string $p = 'rc'): string
{
    $types = '';
    foreach (ACCOUNT_TYPES as $k => $l) {
        $types .= '<option value="' . e($k) . '"' . ($v['account_type'] === $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    $onb = '<option value="">Not yet</option>';
    foreach (ONB_TRACKS as $k => $l) {
        $onb .= '<option value="' . e($k) . '"' . ($v['onboarded'] === $k ? ' selected' : '') . '>Already onboarded with our team: ' . e($l) . '</option>';
    }
    $in = fn (string $name, string $label, string $type, int $max, string $extra = '') => '<label for="' . $p . '-' . $name . '">' . $label . '</label>'
        . '<input id="' . $p . '-' . $name . '" name="' . $name . '" type="' . $type . '" maxlength="' . $max . '" autocomplete="off" value="' . e((string) $v[$name]) . '"' . $extra . '>';
    return '<div>' . $in('first_name', 'First name', 'text', 50, ' required') . '</div>'
        . '<div>' . $in('last_name', 'Last name', 'text', 50) . '</div>'
        . '<div class="full">' . $in('email', 'Email', 'email', 190, ' required') . '</div>'
        . '<div>' . $in('phone', 'Phone <span class="opt">(optional)</span>', 'tel', 25, ' placeholder="(555) 123-4567"') . '</div>'
        . '<div><label for="' . $p . '-type">I am a…</label><select id="' . $p . '-type" name="account_type" data-drives="owner_operator,driver">' . $types . '</select></div>'
        . '<div class="full">' . city_picker('city', (string) $v['city'], $p . '-city', 'City and state (optional)') . '</div>'
        . vehicle_picker(user_vehicles($v), (string) $v['vehicle_other'], 'full', !account_drives((string) $v['account_type']), 'Vehicles, if they have any', $p . '-vehicle-other')
        . '<div class="full"><label for="' . $p . '-onb">Onboarding</label><select id="' . $p . '-onb" name="onboarded">' . $onb . '</select>'
        . '<p class="hint">Choose “Already onboarded” for drivers who finished onboarding with your team. When they sign up, they skip the uploads.</p></div>'
        . '<div class="full"><label for="' . $p . '-notes">Notes <span class="opt">(private, only your team sees them)</span></label>'
        . '<textarea id="' . $p . '-notes" name="notes" maxlength="2000" rows="3" placeholder="e.g. Ran Walmart routes in Tampa in 2025. Prefers morning starts.">' . e((string) $v['notes']) . '</textarea></div>';
}

/**
 * Someone with a record's email now has a confirmed account: the record moves onto it. What they typed when they signed up
 * stays (it's the newest); the record fills in what's empty, adds the vehicles they didn't tick, and marks them onboarded
 * when our team onboarded them. The record stays as "From your records" on their page, notes included.
 */
function record_merge(int $userId): bool
{
    $u = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    $r = $u ? record_by_email((string) $u['email']) : null;
    if (!$r) {
        return false;
    }
    $vehicles = array_values(array_intersect(array_keys(APPLY_VEHICLES), array_merge(user_vehicles($u), user_vehicles($r))));
    $other = trim((string) $u['vehicle_other']) !== '' ? (string) $u['vehicle_other'] : (in_array('other', $vehicles, true) ? (string) $r['vehicle_other'] : '');
    db_run("UPDATE users SET name = IF(TRIM(name) = '', ?, name), phone = IF(phone = '', ?, phone), city = IF(city = '', ?, city), vehicle = ?, vehicle_other = ? WHERE id = ?",
        [trim($r['first_name'] . ' ' . $r['last_name']), $r['phone'], $r['city'], implode(',', $vehicles), $other, $userId]);
    if (!$u['is_admin'] && isset(ONB_TRACKS[$r['onboarded']]) && !is_onboarded(onboarding_row($userId)['stage'] ?? null)) {
        onboarding_mark_done($userId, (string) $r['onboarded'], (int) ($r['added_by'] ?? 0));
    }
    db_run('UPDATE member_records SET merged_user_id = ?, merged_at = NOW(), updated_at = NOW() WHERE id = ?', [$userId, $r['id']]);
    return true;
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
    // The onboarding emails staff sent them and the reminders log, so nothing treats them as "emailed, no account yet"
    // (which would start sign-up reminders). A "Stop reminders" choice is kept.
    $email = (string) db_val('SELECT email FROM users WHERE id = ?', [$userId]);
    db_run('DELETE FROM onboarding_emails WHERE user_id = ? OR email = ?', [$userId, $email]);
    db_run('DELETE FROM followup_log WHERE user_id = ? OR email = ?', [$userId, $email]);
    db_run('DELETE FROM users WHERE id = ?', [$userId]); // profile, documents, applications, saved jobs and their record go with it
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
