<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Job posts: display helpers, the apply settings, and automations.
 * Automations (set per job post in Admin -> Job posts):
 *   - welcome email to each new applicant
 *   - move applicants to "In review" once their onboarding is complete
 *   - remind applicants who haven't finished onboarding after N days (one email)
 *   - mark them "Not selected" if still not done N days after the reminder
 *   - close the post once enough applicants are Approved
 * There's no scheduled job on the hosting, so the time-based ones run at most every 30 minutes,
 * when someone opens a page.
 */

const DEFAULT_WELCOME = "Hi {first_name},\n\nThanks for applying for {job_title} at LamazonLoads! We've got your application.\n\nTo move forward fast, finish your onboarding in your dashboard: add your equipment, home ZIP code and availability, and upload your W-9, insurance and driver's license.\n\nTalk soon,\nThe LamazonLoads team";

/** Is the built-in "Join the driver network" application shown? Staff turn it on/off in Admin -> Job posts. */
function network_enabled(): bool
{
    static $on = null;
    return $on ??= db_val("SELECT v FROM meta WHERE k = 'network_job'") !== 'off';
}

/** The general "Join the driver network" application (job id 0). */
function network_job(): array
{
    return [
        'id' => 0, 'title' => 'Join the LamazonLoads driver network', 'category' => 'other', 'location' => 'USA',
        'equipment' => 'Cargo Van, Sprinter, Box Truck & more', 'pay' => 'Depends on load / route',
        'description' => "Apply once to join our network of drivers and owner-operators.\n\nComplete your onboarding, and when loads, daily routes or new openings match your equipment, ZIP code and availability, our team reaches out to you first.",
        'requirements' => "Qualified vehicle (cargo van, Sprinter, box truck or other)\nValid driver's license\nInsurance and W-9\nUp-to-date ZIP code and availability in your profile",
        'status' => 'open', 'hires_needed' => 'ongoing', 'country' => 'United States', 'language' => 'English', 'job_types' => 'full_time,part_time,contract',
        'apply_method' => 'site', 'apply_url' => '', 'resume' => 'optional', 'notify_on' => 0, 'notify_emails' => '', 'contact_by_email' => 0, 'contact_email' => '',
        'fair_chance' => 0, 'background_check' => 0, 'hiring_timeline' => '', 'auto_welcome' => 0, 'welcome_message' => '', 'auto_review' => 0,
        'auto_remind' => 0, 'remind_days' => 2, 'auto_decline' => 0, 'decline_days' => 5, 'auto_close' => 0,
    ];
}

function job_type_labels(array $job): array
{
    return array_values(array_intersect_key(JOB_TYPES, array_flip(array_filter(explode(',', (string) ($job['job_types'] ?? ''))))));
}

/** Start of the description, shortened (first paragraph, or the whole text with $whole), for job boxes and page intros. */
function job_excerpt(array $job, int $len = 160, bool $whole = false): string
{
    $desc = trim((string) ($job['description'] ?? ''));
    $first = $whole ? $desc : trim((string) (preg_split('/\R{2,}/', $desc)[0] ?? ''));
    if ($first === '') {
        $first = (string) ($job['summary'] ?? '');
    }
    return mb_strimwidth((string) preg_replace('/\s+/', ' ', $first), 0, $len, '…');
}

function job_hiring_label(array $job): string
{
    $n = (string) ($job['hires_needed'] ?? '1');
    if ($n === 'ongoing') {
        return 'Ongoing hiring';
    }
    if ($n === '10+') {
        return 'Hiring 10+ people';
    }
    return (int) $n > 1 ? 'Hiring ' . (int) $n . ' people' : 'Hiring 1 person';
}

function emails_in(string $list): array
{
    return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $list) ?: []), fn ($e) => (bool) filter_var($e, FILTER_VALIDATE_EMAIL)));
}

/** The member finished onboarding: profile + W-9 + insurance + license (everything except applying). */
function onboarding_complete(int $userId): bool
{
    foreach (onboarding_steps($userId) as [$label, $ok, $href]) {
        if (!$ok && $href !== 'careers.php') {
            return false;
        }
    }
    return true;
}

function onboarding_missing(int $userId): array
{
    $out = [];
    foreach (onboarding_steps($userId) as [$label, $ok, $href]) {
        if (!$ok && $href !== 'careers.php') {
            $out[] = $label;
        }
    }
    return $out;
}

