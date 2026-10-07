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
    echo '<section class="section"><div class="container narrow"><div class="card pad ss ss-card"><div class="ss-ico" aria-hidden="true">' . icon('briefcase') . '</div><h1 class="ss-title">This opening is closed</h1><p class="ss-lead">It may have been filled. See what’s open now.</p><div class="ss-acts ss-acts-inline"><a class="btn btn-primary" href="' . e(url('careers.php')) . '">See current openings</a></div></div></div></section>';
    page_footer();
    exit;
}

$user = current_user();
// Drivers already approved in onboarding don't apply again: they message the team on Telegram about an opening
$onbStage = $user && !$user['is_admin'] ? (string) (onboarding_row((int) $user['id'])['stage'] ?? '') : '';
$onboarded = is_onboarded($onbStage);
$existing = $user ? db_one('SELECT * FROM applications WHERE user_id = ? AND job_id = ?', [$user['id'], $id]) : null;
$external = $job['apply_method'] === 'url' && $job['apply_url'] !== '';
$resumeMode = (string) $job['resume'];
$onFile = $user ? db_one("SELECT * FROM documents WHERE user_id = ? AND kind = 'resume' ORDER BY id DESC LIMIT 1", [$user['id']]) : null;
$errors = [];
$form = $user ? apply_form_values($user) : [];
$cities = walmart_cities();

// "Resend email" on the success screen: the same next-steps email again (3 times an hour at most)
if (is_post() && ($_POST['action'] ?? '') === 'resend_email') {
    $user = require_verified();
    csrf_check();
    $note = null;
    if ($existing && $existing['email_sent'] !== '') {
        if (rate_limited('apply_resend', (string) $user['id'], 3, 3600)) {
            $note = ['error', 'We already sent it a few times. Please check your spam folder, or contact us.'];
        } else {
            record_hit('apply_resend', (string) $user['id']);
            $note = send_onboarding_email((int) $existing['id'], true) !== '' ? ['success', 'Sent again. Check your inbox.'] : ['error', 'The email could not be sent. Please contact us.'];
        }
    }
    $_SESSION['just_applied'] = ['id' => (int) ($existing['id'] ?? 0), 'note' => $note];
    redirect('job.php?id=' . $id . '#applied');
}

if (is_post() && !$external) {
    $user = require_verified();
    csrf_check();
    if ($onboarded) {
        redirect('job.php?id=' . $id . '&apply=1#apply'); // shows the "message us on Telegram" note instead of the form
    }
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
        $_SESSION['just_applied'] = ['id' => $appId, 'note' => null]; // the apply pop-up turns into "Application sent! Check your email"
        redirect('job.php?id=' . $id . '#applied');
    }
}

// Just applied (or tapped "Resend email"): show the success screen once
$justApplied = null;
if (!empty($_SESSION['just_applied'])) {
    $ja = $_SESSION['just_applied'];
    unset($_SESSION['just_applied']);
    if ($existing && (int) $ja['id'] === (int) $existing['id']) {
        $justApplied = $existing + ['note' => $ja['note']];
    }
}

$types = job_type_labels($job);
$urgent = $job['hiring_timeline'] === '1-3d';
page_header($job['title'], 'careers', job_excerpt($job));
$here = 'job.php?id=' . $id;
?>
<section class="job-band"><div class="container job-band-row">
  <nav class="job-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('careers.php')) ?>" data-back="careers.php">Careers</a><span aria-hidden="true">/</span><span><?= e($id === 0 ? 'Driver network' : (JOB_CATEGORIES[$job['category']] ?? 'Opportunity')) ?></span></nav>
  <div class="tags">
    <?php if ($id === 0): ?><span class="tag tag-solid">Always open</span><?php endif; ?>
    <?php if ($urgent): ?><span class="tag tag-urgent">⚡ Urgently hiring</span><?php endif; ?>
    <?php if ($job['status'] !== 'open'): ?><span class="tag">Closed (staff view)</span><?php endif; ?>
  </div>
</div></section>

