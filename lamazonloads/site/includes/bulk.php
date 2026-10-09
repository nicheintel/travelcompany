<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Send onboarding email → Send to a list (admins). Paste first names and emails, check the list, and the scheduled task
 * (cron.php, or a page visit when it isn't set up) sends them in the background: a few each run between 9 am and 7 pm
 * New York time, up to the daily amount chosen on the page, and never past the mailbox's daily limit (it keeps 150 free
 * for sign-up emails and the emails you send by hand).
 */

const BULK_MAX_LINES = 2000;      // one paste at a time
const BULK_RECENT_DAYS = 30;      // someone emailed this recently isn't added again
const BULK_KEEP_FREE = 150;       // of the mailbox's daily limit

/** Emails a day the list sends (the setting on the page; 300 by default). */
function bulk_per_day(): int
{
    return max(25, min(900, (int) meta_get('bulk_per_day', '300')));
}

/**
 * The pasted list: one person per line, "first name, email" (or "email, first name", or just the email; tabs from a
 * spreadsheet work too). Returns [people as [email, first name], lines without an email].
 */
function bulk_parse(string $text): array
{
    $people = [];
    $bad = [];
    foreach (array_slice(preg_split('/\R/u', $text) ?: [], 0, BULK_MAX_LINES) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $line, $m)) {
            if (!preg_match('/^(first\s*name|name|email)\b/i', $line)) $bad[] = $line; // a header row isn't a mistake
            continue;
        }
        $name = trim((string) preg_replace(['/[,;\t<>"|]+/', '/\s+/'], ' ', str_replace($m[0], ' ', $line)));
        $first = first_name($name, '');
        if ($first !== '' && ($first === mb_strtolower($first) || $first === mb_strtoupper($first))) {
            $first = mb_convert_case($first, MB_CASE_TITLE); // "marcus" or "MARCUS" → "Marcus"
        }
        $people[] = [strtolower($m[0]), mb_substr($first, 0, 60)];
    }
    return [$people, $bad];
}

/** Why someone shouldn't get the onboarding email now, or '' when they should. $days: how recent an earlier email counts. */
function bulk_skip_reason(string $email, int $days): string
{
    if (!email_valid($email)) return 'Not a valid email';
    if (db_val('SELECT 1 FROM followup_optout WHERE email = ? AND by_staff IS NULL', [$email])) return 'Unsubscribed';
    $u = db_one('SELECT id, is_admin FROM users WHERE email = ?', [$email]);
    if ($u && $u['is_admin']) return 'Staff account';
    if ($u && is_onboarded(onboarding_row((int) $u['id'])['stage'] ?? null)) return 'Already onboarded';
    if (db_val('SELECT 1 FROM onboarding_emails WHERE email = ? AND sent_at > NOW() - INTERVAL ' . $days . ' DAY LIMIT 1', [$email])) {
        return 'Emailed in the last ' . $days . ' days';
    }
    return '';
}

/** Checks a pasted list: [ready people, skipped as [email or line, reason]]. */
function bulk_check(array $people, array $bad): array
{
    $ready = [];
    $skipped = array_map(fn ($l) => [mb_strimwidth($l, 0, 80, '…'), 'No email on this line'], $bad);
    $seen = [];
    foreach ($people as [$email, $first]) {
        if (isset($seen[$email])) {
            $skipped[] = [$email, 'Listed twice'];
            continue;
        }
        $seen[$email] = true;
        $why = db_val("SELECT 1 FROM onboarding_queue WHERE email = ? AND status = 'waiting'", [$email]) ? 'Already on the sending list'
            : bulk_skip_reason($email, BULK_RECENT_DAYS);
        if ($why !== '') {
            $skipped[] = [$email, $why];
        } else {
            $ready[] = [$email, $first];
        }
    }
    return [$ready, $skipped];
}

/** Sends one Dispatch or Walmart email (by hand or from the list) and records it. False when the mail server refused it. */
function onboarding_email_send(string $email, string $type, string $city, string $first, int $staffId): bool
{
    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($user) { // their onboarding page asks for this email's documents (unless staff already reviewed them)
        $row = onboarding_row((int) $user['id']);
        if (!$row) {
            onboarding_start((int) $user['id'], $type);
        } elseif ($row['track'] !== $type && in_array($row['stage'], ['documents', 'changes'], true)) {
            db_run('UPDATE onboarding SET track = ?, updated_at = NOW() WHERE user_id = ?', [$type, $user['id']]);
        }
    }
    [$subject, $text, $html] = manual_onboarding_email($email, $type, $city, $first, $user);
    if (!send_mail($email, $subject, $text, $html, support_email())) {
        return false;
    }
    db_run('INSERT INTO onboarding_emails (email, user_id, type, city, first_name, had_account, sent_by, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
        [$email, $user['id'] ?? null, $type, $city, $first, $user ? 1 : 0, $staffId ?: null]);
    return true;
}

/** Sends the next few from the list (called with the other automations). */
function bulk_run(): array
{
    $done = ['list_sent' => 0, 'list_failed' => 0];
    $hour = (int) date('G');
    if (meta_get('bulk_paused') === '1' || $hour < FOLLOWUP_HOURS[0] || $hour >= FOLLOWUP_HOURS[1]) {
        return $done;
    }
    $perDay = bulk_per_day();
    $sent24 = (int) db_val("SELECT COUNT(*) FROM onboarding_queue WHERE status = 'sent' AND sent_at > NOW() - INTERVAL 24 HOUR");
    // spread over the day (two runs an hour from 9 to 7), never past the daily amount or the mailbox's limit
    $n = min((int) ceil($perDay / 20), $perDay - $sent24, mail_daily_limit() - BULK_KEEP_FREE - mail_sent_24h());
    if ($n <= 0) {
        return $done;
    }
    $rows = db_all("SELECT * FROM onboarding_queue WHERE status = 'waiting' AND (last_try_at IS NULL OR last_try_at < NOW() - INTERVAL 20 HOUR)
        ORDER BY id LIMIT " . (int) $n);
    foreach ($rows as $r) {
        if (meta_get('bulk_paused') === '1') {
            break; // paused while this run was sending
        }
        $why = bulk_skip_reason((string) $r['email'], 7); // things change while people wait: they sign up, get emailed by hand…
        if ($why !== '') {
            db_run("UPDATE onboarding_queue SET status = 'skipped', note = ?, last_try_at = NOW() WHERE id = ?", [$why, $r['id']]);
            continue;
        }
        if (onboarding_email_send((string) $r['email'], (string) $r['type'], (string) $r['city'], (string) $r['first_name'], (int) $r['added_by'])) {
            db_run("UPDATE onboarding_queue SET status = 'sent', note = '', sent_at = NOW(), last_try_at = NOW(), attempts = attempts + 1 WHERE id = ?", [$r['id']]);
            $done['list_sent']++;
        } else { // the mail server refused it: tried again the next day, three times at most
            db_run("UPDATE onboarding_queue SET attempts = attempts + 1, last_try_at = NOW(), note = ?, status = IF(attempts >= 3, 'failed', 'waiting') WHERE id = ?",
                [mb_substr(mail_last_error() ?: 'The mail server didn’t accept it', 0, 190), $r['id']]);
            $done['list_failed']++;
        }
    }
    return $done;
}
