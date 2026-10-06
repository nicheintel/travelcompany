<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$job = $id === 0 ? network_job() : db_one('SELECT * FROM jobs WHERE id = ?', [$id]);
if ($id === 0 && !network_enabled()) {
    $job['status'] = 'closed'; // hidden by staff
}
if (!$job || ($job['status'] !== 'open' && !is_admin())) {
    http_response_code(404);
    page_header('Opening not found', 'careers');
    echo '<section class="section"><div class="container narrow"><div class="card pad center"><h1>This opening is no longer available</h1><p class="muted">It may have been filled or closed.</p><a class="btn btn-primary" href="' . e(url('careers.php')) . '">See current openings</a></div></div></section>';
    page_footer();
    exit;
}

$user = current_user();
$existing = $user ? db_one('SELECT * FROM applications WHERE user_id = ? AND job_id = ?', [$user['id'], $id]) : null;
$external = $job['apply_method'] === 'url' && $job['apply_url'] !== '';
$resumeMode = (string) $job['resume'];
$onFile = $user ? db_one("SELECT * FROM documents WHERE user_id = ? AND kind = 'resume' ORDER BY id DESC LIMIT 1", [$user['id']]) : null;
$errors = [];
$form = $user ? apply_form_values($user) : [];
$cities = walmart_cities();

if (is_post() && !$external) {
    $user = require_verified();
    csrf_check();
    $resumeId = null;
    if ($job['status'] !== 'open') {
        $errors[] = 'This opening is closed.';
    } elseif ($existing) {
        $errors[] = 'You already applied for this opening.';
    } else {
        $errors = apply_validate($form);
    }
    if (!$errors && $resumeMode !== 'no') { // resume is always optional on this form
        $choice = (string) ($_POST['resume_choice'] ?? '');
        if ($choice === 'file' && $onFile) {
            $resumeId = (int) $onFile['id'];
        } elseif (($_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$docId, $err] = save_upload($_FILES['resume'], (int) $user['id'], 'resume');
            if ($err !== '') {
                $errors[] = $err;
            } else {
                $resumeId = $docId;
            }
        }
    }
    if (!$errors) {
        db_run("INSERT INTO applications (user_id, job_id, message, resume_doc_id, first_name, last_name, phone, location, vehicles, vehicle_other,
                ownership, ownership_other, walmart, walmart_city, rate_requested, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW(), NOW())",
            [$user['id'], $id, $form['message'], $resumeId, $form['first_name'], $form['last_name'], $form['phone'], $form['location'],
             implode(',', $form['vehicles']), $form['vehicle_other'], $form['ownership'], $form['ownership_other'], $form['walmart'] ? 1 : 0,
             $form['walmart_city'], $form['rate_requested']]);
        $appId = (int) db()->lastInsertId();
        if ((string) $user['phone'] === '') {
            db_run('UPDATE users SET phone = ? WHERE id = ?', [$form['phone'], $user['id']]);
        }
        on_new_application($appId);
        $sent = (string) db_val('SELECT email_sent FROM applications WHERE id = ?', [$appId]);
        flash('success', 'Application sent! ' . ($sent !== ''
            ? 'We emailed you the next steps from info@lamazonloads.com. Just reply to that email with your documents.'
            : 'We will review it and contact you.'));
        redirect('account.php');
    }
}

$types = job_type_labels($job);
$urgent = $job['hiring_timeline'] === '1-3d';
page_header($job['title'], 'careers', job_excerpt($job));
$here = 'job.php?id=' . $id;
?>
<section class="page-hero"><div class="container">
  <a href="<?= e(url('careers.php')) ?>" class="back-link">&larr; All openings</a>
  <div class="tags hero-tags">
    <span class="tag tag-solid"><?= e($id === 0 ? 'Always open' : (JOB_CATEGORIES[$job['category']] ?? 'Opportunity')) ?></span>
    <?php if ($urgent): ?><span class="tag tag-urgent">⚡ Urgently hiring</span><?php endif; ?>
    <?php if ($job['status'] !== 'open'): ?><span class="tag">Closed (staff view)</span><?php endif; ?>
  </div>
  <h1><?= e($job['title']) ?></h1>
  <p class="lead"><?= e(job_excerpt($job, 220)) ?></p>
</div></section>

