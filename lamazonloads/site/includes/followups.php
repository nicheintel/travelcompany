<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Automatic follow-ups (Admin → Automatic follow-ups). They run with the other automations: from cron.php every
 * 30 minutes, or right after a page has loaded when no scheduled task is set up (see run_automations_if_due()).
 *  - Drivers who stall during sign-up or onboarding get a short series of reminders, which stops as soon as they act.
 *  - Staff get one morning summary of what's waiting.
 * Rules: at most one follow-up per person a day, only between 9 am and 7 pm New York time, 20 emails a run,
 * nothing about a stage that began before the series could have run (its last day plus 4), and never to anyone
 * who tapped "Stop reminders" or was paused by staff.
 */

/** kind => [name, when it applies, days after the stage began (default)] */
const FOLLOWUPS = [
    'verify' => ['Email not confirmed', 'Signed up, but hasn’t confirmed their email yet.', '1,3'],
    'open' => ['Onboarding link not opened', 'Got the onboarding email, but hasn’t opened their upload page.', '1,3'],
    'docs' => ['Documents not finished', 'Opened their onboarding page, but hasn’t sent everything for review.', '2,5,10'],
    'changes' => ['Changes requested, not fixed', 'Your team asked for changes, and they haven’t resubmitted.', '2,5'],
    'sign' => ['Agreement not signed', 'Approved, but hasn’t signed the driver agreement.', '1,3'],
    'invite' => ['Emailed by your team, no account yet', 'You sent them an onboarding email, and they haven’t signed up.', '3,7'],
    'signin' => ['Added by your team, never signed in', 'You added their account, and they haven’t signed in.', '3'],
];
const FOLLOWUP_BATCH = 20;           // emails per run at most
const FOLLOWUP_HOURS = [9, 19];      // sent between 9:00 and 18:59, New York time

/** The settings from Admin → Automatic follow-ups. */
function followup_settings(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $s = [
        'on' => meta_get('fu_on', '1') === '1',
        'summary' => meta_get('fu_summary_on', '1') === '1',
        'summary_to' => meta_get('fu_summary_to', support_email()),
        'decline' => meta_get('fu_decline_on', '0') === '1',
        'decline_days' => max(1, min(30, (int) meta_get('fu_decline_days', '5'))),
        'kinds' => [],
    ];
    foreach (FOLLOWUPS as $k => [, , $days]) {
        $s['kinds'][$k] = ['on' => meta_get('fu_' . $k . '_on', '1') === '1', 'days' => followup_days(meta_get('fu_' . $k . '_days', $days))];
    }
    return $s;
}

/** "1, 3" → [1, 3]: up to three different days from 1 to 30, in order. */
function followup_days(string $v): array
{
    $d = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', $v) ?: []), fn ($n) => $n >= 1 && $n <= 30)));
    sort($d);
    return array_slice($d, 0, 3);
}

/** "Day 1 and day 3" */
function followup_days_label(array $days): string
{
    $d = array_map(fn ($n) => 'day ' . $n, $days);
    $last = array_pop($d);
    return ucfirst($d ? implode(', ', $d) . ' and ' . $last : (string) $last);
}

/* ---------- "Stop reminders" ---------- */

function followup_secret(): string
{
    $s = meta_get('fu_secret');
    if (!preg_match('/^[a-f0-9]{48}$/', $s)) {
        $s = bin2hex(random_bytes(24));
        meta_set('fu_secret', $s);
    }
    return $s;
}

/** The code in each "Stop reminders" link, so only the person who got the email can use it. */
function followup_stop_token(string $email): string
{
    return substr(hash_hmac('sha256', strtolower(trim($email)), followup_secret()), 0, 32);
}

function followup_stop_link(string $email): string
{
    return abs_url('reminders.php?e=' . rawurlencode(strtolower(trim($email))) . '&t=' . followup_stop_token($email));
}

function followup_opted_out(string $email): bool
{
    return (bool) db_val('SELECT 1 FROM followup_optout WHERE email = ?', [strtolower(trim($email))]);
}

