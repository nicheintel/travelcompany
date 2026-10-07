<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Driver onboarding, start to finish, on the website:
 *   1. They apply and get the Dispatch or Walmart email with an "Upload my documents" button.
 *   2. Signed in, they upload vehicle photos, W-9, insurance, driver's license and add their payment details, then submit.
 *   3. Staff review in Admin: Approve, or Request changes (the driver gets an email with the note).
 *   4. Approved: if that track has a contract (Admin → Contracts), they sign it online; otherwise they go straight to step 5.
 *   5. Done: they get the Telegram link (and QR code) for @LLDriverOnboarding.
 */

const ONB_TRACKS = ['dispatch' => 'Professional Dispatch', 'walmart' => 'Walmart Daily Route'];
const ONB_STAGES = [
    'documents' => 'Uploading documents',
    'review' => 'Ready for review',
    'changes' => 'Changes requested',
    'contract' => 'Waiting for signature',
    'done' => 'Complete',
];
/** What every driver uploads or fills in before review. */
const ONB_ITEMS = [
    'vehicle_photo' => ['Pictures of your vehicle', 'Clear photos of the outside and the cargo area. You can add several.'],
    'w9' => ['W-9 form', 'Filled out and signed. A blank W-9 is free at irs.gov.'],
    'insurance' => ['Proof of insurance', 'Your certificate of insurance (COI) or insurance card.'],
    'license' => ['Driver’s license', 'A clear photo of the front.'],
    'payout' => ['Payment details', 'How we pay you. Zelle is preferred.'],
];
const PAYOUT_METHODS = [
    'zelle' => 'Zelle',
    'cashapp' => 'Cash App',
    'applepay' => 'Apple Pay',
    'direct_deposit' => 'Direct deposit',
];
const TELEGRAM_DEFAULT = 'https://t.me/LLDriverOnboarding';

function onboarding_row(int $userId): ?array
{
    return db_one('SELECT * FROM onboarding WHERE user_id = ?', [$userId]);
}

/** Starts onboarding for a member (or switches a not-yet-approved one to Dispatch when they now qualify). */
function onboarding_start(int $userId, string $track): void
{
    if (!isset(ONB_TRACKS[$track])) {
        return;
    }
    $row = onboarding_row($userId);
    if (!$row) {
        db_run("INSERT INTO onboarding (user_id, track, stage, access_token, created_at, updated_at) VALUES (?, ?, 'documents', ?, NOW(), NOW())",
            [$userId, $track, bin2hex(random_bytes(16))]);
    } elseif ($row['track'] !== $track && $track === 'dispatch' && in_array($row['stage'], ['documents', 'changes', 'review'], true)) {
        db_run('UPDATE onboarding SET track = ?, updated_at = NOW() WHERE user_id = ?', [$track, $userId]);
    }
}

/**
 * Each driver's personal onboarding link (it goes in every onboarding email). Their onboarding page and its menu link
 * stay hidden until they open it from that email, so nobody skips the email.
 */
function onboarding_link(int $userId): string
{
    $tok = (string) db_val('SELECT access_token FROM onboarding WHERE user_id = ?', [$userId]);
    if (!preg_match('/^[a-f0-9]{32}$/', $tok)) {
        $tok = bin2hex(random_bytes(16));
        db_run('UPDATE onboarding SET access_token = ? WHERE user_id = ?', [$tok, $userId]);
    }
    return abs_url('onboarding.php?k=' . $tok);
}

/** Has the driver opened their onboarding from the email link yet? */
function onboarding_unlocked(?array $row): bool
{
    return $row !== null && !empty($row['opened_at']);
}

/** Emails the driver their onboarding link again (from the "Check your email" page or from Admin). */
function onboarding_send_link(array $u): bool
{
    [$text, $html] = email_body('Your onboarding link', [
        'Hi ' . onb_first($u) . ',',
        'Here is your personal link to finish your LamazonLoads onboarding: upload your vehicle photos, W-9, proof of insurance, driver’s license and payment details.',
    ], 'Upload my documents', onboarding_link((int) $u['id']), 'You’ll need to sign in to your LamazonLoads account.');
    return send_mail((string) $u['email'], 'Your LamazonLoads onboarding link', $text, $html, support_email());
}

