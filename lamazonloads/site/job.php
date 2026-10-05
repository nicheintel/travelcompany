<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id === 0) {
    // The always-open general application.
    $job = [
        'id' => 0, 'title' => 'Join the LamazonLoads driver network', 'category' => 'other', 'location' => 'USA',
        'equipment' => 'Cargo Van, Sprinter, Box Truck & more', 'pay' => 'Depends on load / route', 'schedule' => 'You choose',
        'summary' => 'Apply once to join our network of drivers and owner-operators.',
        'description' => "Join the network and complete your onboarding. When loads, daily routes or new openings match your equipment, ZIP code and availability, our team reaches out to you first.",
        'requirements' => "Qualified vehicle (cargo van, Sprinter, box truck or other)\nValid driver's license\nInsurance and W-9\nUp-to-date ZIP code and availability in your profile",
        'status' => 'open',
    ];
} else {
    $job = db_one('SELECT * FROM jobs WHERE id = ?', [$id]);
    if (!$job || ($job['status'] !== 'open' && !is_admin())) {
        http_response_code(404);
        page_header('Opening not found', 'careers');
        echo '<section class="section"><div class="container narrow"><div class="card pad center"><h1>This opening is no longer available</h1><p class="muted">It may have been filled or closed.</p><a class="btn btn-primary" href="' . e(url('careers.php')) . '">See current openings</a></div></div></section>';
        page_footer();
        exit;
    }
}

$user = current_user();
$existing = $user ? db_one('SELECT * FROM applications WHERE user_id = ? AND job_id = ?', [$user['id'], $id]) : null;
$errors = [];

if (is_post()) {
    $user = require_login();
    csrf_check();
    $message = post('message', 3000);
    if ($job['status'] !== 'open') {
        $errors[] = 'This opening is closed.';
    } elseif ($existing) {
        $errors[] = 'You already applied for this opening.';
    } else {
        db_run('INSERT INTO applications (user_id, job_id, message, status, created_at, updated_at) VALUES (?, ?, ?, \'new\', NOW(), NOW())', [$user['id'], $id, $message]);
        flash('success', 'Application sent! We will review it and contact you. Complete your profile and documents to speed things up.');
        redirect('account.php');
    }
}

page_header($job['title'], 'careers', $job['summary']);
$here = 'job.php?id=' . $id;
?>
<section class="page-hero"><div class="container">
  <a href="<?= e(url('careers.php')) ?>" style="color:#9DB4E8">&larr; All openings</a>
  <div class="tags" style="margin:16px 0 10px"><span class="tag tag-solid"><?= e($id === 0 ? 'Always open' : (JOB_CATEGORIES[$job['category']] ?? 'Opportunity')) ?></span><?php if ($job['status'] !== 'open'): ?><span class="tag">Closed (staff view)</span><?php endif; ?></div>
  <h1><?= e($job['title']) ?></h1>
  <p class="lead"><?= e($job['summary']) ?></p>
</div></section>

<section class="section">
  <div class="container story" style="align-items:start;grid-template-columns:1.4fr 1fr">
    <div class="card pad prose">
      <div class="job-meta" style="margin-top:0">
        <?php foreach (['Location' => $job['location'], 'Equipment' => $job['equipment'], 'Pay' => $job['pay'], 'Schedule' => $job['schedule']] as $k => $v): if ($v === '') continue; ?>
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
    </div>

    <aside class="card pad" id="apply" style="position:sticky;top:96px">
      <?php if ($existing): ?>
        <h3>You've applied</h3>
        <p>Status: <?= status_badge($existing['status']) ?></p>
        <p class="muted">Applied on <?= e(fmt_date($existing['created_at'])) ?>. Keep your profile and documents up to date so we can move fast.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('account.php')) ?>">Go to my dashboard</a>
      <?php elseif ($job['status'] !== 'open'): ?>
        <h3>This opening is closed</h3>
        <a class="btn btn-ghost btn-block" href="<?= e(url('careers.php')) ?>">See current openings</a>
      <?php elseif (!$user): ?>
        <h3>Apply in one click</h3>
        <p class="muted">Create a free account or sign in to apply. Your profile and documents are attached to every application.</p>
        <a class="btn btn-accent btn-block" href="<?= e(url('register.php?next=' . rawurlencode($here))) ?>">Create account &amp; apply</a>
        <a class="btn btn-ghost btn-block mt" style="margin-top:10px" href="<?= e(url('login.php?next=' . rawurlencode($here))) ?>">I already have an account</a>
      <?php else: ?>
        <h3>Apply now</h3>
        <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= e(url($here)) ?>#apply">
          <?= csrf_field() ?>
          <label for="message">Anything we should know? <span class="opt">(optional)</span></label>
          <textarea id="message" name="message" maxlength="3000" placeholder="Your equipment, preferred lanes, when you can start…"><?= e(post('message', 3000)) ?></textarea>
          <p class="hint">Applying as <?= e($user['name']) ?> (<?= e($user['email']) ?>)</p>
          <button class="btn btn-accent btn-block" type="submit">Send application <?= icon('arrow') ?></button>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php page_footer();