/** Stop ($off) or allow automatic follow-ups for an address. $staffId: paused by staff on the member's page. */
function followup_set_optout(string $email, bool $off, ?int $staffId = null): void
{
    $email = strtolower(trim($email));
    if ($off) {
        db_run('INSERT INTO followup_optout (email, by_staff, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE by_staff = VALUES(by_staff)', [$email, $staffId]);
    } else {
        db_run('DELETE FROM followup_optout WHERE email = ?', [$email]);
    }
}

/* ---------- Who is due ---------- */

/** People at a stage that began in the last $window days. Each row: user_id, email, name, anchor (when the stage began), extras. */
function followup_candidates(string $kind, int $window): array
{
    $since = 'NOW() - INTERVAL ' . max(1, $window) . ' DAY';
    // Onboarding reminders need a signed-in driver: accounts your team added that never signed in get "signin" instead
    $member = 'u.is_admin = 0 AND u.email_verified_at IS NOT NULL AND (u.added_by IS NULL OR u.last_login_at IS NOT NULL)';
    $onb = 'SELECT u.id AS user_id, u.email, u.name, %s AS anchor%s FROM onboarding o JOIN users u ON u.id = o.user_id WHERE ' . $member . ' AND %s';
    return match ($kind) {
        'verify' => db_all("SELECT u.id AS user_id, u.email, u.name, u.created_at AS anchor FROM users u
            WHERE u.is_admin = 0 AND u.email_verified_at IS NULL AND u.added_by IS NULL AND u.created_at >= $since"),
        'open' => db_all(sprintf($onb, 'o.created_at', '', "o.stage = 'documents' AND o.opened_at IS NULL AND o.created_at >= $since")),
        'docs' => db_all(sprintf($onb, 'o.opened_at', '', "o.stage = 'documents' AND o.opened_at IS NOT NULL AND o.opened_at >= $since")),
        'changes' => db_all(sprintf($onb, 'o.reviewed_at', ', o.review_note', "o.stage = 'changes' AND o.reviewed_at >= $since")),
        'sign' => db_all(sprintf($onb, 'o.approved_at', '', "o.stage = 'contract' AND o.approved_at >= $since")),
        'invite' => db_all("SELECT NULL AS user_id, m.email, m.first_name AS name, m.type, m.city, m.sent_at AS anchor FROM onboarding_emails m
            WHERE m.user_id IS NULL AND m.sent_at >= $since AND m.id = (SELECT MAX(m2.id) FROM onboarding_emails m2 WHERE m2.email = m.email)
            AND NOT EXISTS (SELECT 1 FROM users x WHERE x.email = m.email)"),
        'signin' => db_all("SELECT u.id AS user_id, u.email, u.name, u.created_at AS anchor FROM users u
            WHERE u.is_admin = 0 AND u.added_by IS NOT NULL AND u.last_login_at IS NULL AND u.created_at >= $since"),
        default => [],
    };
}

/* ---------- The emails (wording chosen by LamazonLoads: 1A, 2B–7B) ---------- */

/**
 * [subject, paragraphs, button, link, footnote] for one follow-up. $c is the person (see followup_candidates()),
 * $final: the last email of its series. $v: what changes per email (a fresh confirm link, a new temporary password),
 * or sample values for the preview in admin.
 */
function followup_email(string $kind, array $c, bool $final, array $v = []): array
{
    $first = trim((string) $c['name']) !== '' ? first_name((string) $c['name']) : 'there';
    $uid = (int) ($c['user_id'] ?? 0);
    $bye = "Best regards,\nLamazonLoads Team";
    $onbLink = $v['onb_link'] ?? ($uid ? onboarding_link($uid) : abs_url('onboarding.php'));
    $signIn = 'You’ll need to sign in to your LamazonLoads account.';
    switch ($kind) {
        case 'verify':
            return ['Confirm your email to finish signing up', [
                "Hi $first,",
                'You’re almost in! Tap below to confirm your email, then you can apply and start onboarding right away.',
                "Talk soon,\nLamazonLoads Team",
            ], 'Confirm my email', $v['link'] ?? abs_url('verify.php'), 'This link works for ' . VERIFY_HOURS . ' hours.'];
        case 'open':
            $applied = $v['applied'] ?? ($uid && db_val('SELECT 1 FROM applications WHERE user_id = ? LIMIT 1', [$uid]));
            return ['Action needed: complete your onboarding', [
                "Hello $first,",
                ($applied ? 'Thank you for your application.' : 'Thank you for your interest in LamazonLoads.')
                    . ' To move forward, please open your onboarding page and upload the required documents. Drivers who complete onboarding are prioritized for available loads and routes.',
                $bye,
            ], 'Upload my documents', $onbLink, $signIn];
        case 'docs':
            $missing = $v['missing'] ?? array_values(array_map(fn ($i) => $i[0], array_filter(onboarding_items($uid), fn ($i) => !$i[2])));
            $paras = ["Hello $first,"];
            if ($missing) {
                $paras[] = 'We have not yet received the following items needed to complete your onboarding:';
                $paras[] = '• ' . implode("\n• ", $missing);
                $paras[] = 'Please upload them at your earliest convenience so our team can review your file.';
            } else { // everything is uploaded, only "Submit for review" is left
                $paras[] = 'All your documents are uploaded. Please open your onboarding page and tap “Submit for review” so our team can review your file.';
            }
            if ($final) {
                $paras[] = 'This is our last reminder. Whenever you’re ready, upload the remaining items and we’ll pick it up from there.';
            }
            $paras[] = $bye;
            return ['Your onboarding is incomplete', $paras, 'Finish my onboarding', $onbLink, $signIn];
        case 'changes':
            $note = trim((string) ($c['review_note'] ?? ''));
            return ['Action required: update to your documents', array_values(array_filter([
                "Hello $first,",
                'Our team has reviewed your onboarding documents and requested the following update:',
                $note !== '' ? '“' . $note . '”' : '',
                'Please make the change and resubmit for review.',
                $bye,
            ])), 'Update my documents', $onbLink, $signIn];
        case 'sign':
            return ['Reminder: please sign your driver agreement', [
                "Hello $first,",
                'Your documents have been approved. The final step is to review and sign your driver agreement online. Once signed, we will share your next steps.',
                $bye,
            ], 'Sign my agreement', $onbLink, $signIn];
        case 'invite':
            $program = ($c['type'] ?? '') === 'walmart'
                ? 'Walmart Daily Route program' . (trim((string) ($c['city'] ?? '')) !== '' ? ' in ' . $c['city'] : '')
                : 'Professional Dispatch program';
            return ['Following up on your LamazonLoads onboarding', [
                "Hello $first,",
                "We are following up on our previous email about the $program. To proceed, please create your LamazonLoads account and upload your documents.",
                $bye,
            ], 'Create my account', abs_url('register.php?email=' . rawurlencode((string) $c['email']) . '&next=onboarding.php'), ''];
        case 'signin':
            return ['Reminder: sign in to your LamazonLoads account', [
                "Hello $first,",
                'Your LamazonLoads account is ready, but you have not signed in yet. For your convenience, here are new sign-in details:',
                'Email: ' . $c['email'] . "\nTemporary password: " . ($v['password'] ?? 'a new one is created for each email'),
                'You will be asked to choose your own password when you sign in.',
                $bye,
            ], 'Sign in', abs_url('login.php?email=' . rawurlencode((string) $c['email'])), 'For your security, don’t share this email.'];
    }
    return ['', [], '', '', ''];
}

/** [subject, text, html] of a follow-up, the button placed before the sign-off. */
function followup_render(string $kind, array $c, bool $final, array $v = []): array
{
    [$subject, $paras, $button, $link, $foot] = followup_email($kind, $c, $final, $v);
    [$text, $html] = email_body('', $paras, $button, $link, $foot, true, count($paras) - 2, followup_stop_link((string) $c['email']));
    return [$subject, $text, $html];
}

/** Sends one follow-up. A fresh confirm link or temporary password only replaces the old one once the email went out. */
function followup_send(string $kind, array $c, bool $final): bool
{
    $v = [];
    if ($kind === 'verify') {
        $raw = bin2hex(random_bytes(32));
        $v['link'] = abs_url('verify.php?t=' . $raw);
    } elseif ($kind === 'signin') {
        $v['password'] = temp_password();
    }
    [$subject, $text, $html] = followup_render($kind, $c, $final, $v);
    if (!send_mail((string) $c['email'], $subject, $text, $html, support_email())) {
        return false;
    }
    if ($kind === 'verify') {
        db_run('UPDATE users SET verify_token = ?, verify_expires = NOW() + INTERVAL ' . VERIFY_HOURS . ' HOUR, verify_sent_at = NOW() WHERE id = ?',
            [hash('sha256', $raw), $c['user_id']]);
    } elseif ($kind === 'signin') {
        db_run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($v['password'], PASSWORD_DEFAULT), $c['user_id']]);
    }
    return true;
}

/* ---------- The run ---------- */

/** Sends what's due. Returns counts for the log. */
function run_followups(): array
{
    $s = followup_settings();
    $done = ['sent' => 0, 'failed' => 0, 'declined' => 0, 'summary' => 0];
    if ($s['summary']) {
        $done['summary'] = followup_summary_if_due();
    }
    if (!$s['on']) {
        return $done;
    }
    if ($s['decline']) {
        $done['declined'] = followup_decline();
    }
    $hour = (int) date('G');
    if ($hour < FOLLOWUP_HOURS[0] || $hour >= FOLLOWUP_HOURS[1]) {
        return $done;
    }
    $budget = FOLLOWUP_BATCH;
    foreach ($s['kinds'] as $kind => $k) {
        if (!$k['on'] || !$k['days']) {
            continue;
        }
        foreach (followup_candidates($kind, (int) end($k['days']) + 4) as $c) {
            if ($budget <= 0) {
                break 2;
            }
            $email = strtolower(trim((string) $c['email']));
            $at = strtotime((string) $c['anchor']);
            if (!email_valid($email) || !$at || followup_opted_out($email)) {
                continue;
            }
            $due = 0; // the latest step whose day has come (one email, never a catch-up burst)
            foreach ($k['days'] as $i => $d) {
                if (time() >= $at + $d * 86400) $due = $i + 1;
            }
            $last = (int) db_val('SELECT MAX(step) FROM followup_log WHERE kind = ? AND email = ? AND anchor = ?', [$kind, $email, $c['anchor']]);
            if ($due <= $last || db_val('SELECT 1 FROM followup_log WHERE email = ? AND sent_at > NOW() - INTERVAL 20 HOUR LIMIT 1', [$email])) {
                continue;
            }
            $budget--;
            if (followup_send($kind, ['email' => $email] + $c, $due === count($k['days']))) {
                db_run('INSERT IGNORE INTO followup_log (kind, email, user_id, step, anchor, sent_at) VALUES (?, ?, ?, ?, ?, NOW())',
                    [$kind, $email, $c['user_id'], $due, $c['anchor']]);
                $done['sent']++;
            } else {
                $done['failed']++;
            }
        }
    }
    return $done;
}

/** Optional: applications still "Received" some days after the last documents reminder become "Not selected". */
function followup_decline(): int
{
    $s = followup_settings();
    $last = count($s['kinds']['docs']['days']);
    if (!$last) {
        return 0;
    }
    $n = 0;
    $rows = db_all("SELECT DISTINCT l.user_id FROM followup_log l JOIN onboarding o ON o.user_id = l.user_id
        WHERE l.kind = 'docs' AND l.step = ? AND l.sent_at <= NOW() - INTERVAL " . $s['decline_days'] . " DAY AND o.stage = 'documents'", [$last]);
    foreach ($rows as $r) {
        foreach (db_all("SELECT id FROM applications WHERE user_id = ? AND status = 'new'", [$r['user_id']]) as $a) {
            db_run("UPDATE applications SET status = 'not_selected', updated_at = NOW() WHERE id = ?", [$a['id']]);
            app_auto_note((int) $a['id'], 'Not selected (onboarding not finished after the last reminder)');
            $n++;
        }
    }
    return $n;
}

/** The staff's morning summary: once a day between 8 am and noon New York time, only when something is waiting. */
function followup_summary_if_due(): int
{
    $hour = (int) date('G');
    if ($hour < 8 || $hour >= 12 || meta_get('fu_summary_day') === date('Y-m-d')) {
        return 0;
    }
    meta_set('fu_summary_day', date('Y-m-d'));
    [$subject, $text, $html] = followup_summary_email();
    if ($subject === '') {
        return 0;
    }
    $sent = 0;
    foreach (emails_in(followup_settings()['summary_to']) ?: emails_in(support_email()) as $to) {
        $sent += send_mail($to, $subject, $text, $html) ? 1 : 0;
    }
    return $sent ? 1 : 0;
}

/** [subject, text, html], or subject '' when nothing is waiting. */
function followup_summary_email(): array
{
    $apps = db_one("SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM applications WHERE status = 'new'");
    $review = (int) db_val("SELECT COUNT(*) FROM onboarding WHERE stage = 'review'");
    $chats = chat_unread_total();
    $msgs = (int) db_val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
    $partners = (int) db_val("SELECT COUNT(*) FROM partner_requests WHERE status = 'new'");
    $signups = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE()');
    $n = (int) $apps['n'];
    $old = $n ? (int) floor((time() - strtotime((string) $apps['oldest'])) / 86400) : 0;
    $p = fn (int $k, string $one, string $many) => $k . ' ' . ($k === 1 ? $one : $many);
    $items = array_filter([
        $n ? $p($n, 'new application', 'new applications') . ($old >= 1 ? ' (oldest: ' . $p($old, 'day', 'days') . ')' : '') : '',
        $review ? $p($review, 'driver ready for review', 'drivers ready for review') : '',
        $chats ? $p($chats, 'unanswered support chat', 'unanswered support chats') : '',
        $msgs ? $p($msgs, 'unread contact message', 'unread contact messages') : '',
        $partners ? $p($partners, 'partner request not contacted', 'partner requests not contacted') : '',
    ]);
    if (!$items) {
        return ['', '', ''];
    }
    $head = array_filter([$n ? $p($n, 'new application', 'new applications') : '', $review ? $review . ' to review' : '',
        $chats ? $p($chats, 'chat', 'chats') : '', $msgs ? $p($msgs, 'message', 'messages') : '', $partners ? $p($partners, 'partner request', 'partner requests') : '']);
    $items[] = $p($signups, 'new sign-up', 'new sign-ups') . ' yesterday';
    [$text, $html] = email_body('Good morning!', ['Here’s what’s waiting:', '• ' . implode("\n• ", $items)], 'Open admin', abs_url('admin/'), '', true, 1);
    return ['Today at LamazonLoads: ' . implode(', ', array_slice($head, 0, 2)), $text, $html];
}

/** A sample of each email for Admin → Automatic follow-ups: [subject, html]. */
function followup_preview(string $kind): array
{
    $c = ['user_id' => 0, 'email' => 'marcus.lee@example.com', 'name' => 'Marcus Lee', 'anchor' => date('Y-m-d H:i:s'),
        'review_note' => 'Your insurance certificate has expired. Please upload your current one.', 'type' => 'walmart', 'city' => 'Tampa, FL'];
    $v = ['link' => abs_url('verify.php?t=…'), 'onb_link' => abs_url('onboarding.php'), 'applied' => true,
        'missing' => ['Proof of insurance', 'Payment details'], 'password' => 'Kx7m-Pq4t-Wz2r'];
    $days = followup_settings()['kinds'][$kind]['days'] ?? [];
    [$subject, , $html] = followup_render($kind, $c, count($days) === 1, $v);
    return [$subject, $html];
}