function telegram_link(): string
{
    $l = meta_get('telegram_link', TELEGRAM_DEFAULT);
    return preg_match('#^https://(t\.me|telegram\.me)/[A-Za-z0-9_+/\-]+$#', $l) ? $l : TELEGRAM_DEFAULT;
}

function telegram_handle(): string
{
    return '@' . basename(parse_url(telegram_link(), PHP_URL_PATH) ?: 'LLDriverOnboarding');
}

/** The QR code image: the one uploaded in Admin → Onboarding, or the built-in one for the default link. */
function telegram_qr_url(): string
{
    $f = meta_get('telegram_qr');
    if ($f !== '' && is_file(dirname(__DIR__) . '/media/' . basename($f))) {
        return media_url(basename($f));
    }
    return telegram_link() === TELEGRAM_DEFAULT ? asset('brand/telegram-qr.png') : '';
}

function payout_label(?array $p): string
{
    if (!$p || ($p['payout_method'] ?? '') === '') {
        return '';
    }
    $m = PAYOUT_METHODS[$p['payout_method']] ?? $p['payout_method'];
    return $p['payout_method'] === 'direct_deposit' ? $m . ' (we’ll contact you to set it up)' : $m . ': ' . $p['payout_handle'];
}

/** Checks and saves the payment details form. Returns a list of problems. */
function payout_save(int $userId): array
{
    $method = post('payout_method', 20);
    $name = post('payout_name', 120);
    $handle = post('payout_handle', 190);
    $errors = [];
    if (!isset(PAYOUT_METHODS[$method])) $errors[] = 'Please choose how you want to be paid.';
    if ($name === '') $errors[] = 'Please enter the name on the account.';
    if ($method === 'direct_deposit') {
        $handle = '';
    } elseif ($method === 'cashapp') {
        $handle = '$' . ltrim($handle, '$ ');
        if (!preg_match('/^\$[A-Za-z][A-Za-z0-9_\-]{0,19}$/', $handle)) $errors[] = 'Please enter your $Cashtag, like $JohnDriver.';
    } elseif ($method !== '') {
        $isEmail = (bool) filter_var($handle, FILTER_VALIDATE_EMAIL);
        $phone = format_phone($handle);
        if (!$isEmail && !preg_match('/^\(\d{3}\) \d{3}-\d{4}$/', $phone)) {
            $errors[] = 'Please enter the email or US phone number linked to your ' . (PAYOUT_METHODS[$method] ?? 'account') . '.';
        } elseif (!$isEmail) {
            $handle = $phone;
        }
    }
    if (!$errors) {
        db_run('INSERT INTO driver_profiles (user_id, payout_method, payout_name, payout_handle, payout_updated_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE payout_method = VALUES(payout_method), payout_name = VALUES(payout_name), payout_handle = VALUES(payout_handle),
            payout_updated_at = NOW(), updated_at = NOW()', [$userId, $method, $name, $handle]);
    }
    return $errors;
}

/** The checklist: [key => ['label', 'hint', done, documents[]]]. */
function onboarding_items(int $userId): array
{
    $docs = db_all("SELECT * FROM documents WHERE user_id = ? AND kind IN ('vehicle_photo', 'w9', 'insurance', 'license') ORDER BY created_at, id", [$userId]);
    $p = db_one('SELECT payout_method, payout_name, payout_handle FROM driver_profiles WHERE user_id = ?', [$userId]);
    $out = [];
    foreach (ONB_ITEMS as $k => [$label, $hint]) {
        $mine = $k === 'payout' ? [] : array_values(array_filter($docs, fn ($d) => $d['kind'] === $k));
        $done = $k === 'payout' ? ($p && $p['payout_method'] !== '' && $p['payout_name'] !== '') : (bool) $mine;
        $out[$k] = [$label, $hint, $done, $mine];
    }
    return $out;
}

function onboarding_progress(int $userId): array
{
    $items = onboarding_items($userId);
    return [count(array_filter($items, fn ($i) => $i[2])), count($items)];
}

/** Contract for a track (Admin → Contracts). The Dispatch one starts with the LamazonLoads Owner-Operator Agreement. */
function contract_get(string $track): array
{
    $c = db_one('SELECT * FROM contracts WHERE track = ?', [$track]);
    if (!$c) {
        $seed = $track === 'dispatch'
            ? ['Lamazon Loads LLC Owner-Operator Agreement', DISPATCH_CONTRACT_SEED, 1]
            : ['Walmart Daily Route Agreement', '', 0];
        db_run('INSERT IGNORE INTO contracts (track, title, body, required, ask_emergency, version, updated_at) VALUES (?, ?, ?, ?, 1, 1, NOW())', [$track, ...$seed]);
        $c = db_one('SELECT * FROM contracts WHERE track = ?', [$track]);
    }
    return $c;
}

/** Does this track have a contract to sign right now? */
function contract_needed(string $track): bool
{
    $c = contract_get($track);
    return (int) $c['required'] === 1 && trim((string) $c['body']) !== '';
}

/** Fills in {driver_name}, {company_name}, {day}, {month}, {year}, {email}, {phone}. */
function contract_fill(string $text, array $u, string $company = '', ?int $time = null): string
{
    $t = $time ?? time();
    return strtr($text, [
        '{driver_name}' => (string) $u['name'],
        '{company_name}' => $company !== '' ? $company : 'N/A',
        '{day}' => date('jS', $t),
        '{month}' => date('F', $t),
        '{year}' => date('Y', $t),
        '{email}' => (string) $u['email'],
        '{phone}' => (string) ($u['phone'] ?? ''),
    ]);
}

/** Contract text → HTML. "# " starts a heading, "• " or "- " a bullet, an empty line a new paragraph. */
function contract_html(string $text): string
{
    $html = '';
    $list = false;
    $para = [];
    $flush = function () use (&$html, &$para) {
        if ($para) {
            $html .= '<p>' . implode('<br>', array_map('e', $para)) . '</p>';
            $para = [];
        }
    };
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $t = trim($line);
        if (preg_match('/^(?:•|-|\*)\s*(.+)$/u', $t, $m)) {
            $flush();
            if (!$list) { $html .= '<ul>'; $list = true; }
            $html .= '<li>' . e($m[1]) . '</li>';
            continue;
        }
        if ($list) { $html .= '</ul>'; $list = false; }
        if ($t === '') {
            $flush();
        } elseif (str_starts_with($t, '#')) {
            $flush();
            $html .= '<h3>' . e(trim(ltrim($t, '#'))) . '</h3>';
        } else {
            $para[] = $t;
        }
    }
    $flush();
    if ($list) $html .= '</ul>';
    return $html;
}

