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
    return [(int) db()->lastInsertId(), $pass];
}

/** "Your LamazonLoads account is ready": sign-in email and the generated password. */
function send_member_welcome(array $u, string $pass): bool
{
    $first = trim((string) strtok((string) $u['name'], ' ')) ?: 'there';
    [$text, $html] = email_body('Your LamazonLoads account is ready', [
        "Hi $first,",
        'Welcome to LamazonLoads! We created your driver account so you can check your onboarding, documents and applications in one place.',
        'The first time you sign in, we’ll ask you to choose your own password.',
        "Your sign-in details:\n\nEmail: {$u['email']}\nPassword: $pass",
    ], 'Sign in to LamazonLoads', abs_url('login.php?email=' . rawurlencode((string) $u['email'])),
        'For your security, don’t share this email. If you weren’t expecting it, reply and let us know.');
    return send_mail((string) $u['email'], 'Your LamazonLoads account is ready', $text, $html, support_email());
}