/** Right after someone applies: alert staff, welcome the applicant, maybe move to In review. */
function on_new_application(int $appId): void
{
    $a = db_one('SELECT a.*, u.name, u.email, u.phone FROM applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$appId]);
    if (!$a) {
        return;
    }
    $job = (int) $a['job_id'] === 0 ? network_job() : db_one('SELECT * FROM jobs WHERE id = ?', [$a['job_id']]);
    if (!$job) {
        return;
    }
    // Application updates: email staff about each new application
    if ((int) $job['notify_on']) {
        $to = emails_in((string) $job['notify_emails']) ?: emails_in(support_email());
        $p = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$a['user_id']]);
        $lines = [$a['name'] . ' applied for ' . $job['title'] . '.',
            'Email: ' . $a['email'] . ' · Phone: ' . $a['phone']
            . ($p && $p['equipment'] !== '' ? ' · Equipment: ' . (EQUIPMENT[$p['equipment']] ?? $p['equipment']) : '')
            . ($p && $p['home_zip'] !== '' ? ' · ZIP: ' . $p['home_zip'] : '')];
        $lines[] = trim((string) $a['message']) !== '' ? mb_strimwidth((string) $a['message'], 0, 800, '…') : 'No message.';
        [$text, $html] = email_body('New application', $lines, 'See the applicant', abs_url('admin/driver.php?id=' . (int) $a['user_id']),
            $a['resume_doc_id'] ? 'A resume is attached to the application in your dashboard.' : '');
        foreach ($to as $addr) {
            send_mail($addr, 'New application: ' . $job['title'] . ': ' . $a['name'], $text, $html, (string) $a['email']);
        }
    }
    // Automation: welcome email
    if ((int) $job['auto_welcome']) {
        $msg = trim((string) $job['welcome_message']) !== '' ? (string) $job['welcome_message'] : DEFAULT_WELCOME;
        $msg = strtr($msg, ['{first_name}' => chat_first_name((string) $a['name']), '{job_title}' => (string) $job['title']]);
        [$text, $html] = email_body('Thanks for applying', [$msg], 'Finish my onboarding', abs_url('account.php'));
        if (send_mail((string) $a['email'], 'We got your application: ' . $job['title'], $text, $html, support_email())) {
            app_auto_note($appId, 'Welcome email sent');
        }
    }
    auto_review_user((int) $a['user_id']);
}

function app_auto_note(int $appId, string $note): void
{
    db_run('UPDATE applications SET auto_note = ? WHERE id = ?', [mb_substr('Auto: ' . $note . ' (' . date('M j') . ')', 0, 255), $appId]);
}

/** Automation: applications still "Received" move to "In review" once the applicant's onboarding is complete. */
function auto_review_user(int $userId): void
{
    if (!onboarding_complete($userId)) {
        return;
    }
    $apps = db_all("SELECT a.id FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.user_id = ? AND a.status = 'new' AND j.auto_review = 1", [$userId]);
    foreach ($apps as $a) {
        db_run("UPDATE applications SET status = 'reviewing', updated_at = NOW() WHERE id = ?", [$a['id']]);
        app_auto_note((int) $a['id'], 'moved to In review (onboarding complete)');
    }
}

/** Automation: close the post once the number of Approved applicants reaches the number to hire. */
function auto_close_job(int $jobId): bool
{
    $job = $jobId ? db_one("SELECT * FROM jobs WHERE id = ? AND status = 'open' AND auto_close = 1", [$jobId]) : null;
    if (!$job || !ctype_digit((string) $job['hires_needed'])) {
        return false;
    }
    $approved = (int) db_val("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = 'approved'", [$jobId]);
    if ($approved >= (int) $job['hires_needed']) {
        db_run("UPDATE jobs SET status = 'closed', updated_at = NOW() WHERE id = ?", [$jobId]);
        return true;
    }
    return false;
}

