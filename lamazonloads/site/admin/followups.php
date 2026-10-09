<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Automatic follow-ups: reminder emails to drivers who stop partway, the staff's morning summary, optional "Not selected".
// The rules and the emails themselves are in includes/followups.php.
$me = require_full_admin();
$after = ['verify' => 'after they sign up', 'open' => 'after the onboarding email', 'docs' => 'after they open their onboarding page',
    'changes' => 'after you ask for changes', 'sign' => 'after you approve their documents', 'invite' => 'after your onboarding email',
    'signin' => 'after you add their account'];
$s = followup_settings();
$f = ['summary' => $s['summary'], 'summary_to' => $s['summary_to'], 'decline' => $s['decline'], 'decline_days' => (string) $s['decline_days'], 'kinds' => []];
foreach ($s['kinds'] as $k => $v) {
    $f['kinds'][$k] = ['on' => $v['on'], 'days' => implode(', ', $v['days'])];
}
$errors = [];

if (is_post()) {
    csrf_check();
    $action = as_str($_POST['action'] ?? '');
    if ($action === 'master') {
        $on = as_str($_POST['on'] ?? '') === '1';
        meta_set('fu_on', $on ? '1' : '0');
        flash('success', $on ? 'Reminder emails are on.' : 'Reminder emails are off. Nothing more goes out until you turn them back on.');
        redirect('admin/followups.php');
    }
    if ($action === 'run') {
        $done = run_automations_locked();
        $hour = (int) date('G');
        $quiet = $hour < FOLLOWUP_HOURS[0] || $hour >= FOLLOWUP_HOURS[1];
        if (!$done) {
            flash('info', 'Follow-ups are already being sent right now. Check the list below in a minute.');
        } elseif ($done['sent'] || $done['failed']) {
            flash($done['failed'] ? 'error' : 'success', ($done['sent'] === 1 ? '1 reminder sent' : $done['sent'] . ' reminders sent')
                . ($done['failed'] ? '. ' . $done['failed'] . ' could not be sent. Check Admin → Email check.' : '.'));
        } else {
            flash('info', $quiet ? 'Reminders only go out between 9 am and 7 pm New York time. The next ones will go out after 9 am.' : 'Everyone is up to date. Nothing was due.');
        }
        redirect('admin/followups.php');
    }
    if ($action === 'resume') { // only a pause by your team: someone who stopped reminders themselves stays stopped
        $email = strtolower(post('email', 190));
        if (db_val('SELECT 1 FROM followup_optout WHERE email = ? AND by_staff IS NOT NULL', [$email])) {
            followup_set_optout($email, false);
            flash('success', 'Reminders are back on for ' . $email . '.');
        }
        redirect('admin/followups.php#stopped');
    }
    if ($action === 'save') {
        foreach (FOLLOWUPS as $k => [$name]) {
            $f['kinds'][$k] = ['on' => !empty($_POST['on_' . $k]), 'days' => post_line('days_' . $k, 20)];
            $days = followup_days($f['kinds'][$k]['days']);
            if ($f['kinds'][$k]['on'] && !$days) {
                $errors[] = $name . ': enter the days to send on, for example “1, 3”.';
            }
            if ($days) {
                $f['kinds'][$k]['days'] = implode(', ', $days);
            }
        }
        $f['summary'] = !empty($_POST['summary']);
        $f['summary_to'] = implode(', ', emails_in(post_line('summary_to', 250)));
        if ($f['summary'] && $f['summary_to'] === '') {
            $errors[] = 'Morning summary: enter the email address it goes to.';
        }
        $f['decline'] = !empty($_POST['decline']);
        $f['decline_days'] = post_line('decline_days', 3);
        if ($f['decline'] && (!ctype_digit($f['decline_days']) || (int) $f['decline_days'] < 1 || (int) $f['decline_days'] > 30)) {
            $errors[] = '“Not selected”: enter a number of days from 1 to 30.';
        }
        if (!$errors) {
            foreach ($f['kinds'] as $k => $v) {
                meta_set('fu_' . $k . '_on', $v['on'] ? '1' : '0');
                if (followup_days($v['days'])) {
                    meta_set('fu_' . $k . '_days', $v['days']);
                }
            }
            meta_set('fu_summary_on', $f['summary'] ? '1' : '0');
            if ($f['summary_to'] !== '') {
                meta_set('fu_summary_to', $f['summary_to']);
            }
            meta_set('fu_decline_on', $f['decline'] ? '1' : '0');
            if (ctype_digit($f['decline_days']) && (int) $f['decline_days'] >= 1 && (int) $f['decline_days'] <= 30) {
                meta_set('fu_decline_days', $f['decline_days']);
            }
            flash('success', 'Follow-up settings saved.');
            redirect('admin/followups.php');
        }
    }
}