<section class="section job-section">
  <div class="container job-wrap">
    <a class="job-back" href="<?= e(url('careers.php')) ?>" data-back="careers.php"><span class="job-back-ico"><?= icon('arrow') ?></span>All jobs</a>
    <article class="card pad prose job-card">
      <header class="job-title-row">
        <h1 class="job-title"><?= e($job['title']) ?></h1>
        <p class="job-where">LamazonLoads &ndash; <?= e(trim((string) $job['location']) !== '' ? $job['location'] : 'United States') ?></p>
        <p class="job-posted"><?= icon('clock') ?><?= e(job_posted_label($job)) ?><?= $id !== 0 && !empty($job['created_at']) ? ' · ' . e(fmt_date((string) $job['created_at'], 'F j, Y')) : '' ?></p>
      </header>
      <div class="tags job-badges">
        <?php foreach ($types as $t): ?><span class="tag"><?= icon('briefcase') ?><?= e($t) ?></span><?php endforeach; ?>
        <span class="tag"><?= icon('users') ?><?= e(job_hiring_label($job)) ?></span>
        <?php if ((int) $job['fair_chance']): ?><span class="tag tag-fair"><?= icon('handshake') ?>Fair chance</span><?php endif; ?>
      </div>
      <div class="job-meta">
        <?php foreach (['Location' => [$job['location'], 'pin'], 'Equipment' => [$job['equipment'], 'truck'], 'Pay' => [$job['pay'], 'dollar']] as $k => [$v, $ic]): if ($v === '' || $v === 'Not applicable') continue; ?>
          <div><span class="jm-ico"><?= icon($ic) ?></span><span><small><?= e($k) ?></small><b><?= e($v) ?></b></span></div>
        <?php endforeach; ?>
      </div>
      <h2 class="job-h">About this opportunity</h2>
      <div class="job-desc"><?= job_description_html((string) $job['description']) ?></div>
      <?php $reqs = array_filter(array_map('trim', preg_split('/\R/', (string) $job['requirements']))); if ($reqs): ?>
        <h2 class="job-h">Requirements</h2>
        <ul class="checklist">
          <?php foreach ($reqs as $r): ?><li><span class="tick"><?= icon('check') ?></span><span><?= e(ltrim($r, "-•●* ")) ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ((int) $job['background_check'] || (int) $job['fair_chance'] || $job['hiring_timeline'] !== ''): ?>
        <h2 class="job-h">Good to know</h2>
        <ul class="checklist">
          <?php if ((int) $job['background_check']): ?><li><span class="tick"><?= icon('shield') ?></span><span>This job requires a background check.</span></li><?php endif; ?>
          <?php if ((int) $job['fair_chance']): ?><li><span class="tick"><?= icon('handshake') ?></span><span>Fair chance employer: we're open to hiring people with a criminal record.</span></li><?php endif; ?>
          <?php if ($job['hiring_timeline'] !== ''): ?><li><span class="tick"><?= icon('clock') ?></span><span>We plan to hire within <?= e(strtolower(HIRING_TIMELINES[$job['hiring_timeline']] ?? '')) ?>.</span></li><?php endif; ?>
        </ul>
      <?php endif; ?>
      <?php if ((int) $job['contact_by_email'] && $job['contact_email'] !== ''): ?>
        <p class="mt job-contact"><?= icon('mail') ?> Questions about this job? Email <a href="mailto:<?= e($job['contact_email']) ?>?subject=<?= rawurlencode('Question: ' . $job['title']) ?>"><?= e($job['contact_email']) ?></a></p>
      <?php endif; ?>
    </article>

    <div class="card job-cta" id="apply-now">
      <?php if ($existing): ?>
        <div><span class="eyebrow">Your application</span><h2>You've applied</h2>
          <p class="muted mb-0">Applied on <?= e(fmt_date($existing['created_at'])) ?> · Status: <?= status_badge($existing['status']) ?></p></div>
        <a class="btn btn-primary btn-lg" href="<?= e(url('account.php')) ?>">Go to my dashboard <?= icon('arrow') ?></a>
      <?php elseif ($job['status'] !== 'open'): ?>
        <div><span class="eyebrow">Closed</span><h2>This opening is closed</h2><p class="muted mb-0">It may have been filled. New openings are posted on our Careers page.</p></div>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('careers.php')) ?>">See current openings</a>
      <?php elseif ($external): ?>
        <div><span class="eyebrow">Interested?</span><h2>Apply for this job</h2><p class="muted mb-0">Applications for this job are taken on another website.</p></div>
        <a class="btn btn-accent btn-lg" href="<?= e($job['apply_url']) ?>" target="_blank" rel="noopener nofollow">Apply on the company site <?= icon('arrow') ?></a>
      <?php elseif ($onboarded && $onbStage === 'contract'): ?>
        <div><span class="eyebrow">You’re approved</span><h2>Finish your onboarding</h2>
          <p class="muted mb-0">Sign your driver agreement, then message our team on Telegram about this opening.</p></div>
        <a class="btn btn-accent btn-lg" href="<?= e(url('onboarding.php')) ?>">Sign my agreement <?= icon('arrow') ?></a>
      <?php elseif ($onboarded): ?>
        <div><span class="eyebrow">Already onboarded</span><h2>Interested in this opening?</h2>
          <p class="muted mb-0">You’re part of our driver network, so there’s no need to apply. Message our team on Telegram and we’ll take it from there.</p></div>
        <a class="btn btn-accent btn-lg" href="<?= e(telegram_link()) ?>" target="_blank" rel="noopener"><?= icon('send') ?> Message us on Telegram</a>
      <?php else: ?>
        <div><span class="eyebrow">Interested?</span><h2>Ready to roll with LamazonLoads?</h2>
          <p class="muted mb-0">Applying takes about two minutes: your name, phone, location and vehicle.</p></div>
        <a class="btn btn-accent btn-lg" href="#apply" data-modal-open="apply">Apply now <?= icon('arrow') ?></a>
      <?php endif; ?>
    </div>
    <a class="job-back-bottom" href="<?= e(url('careers.php')) ?>" data-back="careers.php"><?= icon('arrow') ?>Back to all jobs</a>
  </div>
