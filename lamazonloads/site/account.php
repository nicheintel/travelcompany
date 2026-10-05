<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();
$steps = onboarding_steps((int) $u['id']);
$done = count(array_filter($steps, fn ($s) => $s[1]));
$pct = (int) round($done / count($steps) * 100);
$apps = db_all('SELECT a.*, j.title, j.status AS job_status FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.user_id = ? ORDER BY a.created_at DESC', [$u['id']]);
$docCount = (int) db_val('SELECT COUNT(*) FROM documents WHERE user_id = ?', [$u['id']]);
$saved = db_all("SELECT j.* FROM saved_jobs s JOIN jobs j ON j.id = s.job_id WHERE s.user_id = ? AND j.status = 'open' ORDER BY s.created_at DESC", [$u['id']]);
if (db_val('SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_id = 0', [$u['id']])) {
    $saved[] = network_job();
}

page_header('My dashboard');
dash_open('overview');
?>
<h1>Hi, <?= e(explode(' ', $u['name'])[0]) ?> 👋</h1>
<p class="muted">Here's where you stand with LamazonLoads.</p>

<div class="stats">
  <div class="card stat"><small>Onboarding</small><b><?= $pct ?>%</b></div>
  <div class="card stat"><small>Applications</small><b><?= count($apps) ?></b></div>
  <div class="card stat"><small>Documents on file</small><b><?= $docCount ?></b></div>
</div>

<div class="card pad">
  <h3 class="mt-0">Onboarding checklist</h3>
  <div class="progress" aria-label="Onboarding <?= $pct ?>% complete"><span style="width:<?= $pct ?>%"></span></div>
  <ul class="checklist">
    <?php foreach ($steps as [$label, $ok, $href]): ?>
      <li><span class="tick<?= $ok ? '' : ' todo' ?>"><?= $ok ? icon('check') : icon('clock') ?></span>
        <span><?= e($label) ?><?php if (!$ok): ?> · <a href="<?= e(url($href)) ?>">Do it now</a><?php endif; ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="card pad">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h3 class="mt-0 mb-0">My applications</h3>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('careers.php')) ?>">Browse openings <?= icon('arrow') ?></a>
  </div>
  <?php if (!$apps): ?>
    <div class="empty">You haven't applied yet. <a href="<?= e(url('careers.php')) ?>">See open opportunities</a> or <a href="<?= e(url('job.php?id=0')) ?>">join the driver network</a>.</div>
  <?php else: ?>
    <div class="table-wrap mt"><table>
      <thead><tr><th>Opening</th><th>Applied</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td><a href="<?= e(url('job.php?id=' . (int) $a['job_id'])) ?>"><?= e((int) $a['job_id'] === 0 ? 'LamazonLoads driver network' : ($a['title'] ?? 'Removed opening')) ?></a></td>
          <td><?= e(fmt_date($a['created_at'])) ?></td>
          <td><?= status_badge($a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<div class="card pad">
  <h3 class="mt-0">Saved jobs</h3>
  <?php if (!$saved): ?>
    <div class="empty">Tap the ♡ on a job to save it here. <a href="<?= e(url('careers.php')) ?>">Browse openings</a></div>
  <?php else: ?>
    <div class="jc-grid jc-grid-2 mt"><?php foreach ($saved as $j) echo job_card($j); ?></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