// Status: is the hosting's scheduled task running, when did the last run finish
$cronAt = (int) meta_get('cron_ran', '0');
$cronOn = $cronAt > time() - 7200;
$lastRun = preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)/', meta_get('automations_done'), $m) ? $m[1] : '';
$ago = function (string $dt): string {
    $s = max(0, time() - (int) strtotime($dt));
    return match (true) {
        $s < 90 => 'just now',
        $s < 3600 => round($s / 60) . ' minutes ago',
        $s < 86400 => ($h = (int) round($s / 3600)) . ($h === 1 ? ' hour ago' : ' hours ago'),
        default => fmt_date($dt, 'M j, g:i a'),
    };
};
$root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$cronCmd = '/usr/bin/php ' . $root . '/cron.php';
$week = (int) db_val('SELECT COUNT(*) FROM followup_log WHERE sent_at > NOW() - INTERVAL 7 DAY');
$perKind = array_column(db_all('SELECT kind, COUNT(*) AS n FROM followup_log WHERE sent_at > NOW() - INTERVAL 30 DAY GROUP BY kind'), 'n', 'kind');
$log = db_all('SELECT l.*, u.name FROM followup_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 50');
$stoppedN = (int) db_val('SELECT COUNT(*) FROM followup_optout');
$stopped = db_all('SELECT o.*, u.id AS uid, u.name, s.name AS staff FROM followup_optout o LEFT JOIN users u ON u.email = o.email
    LEFT JOIN users s ON s.id = o.by_staff ORDER BY o.created_at DESC LIMIT 50');
// An email's body for the preview, with the logo loaded from this site
$mailBody = fn (string $html): string => str_replace(rtrim((string) config('app_url'), '/') . url('assets/brand/email-logo.png'), url('assets/brand/email-logo.png'),
    preg_match('#<body[^>]*>(.*)</body>#s', $html, $mm) ? $mm[1] : '');
$plural = fn (int $n, string $one, string $many): string => $n . ' ' . ($n === 1 ? $one : $many);

page_header('Automatic follow-ups');
admin_open('followups');
?>
<?= admin_head('Automatic follow-ups', 'Short reminder emails go out when a driver stops partway through sign-up or onboarding. Each series stops as soon as they take the step, and you get one summary each morning.') ?>

<section class="card fu-status<?= $s['on'] ? ' is-on' : '' ?>">
  <div class="fu-state">
    <span class="fu-state-ico"><?= icon('bell') ?></span>
    <div><b><?= $s['on'] ? 'Reminder emails are on' : 'Reminder emails are off' ?></b>
      <small><?= $s['on'] ? e($plural($week, 'reminder', 'reminders')) . ' sent in the last 7 days' . ($stoppedN ? ' · ' . e($plural($stoppedN, 'person', 'people')) . ' stopped them' : '') : 'Nothing goes out until you turn them on. The morning summary has its own switch below.' ?></small></div>
  </div>
  <div class="fu-state-acts">
    <form method="post" action="<?= e(url('admin/followups.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="run">
      <button class="btn btn-ghost btn-sm" type="submit" data-busy="Sending…"<?= $s['on'] ? '' : ' disabled' ?>><?= icon('send') ?> Send what’s due now</button></form>
    <form method="post" action="<?= e(url('admin/followups.php')) ?>" class="inline-form"<?= $s['on'] ? ' data-confirm="Turn off all reminder emails? Drivers in the middle of a series won’t get the rest." data-confirm-ok="Turn off"' : '' ?>><?= csrf_field() ?>
      <input type="hidden" name="action" value="master"><input type="hidden" name="on" value="<?= $s['on'] ? '0' : '1' ?>">
      <button class="btn <?= $s['on'] ? 'btn-ghost' : 'btn-primary' ?> btn-sm" type="submit"><?= $s['on'] ? 'Turn off' : 'Turn on' ?></button></form>
  </div>
  <div class="fu-cron<?= $cronOn ? ' is-ok' : '' ?>">
    <?= icon($cronOn ? 'check' : 'clock') ?>
    <div>
      <?php if ($cronOn): ?>
        <b>Scheduled task is running</b><small>Checks every 30 minutes. Last check <?= e($ago(date('Y-m-d H:i:s', $cronAt))) ?>.</small>
      <?php else: ?>
        <b>Scheduled task not set up yet</b>
        <small>Reminders still go out when someone visits the website<?= $lastRun !== '' ? ' (last check ' . e($ago($lastRun)) . ')' : '' ?>, but can be late on quiet days. Set it up once in Hostinger:</small>
        <details class="fu-howto"><summary>How to set it up (2 minutes)</summary>
          <ol>
            <li>In hPanel, open <b>Advanced → Cron Jobs</b>.</li>
            <li>Choose <b>Custom</b> and paste this command:<code class="fu-code"><?= e($cronCmd) ?></code></li>
            <li>Set it to run <b>every 30 minutes</b>, then tap <b>Save</b>.</li>
          </ol>
          <p class="hint mb-0">This box turns green within 30 minutes once it works.</p>
        </details>
      <?php endif; ?>
    </div>
  </div>
</section>

<form method="post" action="<?= e(url('admin/followups.php')) ?>" class="fu-form" novalidate>
<?= csrf_field() ?><input type="hidden" name="action" value="save">
<?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

<section class="card panel">
  <header class="panel-head"><h2><?= icon('mail') ?>Reminders to drivers</h2><small>One email a day at most · 9&nbsp;am to 7&nbsp;pm New&nbsp;York time</small></header>
  <div class="panel-body fu-list">
    <?php foreach (FOLLOWUPS as $k => [$name, $desc]): $v = $f['kinds'][$k];
        $waiting = count(followup_candidates($k, (int) (end($s['kinds'][$k]['days']) ?: 3) + 4));
        $sent30 = (int) ($perKind[$k] ?? 0);
        [$pSubject, $pHtml] = followup_preview($k); ?>
      <div class="fu-item<?= $v['on'] ? '' : ' is-off' ?>">
        <label class="fu-top"><input type="checkbox" name="on_<?= e($k) ?>" value="1"<?= $v['on'] ? ' checked' : '' ?>>
          <span><b><?= e($name) ?></b><small><?= e($desc) ?></small></span></label>
        <p class="fu-stats"><span><?= $waiting ? e($plural($waiting, 'person', 'people')) . ' at this step' : 'Nobody at this step' ?></span><span><?= e($plural($sent30, 'email', 'emails')) ?> in 30 days</span></p>
        <div class="fu-when">
          <label for="days_<?= e($k) ?>">Send on day</label>
          <input id="days_<?= e($k) ?>" name="days_<?= e($k) ?>" type="text" inputmode="numeric" maxlength="20" value="<?= e($v['days']) ?>" aria-describedby="when_<?= e($k) ?>">
          <span id="when_<?= e($k) ?>"><?= e($after[$k]) ?></span>
        </div>
        <details class="fu-prev"><summary><?= icon('eye') ?> Preview: <?= e($pSubject) ?></summary>
          <div class="os-mail" inert><?= $mailBody($pHtml) ?></div></details>
      </div>
    <?php endforeach; ?>
    <p class="hint mb-0">Up to three days each, like “2, 5, 10”. Each email has a “Stop reminders” link. To pause one person, open their member page.</p>
  </div>
</section>

<div class="fu-pair">
<section class="card panel">
  <header class="panel-head"><h2><?= icon('chart') ?>Morning summary for your team</h2></header>
  <div class="panel-body fu-opt">
    <label class="check"><input type="checkbox" name="summary" value="1"<?= $f['summary'] ? ' checked' : '' ?>>
      <span>Email a list of what’s waiting at 8 am New York time, only on days when something is waiting</span></label>
    <div><label for="summary_to">Send it to</label>
      <input id="summary_to" name="summary_to" type="text" maxlength="250" value="<?= e($f['summary_to']) ?>" placeholder="info@lamazonloads.com">
      <p class="hint">Separate several addresses with commas.</p></div>
    <?php [$sSubject, , $sHtml] = followup_summary_email(); ?>
    <details class="fu-prev"><summary><?= icon('eye') ?> <?= $sSubject !== '' ? 'Preview: ' . e($sSubject) : 'Preview' ?></summary>
      <?php if ($sSubject !== ''): ?><div class="os-mail" inert><?= $mailBody($sHtml) ?></div>
      <?php else: ?><p class="muted mb-0">Nothing is waiting right now, so no summary would go out today.</p><?php endif; ?></details>
  </div>
</section>

<section class="card panel">
  <header class="panel-head"><h2><?= icon('clipboard') ?>Applicants who don’t finish</h2></header>
  <div class="panel-body fu-opt">
    <label class="check"><input type="checkbox" name="decline" value="1"<?= $f['decline'] ? ' checked' : '' ?>>
      <span>Mark their application “Not selected” when onboarding still isn’t done after the last documents reminder</span></label>
    <div class="fu-when"><label for="decline_days">Wait</label>
      <input id="decline_days" name="decline_days" type="text" inputmode="numeric" maxlength="3" value="<?= e($f['decline_days']) ?>">
      <span>days after the last reminder</span></div>
    <p class="hint mb-0">Only applications still marked “Received” change. They see “Not selected” in their dashboard, and no email is sent.</p>
  </div>
</section>
</div>

<div class="row-actions"><button class="btn btn-accent btn-lg" type="submit">Save settings</button></div>
</form>

<section class="card panel">
  <header class="panel-head"><h2><?= icon('clock') ?>Recently sent</h2><small><?= $log ? 'Last ' . count($log) : '' ?></small></header>
  <?php if (!$log): ?>
    <div class="panel-body"><p class="muted mb-0">Reminders show up here as they go out.</p></div>
  <?php else: ?>
    <ul class="os-list fu-log">
      <?php foreach ($log as $r): $kName = FOLLOWUPS[$r['kind']][0] ?? $r['kind']; ?>
        <li>
          <span class="os-when"><?= e(fmt_date((string) $r['sent_at'], 'M j')) ?><small><?= e(fmt_date((string) $r['sent_at'], 'g:i a')) ?></small></span>
          <span class="os-to"><b><?php if ($r['user_id'] !== null && $r['name'] !== null): ?><a href="<?= e(url('admin/driver.php?id=' . (int) $r['user_id'])) ?>"><?= e((string) $r['name']) ?></a><?php else: ?><?= e((string) $r['email']) ?><?php endif; ?></b>
            <small><?= e($r['name'] !== null ? (string) $r['email'] : 'No account yet') ?></small></span>
          <span class="os-kind"><b><?= e($kName) ?></b><small>Reminder <?= (int) $r['step'] ?></small></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card panel" id="stopped">
  <header class="panel-head"><h2><?= icon('bell') ?>Stopped reminders</h2><small><?= $stoppedN ?: '' ?></small></header>
  <?php if (!$stopped): ?>
    <div class="panel-body"><p class="muted mb-0">Nobody has stopped reminders. People who tap “Stop reminders” in an email, or whom you pause on their member page, are listed here.</p></div>
  <?php else: ?>
    <ul class="os-list fu-log">
      <?php foreach ($stopped as $r): ?>
        <li>
          <span class="os-when"><?= e(fmt_date((string) $r['created_at'], 'M j')) ?><small><?= e(fmt_date((string) $r['created_at'], 'g:i a')) ?></small></span>
          <span class="os-to"><b><?php if ($r['uid'] !== null): ?><a href="<?= e(url('admin/driver.php?id=' . (int) $r['uid'])) ?>"><?= e((string) $r['name']) ?></a><?php else: ?><?= e((string) $r['email']) ?><?php endif; ?></b>
            <small><?= e($r['uid'] !== null ? (string) $r['email'] : 'No account yet') ?></small></span>
          <span class="os-kind"><?php if ($r['by_staff'] !== null): ?><b>Paused by <?= e(first_name((string) ($r['staff'] ?? 'Staff'))) ?></b>
            <form method="post" action="<?= e(url('admin/followups.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="resume"><input type="hidden" name="email" value="<?= e((string) $r['email']) ?>">
              <button class="link-btn" type="submit">Turn back on</button></form>
            <?php else: ?><b>Stopped by them</b><small>From the email link</small><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php dash_close(); page_footer();