</section>

<?php if (!$existing && $job['status'] === 'open' && !$external): ?>
<?php $openNow = $errors || isset($_GET['apply']); ?>
<div class="modal<?= $openNow ? ' is-open' : '' ?>" id="apply" data-modal role="dialog" aria-modal="true" aria-labelledby="apply-title"<?= $openNow ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#apply-now" data-modal-close aria-label="Close"></a>
  <div class="modal-panel">
    <header class="modal-head">
      <div><span class="eyebrow"><?= $onboarded ? 'Interested?' : 'Apply now' ?></span><h2 id="apply-title"><?= e($job['title']) ?></h2></div>
      <a class="modal-x" href="#apply-now" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <?php if ($user && !is_verified($user)): ?>
        <div class="ss modal-ss"><div class="ss-ico" aria-hidden="true"><?= icon('mail') ?></div><h3 class="ss-title" tabindex="-1" data-autofocus>Confirm your email first</h3>
          <p class="ss-lead">Check your inbox for our confirmation link.</p>
          <?= ss_chip((string) $user['email']) ?>
          <?= ss_hint('After that, applying takes about 2 minutes.', 'clock') ?>
          <div class="ss-acts"><a class="btn btn-accent btn-block" href="<?= e(url('verify.php')) ?>">Confirm my email <?= icon('arrow') ?></a></div></div>
      <?php elseif (!$user): ?>
        <div class="ss modal-ss"><div class="ss-ico" aria-hidden="true"><?= icon('user') ?></div><h3 class="ss-title" tabindex="-1" data-autofocus>Sign in to apply</h3>
          <p class="ss-lead">The application takes about 2 minutes.</p>
          <ul class="ss-tags" aria-label="We'll ask for"><li>Name</li><li>Phone</li><li>Location</li><li>Vehicle</li></ul>
          <div class="ss-acts">
            <a class="btn btn-accent btn-block" href="<?= e(url('register.php?next=' . rawurlencode($here . '&apply=1'))) ?>">Create account &amp; apply</a>
            <a class="btn btn-ghost btn-block" href="<?= e(url('login.php?next=' . rawurlencode($here . '&apply=1'))) ?>">I already have an account</a>
          </div></div>
      <?php elseif ($onboarded && $onbStage === 'contract'): ?>
        <div class="ss modal-ss"><div class="ss-ico" aria-hidden="true"><?= icon('edit') ?></div><h3 class="ss-title" tabindex="-1" data-autofocus>You’re approved</h3>
          <p class="ss-lead">One step left: sign your driver agreement to finish onboarding.</p>
          <?= ss_hint('After that, message our team on Telegram about this opening, and we’ll take it from there.', 'send') ?>
          <div class="ss-acts"><a class="btn btn-accent btn-block" href="<?= e(url('onboarding.php')) ?>">Sign my agreement <?= icon('arrow') ?></a></div></div>
      <?php elseif ($onboarded): ?>
        <div class="ss modal-ss"><div class="ss-ico" aria-hidden="true"><?= icon('truck') ?><span class="ss-badge"><?= icon('check') ?></span></div><h3 class="ss-title" tabindex="-1" data-autofocus>You’re already onboarded</h3>
          <p class="ss-lead">You’re part of the LamazonLoads driver network, so there’s no need to apply again.</p>
          <?= ss_chip(telegram_handle(), 'send') ?>
          <?= ss_hint('Interested in this opening? Message our team on Telegram with the job title, and we’ll take it from there.', 'chat') ?>
          <div class="ss-acts">
            <a class="btn btn-accent btn-block" href="<?= e(telegram_link()) ?>" target="_blank" rel="noopener"><?= icon('send') ?> Message us on Telegram</a>
            <a class="btn btn-ghost btn-block" href="<?= e(url('account.php')) ?>">Go to my dashboard</a>
          </div></div>
      <?php else: ?>
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

          <?= vehicle_picker($form['vehicles'], $form['vehicle_other'], 'af-set', false, 'Your vehicle') ?>
          <p class="af-note" data-suv-note<?= $form['vehicles'] && !array_intersect($form['vehicles'], DISPATCH_VEHICLES) ? '' : ' hidden' ?>><?= icon('route') ?><span>For SUVs and other vehicles, we currently have openings for the <b>Walmart daily route</b> only.</span></p>

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
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($justApplied):
    $mailed = (string) $justApplied['email_sent'] !== '';
    $earlier = $mailed && strtotime((string) $justApplied['email_sent_at']) < strtotime((string) $justApplied['created_at']) - 120; // same email went out for an earlier application
    $webmail = $mailed ? webmail_link((string) $user['email']) : []; ?>