function onb_first(array $u): string
{
    return trim((string) strtok((string) $u['name'], ' ')) ?: 'there';
}

/** Emails staff (the support inbox). */
function onb_notify_staff(string $subject, array $lines, int $userId, string $replyTo = ''): void
{
    [$text, $html] = email_body($subject, $lines, 'Open in Admin', abs_url('admin/driver.php?id=' . $userId . '#onboarding'));
    foreach (emails_in(support_email()) as $addr) {
        send_mail($addr, $subject, $text, $html, $replyTo);
    }
}

/** The driver submits their checklist for review. */
function onboarding_submit(array $u): bool
{
    [$done, $total] = onboarding_progress((int) $u['id']);
    $row = onboarding_row((int) $u['id']);
    if (!$row || $done < $total || !in_array($row['stage'], ['documents', 'changes'], true)) {
        return false;
    }
    db_run("UPDATE onboarding SET stage = 'review', submitted_at = NOW(), updated_at = NOW() WHERE user_id = ?", [$u['id']]);
    if (meta_get('onboarding_notify', '1') === '1') {
        onb_notify_staff('Documents ready for review: ' . $u['name'], [
            $u['name'] . ' uploaded their onboarding documents for ' . ONB_TRACKS[$row['track']] . ' and is waiting for your review.',
            'Email: ' . $u['email'] . ($u['phone'] !== '' ? ' · Phone: ' . $u['phone'] : ''),
        ], (int) $u['id'], (string) $u['email']);
    }
    return true;
}