/** Time-based automations. Runs at most every 30 minutes, when someone opens a page. */
function run_automations_if_due(): void
{
    try {
        if (time() - (int) db_val("SELECT v FROM meta WHERE k = 'automations_ran'") < 1800) {
            return;
        }
        db_run("INSERT INTO meta (k, v) VALUES ('automations_ran', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string) time()]);
        run_automations();
    } catch (Throwable $e) {
        error_log('[automations] ' . $e->getMessage());
    }
}

function run_automations(): array
{
    $done = ['reminded' => 0, 'declined' => 0, 'reviewed' => 0, 'closed' => 0];
    // Remind applicants who haven't finished onboarding after N days (once)
    $rows = db_all("SELECT a.id, a.user_id, u.name, u.email, j.title FROM applications a JOIN users u ON u.id = a.user_id JOIN jobs j ON j.id = a.job_id
        WHERE j.auto_remind = 1 AND a.status IN ('new', 'reviewing') AND a.reminded_at IS NULL AND a.created_at <= NOW() - INTERVAL j.remind_days DAY");
    foreach ($rows as $r) {
        $missing = onboarding_missing((int) $r['user_id']);
        if (!$missing) {
            continue;
        }
        [$text, $html] = email_body('Finish your onboarding', [
            'Hi ' . chat_first_name((string) $r['name']) . ',',
            'Thanks again for applying for ' . $r['title'] . '. Your application is waiting on a few things:',
            '• ' . implode("\n• ", $missing),
            'It only takes a few minutes, and complete applications are reviewed first.',
        ], 'Finish my onboarding', abs_url('account.php'));
        send_mail((string) $r['email'], 'Your LamazonLoads application: a few things left', $text, $html, support_email());
        db_run('UPDATE applications SET reminded_at = NOW() WHERE id = ?', [$r['id']]);
        app_auto_note((int) $r['id'], 'onboarding reminder sent');
        $done['reminded']++;
    }
    // Not selected: still not complete N days after the reminder
    $rows = db_all("SELECT a.id, a.user_id FROM applications a JOIN jobs j ON j.id = a.job_id
        WHERE j.auto_decline = 1 AND a.status = 'new' AND a.reminded_at IS NOT NULL AND a.reminded_at <= NOW() - INTERVAL j.decline_days DAY");
    foreach ($rows as $r) {
        if (onboarding_complete((int) $r['user_id'])) {
            continue;
        }
        db_run("UPDATE applications SET status = 'not_selected', updated_at = NOW() WHERE id = ?", [$r['id']]);
        app_auto_note((int) $r['id'], 'Not selected (onboarding not finished after reminder)');
        $done['declined']++;
    }
    // Catch-up for In review and auto-close
    foreach (db_all("SELECT DISTINCT a.user_id FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.auto_review = 1 AND a.status = 'new'") as $r) {
        auto_review_user((int) $r['user_id']);
    }
    foreach (db_all("SELECT id FROM jobs WHERE status = 'open' AND auto_close = 1") as $j) {
        $done['closed'] += auto_close_job((int) $j['id']) ? 1 : 0;
    }
    return $done;
}

const UPLOAD_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
const RESUME_TYPES = ['application/pdf' => 'pdf', 'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

/** Saves an uploaded file as one of the member's documents. Returns [document id or 0, error message or '']. */
function save_upload(?array $f, int $userId, string $kind): array
{
    $maxMb = (int) config('max_upload_mb');
    $allowed = $kind === 'resume' ? RESUME_TYPES : UPLOAD_TYPES;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [0, 'Please choose a file to upload.'];
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > $maxMb * 1024 * 1024) {
        return [0, "That file is too big. The limit is {$maxMb} MB."];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return [0, 'The upload did not finish. Please try again.'];
    }
    if ((int) db_val('SELECT COUNT(*) FROM documents WHERE user_id = ?', [$userId]) >= 40) {
        return [0, 'You have reached the document limit. Remove old documents first.'];
    }
    // Check what the file really is, not just its name. (Some servers report .docx files as zip.)
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime === 'application/zip' && $kind === 'resume' && preg_match('/\.docx$/i', (string) $f['name'])) {
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    if (!isset($allowed[$mime])) {
        return [0, $kind === 'resume' ? 'Please upload your resume as a PDF, Word (DOC/DOCX), JPG or PNG file.' : 'Please upload a PDF, JPG or PNG file.'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], dirname(__DIR__) . '/uploads/' . $stored)) {
        return [0, 'Could not save the file. Please try again.'];
    }
    $name = mb_substr(preg_replace('/[^\w .()\-]+/u', '_', basename((string) $f['name'])) ?: 'document', 0, 190);
    db_run('INSERT INTO documents (user_id, kind, stored_name, original_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$userId, $kind, $stored, $name, $mime, (int) $f['size']]);
    return [(int) db()->lastInsertId(), ''];
}
