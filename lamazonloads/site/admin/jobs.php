<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$statuses = ['open' => 'Open (visible on the website)', 'draft' => 'Draft (hidden)', 'closed' => 'Closed (hidden)'];
$text = ['title' => 150, 'category' => 30, 'location' => 120, 'equipment' => 120, 'pay' => 120, 'description' => 8000, 'requirements' => 4000,
    'status' => 10, 'hires_needed' => 10, 'apply_method' => 10, 'apply_url' => 300, 'resume' => 10, 'notify_emails' => 300, 'contact_email' => 190,
    'hiring_timeline' => 10, 'welcome_message' => 3000, 'remind_days' => 2, 'decline_days' => 2];
$flags = ['notify_on', 'contact_by_email', 'fair_chance', 'background_check', 'auto_welcome', 'auto_review', 'auto_remind', 'auto_decline', 'auto_close'];
$dayChoices = [1 => '1 day', 2 => '2 days', 3 => '3 days', 5 => '5 days', 7 => '7 days'];
$errors = [];
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$job = $editId ? db_one('SELECT * FROM jobs WHERE id = ?', [$editId]) : null;
if ($editId && !$job) {
    redirect('admin/jobs.php');
}
$defaults = array_merge(array_fill_keys(array_keys($text), ''), array_fill_keys($flags, 0), [
    'category' => 'light_truck', 'status' => 'open', 'hires_needed' => '', 'apply_method' => 'site', 'resume' => 'optional', 'notify_on' => 1,
    'notify_emails' => (string) config('contact_email'), 'contact_email' => (string) config('contact_email'), 'remind_days' => 2, 'decline_days' => 5,
    'job_types' => '', 'welcome_message' => DEFAULT_WELCOME,
]);
$form = $job ? array_merge($defaults, $job) : $defaults;
if ($job && trim((string) $job['welcome_message']) === '') {
    $form['welcome_message'] = DEFAULT_WELCOME;
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        require_full_admin_action('admin/jobs.php');
        db_run('DELETE FROM applications WHERE job_id = ? AND job_id <> 0', [$id]);
        db_run('DELETE FROM jobs WHERE id = ?', [$id]);
        flash('success', 'Job post deleted.');
        redirect('admin/jobs.php');
    }
    if ($action === 'network') {
        $on = ($_POST['on'] ?? '') === '1';
        db_run("INSERT INTO meta (k, v) VALUES ('network_job', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$on ? 'on' : 'off']);
        flash('success', $on ? 'The driver network application is shown on Careers again.' : 'The driver network application is hidden from the website.');
        redirect('admin/jobs.php');
    }
    if ($action === 'status') {
        $s = post('status', 10);
        if (isset($statuses[$s])) {
            db_run('UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ?', [$s, $id]);
            flash('success', 'Job post is now ' . strtolower(strtok($statuses[$s], ' ')) . '.');
        }
        redirect('admin/jobs.php');
    }
    foreach ($text as $k => $max) {
        $form[$k] = post($k, $max);
    }
    foreach ($flags as $k) {
        $form[$k] = !empty($_POST[$k]) ? 1 : 0;
    }
    $types = array_values(array_intersect(array_keys(JOB_TYPES), (array) ($_POST['job_types'] ?? [])));
    $form['job_types'] = implode(',', $types);
    $form['remind_days'] = isset($dayChoices[(int) $form['remind_days']]) ? (int) $form['remind_days'] : 2;
    $form['decline_days'] = isset($dayChoices[(int) $form['decline_days']]) ? (int) $form['decline_days'] : 5;

    if ($form['title'] === '') $errors[] = 'Please enter a job title.';
    if (!isset(JOB_CATEGORIES[$form['category']])) $errors[] = 'Please choose a category.';
    if (!isset(HIRE_COUNTS[$form['hires_needed']])) $errors[] = 'Please choose the number of people to hire in the next 30 days.';
    if (!$types) $errors[] = 'Please choose at least one job type.';
    if (trim($form['description']) === '') $errors[] = 'Please write a job description.';
    if (!isset($statuses[$form['status']])) $form['status'] = 'draft';
    if (!in_array($form['apply_method'], ['site', 'url'], true)) $form['apply_method'] = 'site';
    if ($form['apply_method'] === 'url' && !preg_match('#^https?://[^\s]+$#i', $form['apply_url'])) $errors[] = 'Please enter the full web address where people apply (starting with https://).';
    if (!isset(RESUME_OPTIONS[$form['resume']])) $form['resume'] = 'optional';
    if ($form['notify_on'] && !emails_in($form['notify_emails'])) $errors[] = 'Please enter at least one email for application updates.';
    if ($form['contact_by_email'] && !filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter the email candidates can contact.';
    if ($form['hiring_timeline'] !== '' && !isset(HIRING_TIMELINES[$form['hiring_timeline']])) $form['hiring_timeline'] = '';
    if ($form['auto_close'] && !ctype_digit($form['hires_needed'])) $errors[] = 'Closing the post automatically needs an exact number of people to hire (1 to 10).';
    if ($form['auto_decline'] && !$form['auto_remind']) $errors[] = '"Mark as Not selected" works together with the onboarding reminder. Turn the reminder on too.';
    $form['country'] = 'United States';
    $form['language'] = 'English';

    if (!$errors) {
        $cols = array_merge(array_keys($text), $flags, ['job_types', 'country', 'language']);
        $vals = array_map(fn ($c) => $form[$c], $cols);
        if ($id) {
            db_run('UPDATE jobs SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $cols)) . ', updated_at = NOW() WHERE id = ?', array_merge($vals, [$id]));
            auto_close_job($id);
            flash('success', 'Job post saved.');
        } else {
            db_run('INSERT INTO jobs (' . implode(', ', $cols) . ', created_at, updated_at) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW(), NOW())', $vals);
            flash('success', 'Job post created.');
        }
        redirect('admin/jobs.php');
    }
    $editId = $id ?: null;
}

$showForm = $editId !== null || isset($_GET['new']) || $errors;
$jobs = db_all("SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) apps, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'approved') hired
    FROM jobs j ORDER BY FIELD(j.status, 'open', 'draft', 'closed'), j.created_at DESC");
$checked = fn (string $k): string => !empty($form[$k]) ? ' checked' : '';
$sel = function (string $name, array $opts, string $cur, string $placeholder = ''): string {
    $h = '<select id="' . $name . '" name="' . $name . '">' . ($placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '');
    foreach ($opts as $k => $l) {
        $h .= '<option value="' . e((string) $k) . '"' . ((string) $cur === (string) $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h . '</select>';
};

page_header('Job posts');
admin_open('jobs');
?>
<?php if ($showForm): ?>
  <a class="back-link" href="<?= e(url('admin/jobs.php')) ?>"><?= icon('chev-left') ?> All job posts</a>
  <?= admin_head($editId ? 'Edit job post' : 'New job post', 'Fill in the basics, the details and the settings. Open posts appear on the Careers page and the home page.') ?>
<?php else: ?>
  <?= admin_head('Job posts', 'Open posts appear on the Careers page and the home page. Members apply with one click.',
      '<a class="btn btn-accent" href="' . e(url('admin/jobs.php?new=1')) . '">' . icon('plus') . ' New job post</a>') ?>
<?php endif; ?>

<?php if ($showForm): ?>
<form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="jp-form" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($editId ?? 0) ?>">
  <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

  <section class="card form-card">
    <h2 class="jp-h"><span>1</span><?= $editId ? 'Edit job post' : 'Job basics' ?></h2>
    <div class="form-grid">
      <div class="full"><label for="title">Job title <span class="req">*</span></label><input id="title" name="title" type="text" maxlength="150" value="<?= e($form['title']) ?>" placeholder="Box Truck Delivery Driver"></div>
      <div class="full"><label for="category">Category <span class="req">*</span></label><?= $sel('category', JOB_CATEGORIES, (string) $form['category']) ?></div>
      <div><label for="country">Country and language</label><select id="country" disabled><option>United States · English</option></select><p class="hint">Jobs are posted in the United States, in English.</p></div>
      <div><label for="location">Job location</label><input id="location" name="location" type="text" maxlength="120" value="<?= e($form['location']) ?>" placeholder="Atlanta, GA · Remote · Nationwide"></div>
      <div class="full"><label for="hires_needed">Number of people to hire in the next 30 days <span class="req">*</span></label><?= $sel('hires_needed', HIRE_COUNTS, (string) $form['hires_needed'], 'Select an option') ?></div>
    </div>
  </section>

  <section class="card form-card">
    <h2 class="jp-h"><span>2</span>Job details</h2>
    <fieldset class="jp-field"><legend>Job type <span class="req">*</span> <span class="opt">(choose all that apply)</span></legend>
      <div class="chips">
        <?php $cur = explode(',', (string) $form['job_types']); foreach (JOB_TYPES as $k => $l): ?>
          <label class="chip"><input type="checkbox" name="job_types[]" value="<?= e($k) ?>"<?= in_array($k, $cur, true) ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="form-grid mt">
      <div><label for="equipment">Equipment</label><input id="equipment" name="equipment" type="text" maxlength="120" value="<?= e($form['equipment']) ?>" placeholder="26 ft Box Truck"></div>
      <div><label for="pay">Pay</label><input id="pay" name="pay" type="text" maxlength="120" value="<?= e($form['pay']) ?>" placeholder="$ per route / per load"></div>
      <div class="full"><label for="description">Job description <span class="req">*</span></label><textarea id="description" name="description" maxlength="8000" rows="8"><?= e($form['description']) ?></textarea><p class="hint">The first paragraph shows on the job cards. Leave an empty line between paragraphs.</p></div>
      <div class="full"><label for="requirements">Requirements <span class="opt">(one per line)</span></label><textarea id="requirements" name="requirements" maxlength="4000" rows="5"><?= e($form['requirements']) ?></textarea></div>
    </div>
  </section>

  <section class="card form-card">
    <h2 class="jp-h"><span>3</span>Settings</h2>
    <div class="jp-settings">
      <div class="jp-set">
        <h3>Application method</h3>
        <label class="check"><input type="radio" name="apply_method" value="site"<?= $form['apply_method'] !== 'url' ? ' checked' : '' ?>> On LamazonLoads: candidates apply with their account (profile and documents attached)</label>
        <label class="check"><input type="radio" name="apply_method" value="url"<?= $form['apply_method'] === 'url' ? ' checked' : '' ?>> On another website</label>
        <input name="apply_url" type="url" maxlength="300" value="<?= e($form['apply_url']) ?>" placeholder="https://… (only for &quot;another website&quot;)" aria-label="Application website">
      </div>
      <div class="jp-set">
        <h3>Require resume</h3>
        <?php foreach (RESUME_OPTIONS as $k => $l): ?><label class="check"><input type="radio" name="resume" value="<?= e($k) ?>"<?= $form['resume'] === $k ? ' checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?>
      </div>
      <div class="jp-set">
        <h3>Application updates</h3>
        <label class="check"><input type="checkbox" name="notify_on" value="1"<?= $checked('notify_on') ?>> Email me each time someone applies</label>
        <input name="notify_emails" type="text" maxlength="300" value="<?= e($form['notify_emails']) ?>" placeholder="you@lamazonloads.com, partner@lamazonloads.com" aria-label="Emails for application updates">
        <p class="hint">Separate several emails with commas.</p>
      </div>
      <div class="jp-set">
        <h3>Candidates contact you</h3>
        <label class="check"><input type="checkbox" name="contact_by_email" value="1"<?= $checked('contact_by_email') ?>> Let candidates email me questions about this job</label>
        <input name="contact_email" type="email" maxlength="190" value="<?= e($form['contact_email']) ?>" aria-label="Email for candidate questions">
        <p class="hint">Shown on the job page.</p>
      </div>
      <div class="jp-set">
        <h3>Fair chance hiring</h3>
        <label class="check"><input type="checkbox" name="fair_chance" value="1"<?= $checked('fair_chance') ?>> I'm open to hiring people with a criminal record</label>
        <p class="hint">Shows a "Fair chance" label on the job.</p>
      </div>
      <div class="jp-set">
        <h3>Background check</h3>
        <label class="check"><input type="checkbox" name="background_check" value="1"<?= $checked('background_check') ?>> This job requires a background check</label>
        <p class="hint">Candidates see this before they apply.</p>
      </div>
      <div class="jp-set">
        <h3>Hiring timeline</h3>
        <label for="hiring_timeline" class="sr-only">How quickly do you need to hire?</label>
        <?= $sel('hiring_timeline', HIRING_TIMELINES, (string) $form['hiring_timeline'], 'How quickly do you need to hire?') ?>
        <p class="hint">"1 to 3 days" shows an "Urgently hiring" label.</p>
      </div>
      <div class="jp-set">
        <h3>Visibility</h3>
        <?= $sel('status', $statuses, (string) $form['status']) ?>
      </div>
    </div>
  </section>

  <section class="card form-card jp-auto">
    <h2 class="jp-h"><span>4</span>Get started with automations</h2>
    <p class="muted">Save time and focus on responsive candidates.</p>
    <div class="auto-list">
      <div class="auto auto-on"><span class="auto-tick"><?= icon('check') ?></span>
        <span><b>Onboarding email: always on</b><small>Right after someone applies, they get the Dispatch email (box truck, cargo van or Sprinter) or the Walmart daily route email (SUV / other), from info@lamazonloads.com. Cities and pay rate: <a href="<?= e(url('admin/walmart.php')) ?>">Admin → Walmart routes</a>.</small></span></div>
      <label class="auto"><input type="checkbox" name="auto_review" value="1"<?= $checked('auto_review') ?>>
        <span><b>Move complete applicants to "In review"</b><small>As soon as an applicant has their profile, W-9, insurance and driver's license on file.</small></span></label>
      <label class="auto"><input type="checkbox" name="auto_remind" value="1"<?= $checked('auto_remind') ?>>
        <span><b>Remind applicants to finish onboarding</b><small>One friendly email listing what's missing, <select name="remind_days" aria-label="Days after applying"><?php foreach ($dayChoices as $d => $l): ?><option value="<?= $d ?>"<?= (int) $form['remind_days'] === $d ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select> after they apply.</small></span></label>
      <label class="auto"><input type="checkbox" name="auto_decline" value="1"<?= $checked('auto_decline') ?>>
        <span><b>Focus on responsive candidates</b><small>Mark as "Not selected" if onboarding still isn't done <select name="decline_days" aria-label="Days after the reminder"><?php foreach ($dayChoices as $d => $l): ?><option value="<?= $d ?>"<?= (int) $form['decline_days'] === $d ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select> after the reminder. They see it in their dashboard.</small></span></label>
      <label class="auto"><input type="checkbox" name="auto_close" value="1"<?= $checked('auto_close') ?>>
        <span><b>Close the post when you've hired enough</b><small>When the number of "Approved" applicants reaches the number to hire.</small></span></label>
    </div>
  </section>

  <div class="row-actions"><button class="btn btn-accent btn-lg" type="submit">Save job post</button><a class="btn btn-ghost" href="<?= e(url('admin/jobs.php')) ?>">Cancel</a></div>
</form>
<?php endif; ?>

<?php if (!$showForm): $netOn = network_enabled(); $netApps = (int) db_val('SELECT COUNT(*) FROM applications WHERE job_id = 0'); ?>
<section class="card net-card">
  <span class="net-ico"><?= icon('users') ?></span>
  <div class="net-text">
    <b>LamazonLoads driver network <span class="badge <?= $netOn ? 'badge-open' : 'badge-closed' ?>"><?= $netOn ? 'Shown on website' : 'Hidden' ?></span></b>
    <p>A built-in general application that is always open (not a job post). Drivers apply once and you reach out when something fits.</p>
  </div>
  <div class="net-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/applications.php?job=0')) ?>"><?= $netApps ?> applicant<?= $netApps === 1 ? '' : 's' ?></a>
    <?php if ($netOn): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('job.php?id=0')) ?>"><?= icon('eye') ?> View</a><?php endif; ?>
    <form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="network"><input type="hidden" name="on" value="<?= $netOn ? '0' : '1' ?>">
      <button class="btn <?= $netOn ? 'btn-ghost' : 'btn-primary' ?> btn-sm" type="submit"><?= $netOn ? 'Hide from website' : 'Show on website' ?></button></form>
  </div>
</section>

<section class="card panel">
  <header class="panel-head"><h2><?= icon('briefcase') ?>Your job posts</h2><small><?= count($jobs) ?> post<?= count($jobs) === 1 ? '' : 's' ?> · <?= count(array_filter($jobs, fn ($j) => $j['status'] === 'open')) ?> open</small></header>
  <?php if (!$jobs): ?><div class="panel-body"><div class="empty">No job posts yet. Click <b>New job post</b> to add one.</div></div><?php else: ?>
  <div class="panel-body flush jl">
    <div class="jl-row jl-head" aria-hidden="true"><span>Job</span><span>Type</span><span>Hiring</span><span>Status</span><span>Applicants</span><span>Actions</span></div>
    <?php foreach ($jobs as $j): $autos = array_filter(['In review' => $j['auto_review'], 'Reminder' => $j['auto_remind'], 'Not selected' => $j['auto_decline'], 'Auto-close' => $j['auto_close']]); ?>
      <div class="jl-row">
        <div class="jl-job"><a href="<?= e(url('admin/jobs.php?edit=' . (int) $j['id'])) ?>"><b><?= e($j['title']) ?></b></a>
          <small><?= e(JOB_CATEGORIES[$j['category']] ?? $j['category']) ?><?= $j['location'] !== '' ? ' · ' . e($j['location']) : '' ?></small>
          <?php if ($autos): ?><small class="jl-auto"><?= icon('star') ?> Automations: <?= e(implode(' · ', array_keys($autos))) ?></small><?php endif; ?></div>
        <span class="jl-type"><span class="jl-k">Type</span><?= e(implode(', ', job_type_labels($j)) ?: '—') ?></span>
        <span class="jl-hire"><span class="jl-k">Hiring</span><?= e(HIRE_COUNTS[$j['hires_needed']] ?? $j['hires_needed']) ?><?php if ((int) $j['hired']): ?><small><?= (int) $j['hired'] ?> approved</small><?php endif; ?></span>
        <span class="jl-status"><span class="badge badge-<?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span></span>
        <a class="jl-apps" href="<?= e(url('admin/applications.php?job=' . (int) $j['id'])) ?>"><b><?= (int) $j['apps'] ?></b> applicant<?= (int) $j['apps'] === 1 ? '' : 's' ?></a>
        <div class="jl-actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/jobs.php?edit=' . (int) $j['id'])) ?>"><?= icon('edit') ?> Edit</a>
          <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('job.php?id=' . (int) $j['id'])) ?>" aria-label="View on website" title="View on website"><?= icon('eye') ?></a>
          <form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
            <input type="hidden" name="status" value="<?= $j['status'] === 'open' ? 'closed' : 'open' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $j['status'] === 'open' ? 'Close' : 'Reopen' ?></button></form>
          <?php if (is_full_admin()): // only admins delete ?><form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="inline-form" data-confirm="Delete this job post and its applications?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>"><button class="btn btn-danger btn-sm btn-icon" type="submit" aria-label="Delete job post" title="Delete"><?= icon('trash') ?></button></form><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php dash_close(); page_footer();