/** Staff approve: on to the contract, or straight to Telegram when the track has no contract. Returns the new stage. */
function onboarding_approve(int $userId, int $staffId): string
{
    $row = onboarding_row($userId);
    $u = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$row || !$u || !in_array($row['stage'], ['documents', 'review', 'changes'], true)) {
        return ''; // only before approval: a signed agreement is never sent out again this way
    }
    $stage = contract_needed($row['track']) ? 'contract' : 'done';
    db_run('UPDATE onboarding SET stage = ?, approved_at = NOW(), reviewed_at = NOW(), reviewed_by = ?, review_note = NULL, updated_at = NOW()'
        . ($stage === 'done' ? ', telegram_sent_at = NOW()' : '') . ' WHERE user_id = ?', [$stage, $staffId, $userId]);
    if ($stage === 'contract') {
        $c = contract_get($row['track']);
        [$text, $html] = email_body('You’re approved!', [
            'Hi ' . onb_first($u) . ',',
            'Great news: our team reviewed and approved your documents.',
            'The last step is to read and sign your ' . $c['title'] . ' online. It only takes a couple of minutes.',
        ], 'Review & sign', onboarding_link($userId), 'You’ll need to sign in to your LamazonLoads account.');
        send_mail((string) $u['email'], 'You’re approved! Please sign your LamazonLoads agreement', $text, $html, support_email());
    } else {
        onboarding_send_telegram($u, 'Great news: our team reviewed and approved your documents.');
    }
    return $stage;
}

/**
 * Staff mark a driver as onboarded by hand, e.g. someone they added who already finished onboarding with the team.
 * They skip the remaining steps; their onboarding page shows the Telegram step as complete.
 */
