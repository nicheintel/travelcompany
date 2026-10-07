<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/* "Partner with us": businesses request a call. Saved in partner_requests; staff follow up in Admin -> Partner requests. */

const PARTNER_SERVICES = [
    'last_mile'  => 'Last-Mile Delivery',
    'healthcare' => 'Healthcare Delivery Solutions',
    'dedicated'  => 'Dedicated Fleet & Driver Services',
    'multiple'   => 'More than one / not sure yet',
];

const PARTNER_VOLUMES = [
    'under50'  => 'Under 50 deliveries a week',
    '50-200'   => '50 to 200 a week',
    '200-1000' => '200 to 1,000 a week',
    '1000+'    => 'More than 1,000 a week',
    'unknown'  => 'Not sure yet',
];

const PARTNER_TIMES = ['morning' => 'Morning (8–12)', 'afternoon' => 'Afternoon (12–5)', 'evening' => 'Evening (5–7)', 'any' => 'Any time'];

const PARTNER_STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'talks' => 'In talks', 'partner' => 'Partner', 'not_fit' => 'Not a fit'];

/** Saves a request and sends the emails. Returns [id or 0, list of errors]. */
function partner_submit(array $in): array
{
    $f = [];
    foreach (['first_name' => 60, 'last_name' => 60, 'company' => 120, 'job_title' => 100, 'email' => 190, 'phone' => 30,
        'service' => 20, 'location' => 120, 'volume' => 20, 'best_time' => 20, 'message' => 3000] as $k => $max) {
        $f[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
    }
    $f['email'] = strtolower($f['email']);
    $f['phone'] = format_phone($f['phone']);
    $errors = [];
    if ($f['first_name'] === '' || $f['last_name'] === '') $errors[] = 'Please enter your first and last name.';
    if ($f['company'] === '') $errors[] = 'Please enter your company name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid business email.';
    } elseif (domain_is_disposable(email_domain($f['email']))) {
        $errors[] = 'Please use your company or permanent email address.';
    }
    if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $f['phone'])) $errors[] = 'Please enter a phone number we can call.';
    if (!isset(PARTNER_SERVICES[$f['service']])) $errors[] = 'Please choose the service you are interested in.';
    if ($f['volume'] !== '' && !isset(PARTNER_VOLUMES[$f['volume']])) $f['volume'] = '';
    if ($f['best_time'] !== '' && !isset(PARTNER_TIMES[$f['best_time']])) $f['best_time'] = '';
    if (empty($in['consent'])) $errors[] = 'Please confirm we may contact you about your request.';
    if (!$errors && rate_limited('partner', limit_ip(), 5, 3600)) $errors[] = 'We already received several requests from your connection. Please call us instead.';
    if ($errors) {
        return [0, $errors];
    }
    record_hit('partner', limit_ip());
    db_run('INSERT INTO partner_requests (first_name, last_name, company, job_title, email, phone, service, location, volume, best_time, message, ip, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
        [$f['first_name'], $f['last_name'], $f['company'], $f['job_title'], $f['email'], $f['phone'], $f['service'], $f['location'], $f['volume'], $f['best_time'], $f['message'], client_ip()]);
    $id = (int) db()->lastInsertId();

    // Alert to staff
    $who = $f['first_name'] . ' ' . $f['last_name'] . ($f['job_title'] !== '' ? ', ' . $f['job_title'] : '') . ' at ' . $f['company'];
    $lines = [
        $who . ' would like to talk about ' . PARTNER_SERVICES[$f['service']] . '.',
        'Phone: ' . $f['phone'] . ' · Email: ' . $f['email']
            . ($f['best_time'] !== '' ? ' · Best time: ' . PARTNER_TIMES[$f['best_time']] : '')
            . ($f['location'] !== '' ? ' · Location: ' . $f['location'] : '')
            . ($f['volume'] !== '' ? ' · Volume: ' . PARTNER_VOLUMES[$f['volume']] : ''),
        $f['message'] !== '' ? $f['message'] : 'No message.',
    ];
    [$text, $html] = email_body('New partner request', $lines, 'Open partner requests', abs_url('admin/partners.php?id=' . $id));
    send_mail(support_email(), 'Partner request: ' . $f['company'] . ' – ' . PARTNER_SERVICES[$f['service']], $text, $html, $f['email']);

    // Thank-you to the business
    $phone = (string) config('contact_phone');
    [$text, $html] = email_body('Thanks for reaching out', [
        'Hi ' . $f['first_name'] . ',',
        'Thank you for your interest in partnering with LamazonLoads. We received your request about ' . PARTNER_SERVICES[$f['service']] . ' and our partnerships team will contact you to schedule a call.',
        'Your request: ' . ($f['message'] !== '' ? $f['message'] : PARTNER_SERVICES[$f['service']]),
    ], 'Visit LamazonLoads', abs_url('partners.php'),
        $phone !== '' ? "Need us sooner? Call $phone or just reply to this email." : 'Need us sooner? Just reply to this email.');
    send_mail($f['email'], 'We received your request – LamazonLoads', $text, $html, support_email());

    return [$id, []];
}