<div class="modal is-open" id="applied" data-modal role="dialog" aria-modal="true" aria-labelledby="applied-title">
  <a class="modal-backdrop" href="#apply-now" data-modal-close aria-label="Close"></a>
  <div class="modal-panel sent-panel">
    <a class="modal-x sent-x" href="#apply-now" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    <div class="modal-body sent-body ss">
      <div class="ss-ico" aria-hidden="true"><?= icon('mail') ?><span class="ss-badge"><?= icon('check') ?></span></div>
      <h2 class="ss-title" id="applied-title" tabindex="-1" data-autofocus>Application sent</h2>
      <?php if ($mailed): ?>
        <p class="ss-lead"><?= $earlier ? 'We already emailed your next steps on ' . e(fmt_date((string) $justApplied['email_sent_at'], 'M j')) . '.' : 'Your next step is waiting in your inbox.' ?></p>
        <?= ss_chip((string) $user['email']) ?>
        <?php if ($justApplied['note']): ?><p class="ss-note is-<?= e($justApplied['note'][0]) ?>" role="status"><?= icon($justApplied['note'][0] === 'success' ? 'check' : 'alert') ?><?= e($justApplied['note'][1]) ?></p><?php endif; ?>
        <?= ss_steps([
            ['Open our email', 'From ' . mail_from()],
            ['Tap “Upload my documents”', 'Send your documents on our website'],
            ['We review and get you started', 'You’ll hear from us by email'],
        ]) ?>
        <?= ss_hint('Not in your inbox? Check spam or promotions.') ?>
      <?php else: ?>
        <p class="ss-lead">Our team will review it and get back to you.</p>
        <?= ss_chip((string) $user['email']) ?>
      <?php endif; ?>
      <div class="ss-acts">
        <?php if ($webmail): ?>
          <a class="btn btn-accent btn-block" href="<?= e($webmail[1]) ?>" target="_blank" rel="noopener"><?= e($webmail[0]) ?> <?= icon('external') ?></a>
        <?php else: ?>
          <a class="btn btn-accent btn-block" href="#apply-now" data-modal-close>Got it</a>
        <?php endif; ?>
        <div class="ss-links">
          <?php if ($mailed): ?><form method="post" action="<?= e(url($here)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="resend_email"><button class="link-btn" type="submit"><?= icon('send') ?>Resend email</button></form><?php endif; ?>
          <a href="<?= e(url('careers.php')) ?>"><?= icon('briefcase') ?>Browse more jobs</a>
          <?php if (!$mailed): ?><a href="<?= e(url('account.php')) ?>"><?= icon('user') ?>My dashboard</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php page_footer();