<section class="section">
  <div class="container story job-layout">
    <div class="card pad prose">
      <div class="tags job-badges">
        <?php foreach ($types as $t): ?><span class="tag"><?= icon('briefcase') ?><?= e($t) ?></span><?php endforeach; ?>
        <span class="tag"><?= icon('users') ?><?= e(job_hiring_label($job)) ?></span>
        <?php if ((int) $job['fair_chance']): ?><span class="tag tag-fair"><?= icon('handshake') ?>Fair chance</span><?php endif; ?>
      </div>
      <div class="job-meta">
        <?php foreach (['Location' => $job['location'], 'Equipment' => $job['equipment'], 'Pay' => $job['pay']] as $k => $v): if ($v === '' || $v === 'Not applicable') continue; ?>
          <div><small><?= e($k) ?></small><b><?= e($v) ?></b></div>
        <?php endforeach; ?>
      </div>
      <h3>About this opportunity</h3>
      <?php foreach (preg_split('/\R{2,}/', trim((string) $job['description'])) as $para): if ($para === '') continue; ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
      <?php $reqs = array_filter(array_map('trim', preg_split('/\R/', (string) $job['requirements']))); if ($reqs): ?>
        <h3 class="mt">Requirements</h3>
        <ul class="checklist">
          <?php foreach ($reqs as $r): ?><li><span class="tick"><?= icon('check') ?></span><span><?= e(ltrim($r, "-•* ")) ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ((int) $job['background_check'] || (int) $job['fair_chance'] || $job['hiring_timeline'] !== ''): ?>
        <h3 class="mt">Good to know</h3>
        <ul class="checklist">
          <?php if ((int) $job['background_check']): ?><li><span class="tick"><?= icon('shield') ?></span><span>This job requires a background check.</span></li><?php endif; ?>
          <?php if ((int) $job['fair_chance']): ?><li><span class="tick"><?= icon('handshake') ?></span><span>Fair chance employer: we're open to hiring people with a criminal record.</span></li><?php endif; ?>
          <?php if ($job['hiring_timeline'] !== ''): ?><li><span class="tick"><?= icon('clock') ?></span><span>We plan to hire within <?= e(strtolower(HIRING_TIMELINES[$job['hiring_timeline']] ?? '')) ?>.</span></li><?php endif; ?>
        </ul>
      <?php endif; ?>
      <?php if ((int) $job['contact_by_email'] && $job['contact_email'] !== ''): ?>
        <p class="mt job-contact"><?= icon('mail') ?> Questions about this job? Email <a href="mailto:<?= e($job['contact_email']) ?>?subject=<?= rawurlencode('Question: ' . $job['title']) ?>"><?= e($job['contact_email']) ?></a></p>
      <?php endif; ?>
    </div>

    <aside class="card pad apply-box" id="apply">
      <?php if ($existing): ?>
        <h3>You've applied</h3>
        <p>Status: <?= status_badge($existing['status']) ?></p>
        <p class="muted">Applied on <?= e(fmt_date($existing['created_at'])) ?>. Keep your profile and documents up to date so we can move fast.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('account.php')) ?>">Go to my dashboard</a>
      <?php elseif ($job['status'] !== 'open'): ?>
        <h3>This opening is closed</h3>
        <a class="btn btn-ghost btn-block" href="<?= e(url('careers.php')) ?>">See current openings</a>
      <?php elseif ($external): ?>
        <h3>Apply for this job</h3>
        <p class="muted">Applications for this job are taken on another website.</p>
        <a class="btn btn-accent btn-block" href="<?= e($job['apply_url']) ?>" target="_blank" rel="noopener nofollow">Apply on the company site <?= icon('arrow') ?></a>
      <?php elseif ($user && !is_verified($user)): ?>
        <h3>Confirm your email to apply</h3>
        <p class="muted">We sent a link to <b><?= e($user['email']) ?></b>. Once you confirm, you can apply in one click.</p>
        <a class="btn btn-accent btn-block" href="<?= e(url('verify.php')) ?>">Confirm my email</a>
      <?php elseif (!$user): ?>
        <h3>Apply in one click</h3>
        <p class="muted">Create a free account or sign in, then fill out a short form: your name, phone, location and vehicle. It takes about two minutes.</p>
        <a class="btn btn-accent btn-block" href="<?= e(url('register.php?next=' . rawurlencode($here))) ?>">Create account &amp; apply</a>
        <a class="btn btn-ghost btn-block mt" href="<?= e(url('login.php?next=' . rawurlencode($here))) ?>">I already have an account</a>
      <?php else: ?>
        <h3>Apply now</h3>
        <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= e(url($here)) ?>#apply" enctype="multipart/form-data" class="apply-form" data-apply novalidate>
          <?= csrf_field() ?>
          <div class="af-row">
            <div><label for="first_name">First name</label><input id="first_name" name="first_name" type="text" maxlength="60" required value="<?= e($form['first_name']) ?>" autocomplete="given-name"></div>
            <div><label for="last_name">Last name</label><input id="last_name" name="last_name" type="text" maxlength="60" required value="<?= e($form['last_name']) ?>" autocomplete="family-name"></div>
          </div>
          <label for="phone">Phone number</label>
          <input id="phone" name="phone" type="tel" maxlength="30" required value="<?= e($form['phone']) ?>" autocomplete="tel" placeholder="(555) 555-5555">
          <label for="location">Where are you located?</label>
          <input id="location" name="location" type="text" maxlength="120" required value="<?= e($form['location']) ?>" placeholder="City, State (e.g. Atlanta, GA)" autocomplete="address-level2">

          <fieldset class="af-set"><legend>Your vehicle <span class="opt">(choose all that apply)</span></legend>
            <div class="af-chips">
              <?php foreach (APPLY_VEHICLES as $k => $l): ?>
                <label class="af-chip"><input type="checkbox" name="vehicles[]" value="<?= e($k) ?>"<?= in_array($k, $form['vehicles'], true) ? ' checked' : '' ?> data-vehicle><span><?= e($l) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="af-reveal" data-show-if="vehicle-other"<?= in_array('other', $form['vehicles'], true) ? '' : ' hidden' ?>><label for="vehicle_other">What is your other vehicle?</label><input id="vehicle_other" name="vehicle_other" type="text" maxlength="80" value="<?= e($form['vehicle_other']) ?>" placeholder="e.g. Pickup truck, minivan"></div>
            <p class="af-note" data-suv-note<?= $form['vehicles'] && !array_intersect($form['vehicles'], DISPATCH_VEHICLES) ? '' : ' hidden' ?>><?= icon('route') ?><span>For SUVs and other vehicles, we currently have openings for the <b>Walmart daily route</b> only.</span></p>
          </fieldset>

          <fieldset class="af-set"><legend>Is the vehicle yours?</legend>
            <div class="af-chips">
              <?php foreach (VEHICLE_OWNERSHIP as $k => $l): ?>
                <label class="af-chip"><input type="radio" name="ownership" value="<?= e($k) ?>"<?= $form['ownership'] === $k ? ' checked' : '' ?> data-ownership><span><?= e($l) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="af-reveal" data-show-if="ownership-other"<?= $form['ownership'] === 'other' ? '' : ' hidden' ?>><label for="ownership_other">Tell us more</label><input id="ownership_other" name="ownership_other" type="text" maxlength="80" value="<?= e($form['ownership_other']) ?>" placeholder="e.g. Borrowed from family"></div>
          </fieldset>

          <?php if ($cities): ?>
            <div class="af-walmart">
              <label class="af-walmart-toggle"><input type="checkbox" name="walmart" value="1"<?= $form['walmart'] ? ' checked' : '' ?> data-walmart>
                <span><b>Interested in the Walmart daily route</b><small><?= e(walmart_headline()) ?></small></span></label>
              <div class="af-reveal" data-show-if="walmart"<?= $form['walmart'] ? '' : ' hidden' ?>>
                <label for="walmart_city">Choose your city <span class="req">*</span></label>
                <select id="walmart_city" name="walmart_city">
                  <option value="">Select a city…</option>
                  <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"<?= $form['walmart_city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
                <label for="rate_requested">What daily pay rate are you looking for? <span class="opt">(optional)</span></label>
                <input id="rate_requested" name="rate_requested" type="text" maxlength="40" value="<?= e($form['rate_requested']) ?>" placeholder="e.g. $275">
                <p class="hint">Routes pay <?= e(walmart_settings()['rate']) ?>. LamazonLoads negotiates the final rate for you.</p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($resumeMode !== 'no'): ?>
            <fieldset class="resume-box"><legend>Resume <span class="opt">(optional)</span></legend>
              <?php if ($onFile): ?>
                <label class="check"><input type="radio" name="resume_choice" value="file" checked> Use my resume on file: <b><?= e($onFile['original_name']) ?></b></label>
                <label class="check"><input type="radio" name="resume_choice" value="new"> Upload a new one</label>
              <?php endif; ?>
              <input type="file" name="resume" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" aria-label="Resume file">
              <p class="hint">PDF, Word, JPG or PNG, up to <?= (int) config('max_upload_mb') ?> MB.</p>
            </fieldset>
          <?php endif; ?>
          <label for="message">Anything else we should know? <span class="opt">(optional)</span></label>
          <textarea id="message" name="message" maxlength="3000" rows="3" placeholder="Preferred lanes, when you can start…"><?= e($form['message']) ?></textarea>
          <p class="hint">Applying as <?= e($user['email']) ?></p>
          <button class="btn btn-accent btn-block" type="submit">Send application <?= icon('arrow') ?></button>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php page_footer();
