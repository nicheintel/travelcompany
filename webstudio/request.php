<?php
/** Receives the "Request your website" form from the home page. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect(url() . '#quote');
}
verify_csrf();

// Bots fill in every field, including the hidden one people never see: pretend it worked.
if (post('company_website') !== '') {
    redirect(url('track.php', ['sent' => 1]));
}

$in = [
    'name' => mb_substr(post('name'), 0, 200),
    'email' => normalize_email(mb_substr(post('email'), 0, 250)),
    'phone' => mb_substr(post('phone'), 0, 60),
    'business_name' => mb_substr(post('business_name'), 0, 200),
    'business_type' => post('business_type'),
    'package' => post('package'),
    'budget' => post('budget'),
    'timeline' => post('timeline'),
    'website_url' => mb_substr(post('website_url'), 0, 300),
    'message' => mb_substr(post('message'), 0, 4000),
];

$errors = [];
if (!len_between($in['name'], 2, 80)) $errors['name'] = 'Please enter your name.';
if (!valid_email($in['email'])) $errors['email'] = 'Please enter a valid email address, so we can reply.';
if ($in['phone'] !== '' && !preg_match('/^[0-9+()\-.\s]{6,30}$/', $in['phone'])) $errors['phone'] = 'Please enter a valid phone number (digits, spaces, + and dashes).';
if (mb_strlen($in['business_name']) > 100) $errors['business_name'] = 'Please keep the business name under 100 characters.';
$website = clean_url($in['website_url']);
if ($website === null) $errors['website_url'] = 'That doesn\'t look like a web address. Example: facebook.com/yourbusiness';
if (!len_between($in['message'], 10, 3000)) $errors['message'] = 'Please tell us a little about your idea (at least 10 characters).';

// Only accept choices that are on the form.
$businessType = in_array($in['business_type'], BUSINESS_TYPES, true) ? $in['business_type'] : '';
$budget = in_array($in['budget'], BUDGETS, true) ? $in['budget'] : '';
$timeline = in_array($in['timeline'], TIMELINES, true) ? $in['timeline'] : '';
$packageId = null;
$packageName = '';
if ($in['package'] === 'custom') {
    $packageName = 'Custom project';
} elseif (ctype_digit($in['package'])) {
    $pkg = db_one('SELECT id, name FROM packages WHERE id = ? AND is_active = 1', [(int) $in['package']]);
    if ($pkg) {
        $packageId = (int) $pkg['id'];
        $packageName = $pkg['name'];
    }
}

if ($errors) {
    $_SESSION['quote_old'] = $in;
    $_SESSION['quote_errors'] = $errors;
    redirect(url() . '#quote');
}

// At most 5 requests per hour from one internet connection (stops spam floods).
if (ip_throttled('request', 5, 3600)) {
    $_SESSION['quote_old'] = $in;
    $_SESSION['quote_errors'] = ['message' => 'You\'ve sent several requests already. Please wait a while, or contact us directly.'];
    redirect(url() . '#quote');
}

$now = now_utc();
$token = bin2hex(random_bytes(16));
$id = db_insert(
    'INSERT INTO requests (ref, track_token, name, email, phone, business_name, business_type, package_id, package_name, budget, timeline, website_url, message, status, ip, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [new_request_ref(), $token, $in['name'], $in['email'], $in['phone'], $in['business_name'], $businessType, $packageId, $packageName,
     $budget, $timeline, (string) $website, $in['message'], 'new', client_ip(), $now, $now],
);
add_event($id, null, 'created', 'Request sent from the website.');

redirect(url('track.php', ['t' => $token, 'new' => 1]));