function onboarding_mark_done(int $userId, string $track, int $staffId): void
{
    if (!isset(ONB_TRACKS[$track])) {
        return;
    }
    onboarding_start($userId, $track);
    db_run("UPDATE onboarding SET track = ?, stage = 'done', approved_at = COALESCE(approved_at, NOW()), reviewed_at = NOW(), reviewed_by = ?, review_note = NULL,
        opened_at = COALESCE(opened_at, NOW()), marked_at = NOW(), marked_by = ?, updated_at = NOW() WHERE user_id = ?", [$track, $staffId, $staffId, $userId]);
}

/** The "Mark as onboarded" panel on a member's page (and its program choice). */
function onboarding_mark_form(string $action, string $track, string $name): string
{
    $opts = '';
    foreach (ONB_TRACKS as $k => $l) {
        $opts .= '<option value="' . e($k) . '"' . ($k === $track ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return '<details class="onb-changes onb-mark"><summary class="btn btn-ghost">' . icon('check') . ' Mark as onboarded</summary>'
        . '<form method="post" action="' . e($action) . '" data-confirm="Mark ' . e($name) . ' as onboarded? Any steps they haven’t finished are skipped." data-confirm-ok="Mark as onboarded">'
        . csrf_field() . '<input type="hidden" name="action" value="onb_mark_done">'
        . '<p class="hint mt-0">For drivers who finished onboarding with our team outside the website. They’ll show as Onboarded, and the job page asks them to message us on Telegram instead of applying.</p>'
        . '<label for="mark-track">Program</label><select id="mark-track" name="track">' . $opts . '</select>'
        . '<label class="check-inline mt"><input type="checkbox" name="notify" value="1"> Email them the Telegram link</label>'
        . '<button class="btn btn-primary btn-sm mt" type="submit">' . icon('check') . ' Mark as onboarded</button></form></details>';
}

function onboarding_send_telegram(array $u, string $lead): bool
{
    [$text, $html] = email_body('Welcome to LamazonLoads!', [
        'Hi ' . onb_first($u) . ',',
        $lead,
        'Your next step: join our driver onboarding group on Telegram (' . telegram_handle() . '). Our team will share your next steps there.',
    ], 'Join us on Telegram', telegram_link(), 'You can also find us by searching ' . telegram_handle() . ' in the Telegram app.');
    return send_mail((string) $u['email'], 'Welcome aboard! Join LamazonLoads on Telegram', $text, $html, support_email());
}

/** Staff ask for changes; the driver gets an email with the note. */
function onboarding_request_changes(int $userId, int $staffId, string $note): bool
{
    $u = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    $row = onboarding_row($userId);
    if (!$u || !$row || !in_array($row['stage'], ['documents', 'review', 'changes'], true)) {
        return false; // only before approval
    }
    db_run("UPDATE onboarding SET stage = 'changes', review_note = ?, reviewed_at = NOW(), reviewed_by = ?, updated_at = NOW() WHERE user_id = ?", [$note, $staffId, $userId]);
    [$text, $html] = email_body('Please update your documents', [
        'Hi ' . onb_first($u) . ',',
        'Thanks for sending your documents. Our team needs a few changes before we can approve you:',
        $note,
    ], 'Update my documents', onboarding_link($userId), 'Questions? Just reply to this email.');
    send_mail((string) $u['email'], 'Action needed: please update your LamazonLoads documents', $text, $html, support_email());
    return true;
}

/** The driver signs: keeps an exact copy of what they signed, then sends the Telegram link. Returns a list of problems. */
function onboarding_sign(array $u): array
{
    $row = onboarding_row((int) $u['id']);
    if (!$row || $row['stage'] !== 'contract') {
        return ['This agreement isn’t ready to sign.'];
    }
    $c = contract_get($row['track']);
    $name = post('signed_name', 120);
    $company = post('signed_company', 120);
    $sig = (string) ($_POST['signature'] ?? '');
    $em = ['emergency_name' => post('emergency_name', 120), 'emergency_relation' => post('emergency_relation', 60), 'emergency_phone' => format_phone(post('emergency_phone', 30))];
    $errors = [];
    if (mb_strlen($name) < 3) $errors[] = 'Please type your full name.';
    if (!str_starts_with($sig, 'data:image/png;base64,') || strlen($sig) > 400000
        || !($bin = base64_decode(substr($sig, 22), true)) || !str_starts_with($bin, "\x89PNG") || !@getimagesizefromstring($bin)) {
        $errors[] = 'Please draw your signature in the box.';
    }
    if ((int) $c['ask_emergency'] === 1) {
        if ($em['emergency_name'] === '' || $em['emergency_relation'] === '') $errors[] = 'Please add your emergency contact’s name and relationship.';
        if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $em['emergency_phone'])) $errors[] = 'Please add your emergency contact’s phone number.';
    }
    if (empty($_POST['agree'])) $errors[] = 'Please tick the box to agree to the terms.';
    if ($errors) {
        return $errors;
    }
    $now = time();
    $filled = contract_fill((string) $c['body'], ['name' => $name] + $u, $company, $now);
    db_run("UPDATE onboarding SET stage = 'done', contract_version = ?, contract_title = ?, contract_text = ?, signed_at = ?, signed_name = ?, signed_company = ?,
        signature = ?, signed_ip = ?, emergency_name = ?, emergency_relation = ?, emergency_phone = ?, telegram_sent_at = NOW(), updated_at = NOW() WHERE user_id = ?",
        [$c['version'], $c['title'], $filled, date('Y-m-d H:i:s', $now), $name, $company, $sig, client_ip(),
         $em['emergency_name'], $em['emergency_relation'], $em['emergency_phone'], $u['id']]);
    onboarding_send_telegram($u, 'Thank you for signing the ' . $c['title'] . '. You can view or print your signed copy any time: ' . abs_url('contract.php'));
    onb_notify_staff('Agreement signed: ' . $u['name'], [
        $u['name'] . ' signed the ' . $c['title'] . ' (version ' . (int) $c['version'] . ') and was sent the Telegram link.',
    ], (int) $u['id'], (string) $u['email']);
    return [];
}

/** Steps shown to the driver: [label, state] with state done / now / next. */
function onboarding_steps_view(array $row): array
{
    $hasContract = $row['stage'] === 'contract' || $row['signed_at'] || ($row['stage'] !== 'done' && contract_needed($row['track']));
    $order = ['upload' => 'Upload documents', 'review' => 'Review'];
    if ($hasContract) $order['sign'] = 'Sign agreement';
    $order['telegram'] = 'Join Telegram';
    $at = match ($row['stage']) { 'documents', 'changes' => 'upload', 'review' => 'review', 'contract' => 'sign', default => 'telegram' };
    $out = [];
    $passed = true;
    foreach ($order as $k => $label) {
        if ($k === $at) {
            $out[$k] = [$label, $row['stage'] === 'done' ? 'done' : 'now'];
            $passed = false;
        } else {
            $out[$k] = [$label, $passed ? 'done' : 'next'];
        }
    }
    return $out;
}

const DISPATCH_CONTRACT_SEED = <<<'TXT'
This Agreement is made and entered into on this {day} day of {month}, {year} by and between Lamazon Loads LLC (“Company”) and {driver_name} (“Owner-Operator”) of {company_name} (Company Name, if any).

# Independent Contractor Status
Owner-Operator shall always act as an independent contractor while performing services for Lamazon Loads LLC. The Company shall not provide social security, unemployment insurance, tax withholding, or employee benefits. Owner-Operator is solely responsible for taxes, insurance, permits, and driver compliance.

# Requirements
• Must be at least 21 years old
• Valid driver’s license
• Smartphone with internet access
• No DUI history preferred
• Professional communication and timely updates required

# Accepted Vehicles
• Cargo Vans
• Sprinter Vans
• Box Trucks
• Semi Trucks

# Payments & Dispatch Fees
Lamazon Loads LLC processes payments within 3–5 business days after load completion and required paperwork submission. Dispatch service fee is generally 10% per booked load unless otherwise agreed.

# Driver Responsibilities
• Provide pickup and delivery updates
• Submit BOL and POD documents
• Communicate delays immediately
• Maintain clean and safe cargo space
• Follow all broker and shipper instructions
• Keep communication professional with dispatchers and customers

# Strictly Prohibited
• Booking loads and refusing them without valid reason
• Failure to communicate during active loads
• Late deliveries without notice
• Unprofessional behavior toward customers or dispatch team
• Providing false location updates

# Detention, TONU & Layover
Detention, TONU, relocation, and layover fees are subject to broker approval and may vary depending on the load. Receipts may be required for reimbursements.

# Disclaimer
Lamazon Loads LLC is not responsible for damages caused at shipper or receiver locations. Failure to follow dispatch procedures may result in rate reduction or termination of this agreement.

By signing below, the Owner-Operator agrees to all terms and conditions stated in this agreement.
TXT;

/**
 * Drivers see the Documents page once onboarding is finished: until then their files show on the onboarding
 * checklist. Members with no onboarding see it when they have files (for example ones staff added).
 */
function documents_visible(array $u): bool
{
    $row = onboarding_row((int) $u['id']);
    if ($row && $row['stage'] !== 'done') {
        return false;
    }
    return $row !== null || (int) db_val('SELECT COUNT(*) FROM documents WHERE user_id = ?', [$u['id']]) > 0;
}

/** Onboarded = approved by staff in onboarding (signing the agreement or done). */
function is_onboarded(?string $stage): bool
{
    return in_array($stage, ['contract', 'done'], true);
}

/** Status badge for admin lists: Onboarded / In review / Uploading / Changes requested / Not started. */
function onboarding_badge(?string $stage): string
{
    return match ($stage) {
        'done' => '<span class="badge badge-onb-yes">' . icon('check') . 'Onboarded</span>',
        'contract' => '<span class="badge badge-onb-yes">' . icon('check') . 'Onboarded</span><small>Signing agreement</small>',
        'review' => '<span class="badge badge-stage-review">In review</span>',
        'changes' => '<span class="badge badge-stage-changes">Changes requested</span>',
        'documents' => '<span class="badge badge-stage-documents">Uploading documents</span>',
        default => '<span class="badge badge-onb-none">Not started</span>',
    };
}

