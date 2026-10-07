<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// The driver's files on record, view only. Uploads happen in onboarding (and staff can add files in Admin).
$u = require_verified();
$onb = onboarding_row((int) $u['id']);
if ($onb && $onb['stage'] !== 'done') {
    redirect('onboarding.php'); // until onboarding is finished, their files are on the onboarding checklist
}

$docs = db_all('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$u['id']]);
$groups = []; // newest file first in each type, types in the usual order
foreach (array_keys(DOC_KINDS) as $k) {
    foreach ($docs as $d) {
        if ($d['kind'] === $k) $groups[$k][] = $d;
    }
}
$icons = ['w9' => 'file', 'insurance' => 'shield', 'license' => 'user', 'vehicle_photo' => 'van', 'resume' => 'briefcase', 'registration' => 'file', 'authority' => 'shield', 'other' => 'file'];
$signed = $onb && $onb['signed_at'];

page_header('Documents');
dash_open('documents');
?>
<h1>Documents</h1>
<p class="muted">Your files on record with LamazonLoads. Only you and our team can open them.</p>

<?php if (!$groups && !$signed): ?>
  <div class="card ss ss-card">
    <div class="ss-ico" aria-hidden="true"><?= icon('file') ?></div>
    <h2 class="ss-title">No documents yet</h2>
    <p class="ss-lead">Your documents show up here after onboarding.</p>
    <div class="ss-acts ss-acts-inline"><a class="btn btn-primary" href="<?= e(url('careers.php')) ?>">Browse openings <?= icon('arrow') ?></a></div>
  </div>
<?php else: ?>
  <section class="card doc-list" data-dv-scope aria-label="Your documents">
    <?php if ($signed): ?>
      <div class="doc-row">
        <span class="doc-ico" aria-hidden="true"><?= icon('edit') ?></span>
        <span class="doc-info"><b>Signed agreement</b><small>Signed <?= e(fmt_date((string) $onb['signed_at'], 'M j, Y')) ?><span class="doc-extra"> · <?= e(ONB_TRACKS[$onb['track']] ?? '') ?></span></small></span>
        <a class="btn btn-ghost btn-sm doc-view" href="<?= e(url('contract.php')) ?>" aria-label="View signed agreement"><?= icon('eye') ?><span>View</span></a>
      </div>
    <?php endif; ?>
    <?php foreach ($groups as $k => $list): $d = $list[0]; $n = count($list); $staff = (bool) array_filter($list, fn ($x) => $x['added_by']); ?>
      <div class="doc-row">
        <span class="doc-ico" aria-hidden="true"><?= icon($icons[$k] ?? 'file') ?></span>
        <span class="doc-info">
          <b><?= e(DOC_KINDS[$k]) ?><?php if ($n > 1): ?> <span class="doc-count"><?= $n ?> <?= $k === 'vehicle_photo' ? 'photos' : 'files' ?></span><?php endif; ?></b>
          <small>Uploaded <?= e(fmt_date((string) $d['created_at'], 'M j, Y')) ?> · <?= e(doc_type_label($d)) ?><span class="doc-extra"> · <?= e(fmt_size((int) $d['size'])) ?></span><?= $staff ? ' · Added by our team' : '' ?></small>
        </span>
        <?php $nice = fn ($x) => DOC_KINDS[$k] . ' · ' . fmt_date((string) $x['created_at'], 'M j, Y'); // instead of a phone's random file name ?>
        <?= doc_link($d, icon('eye') . '<span>View</span>', 'btn btn-ghost btn-sm doc-view', '', $nice($d)) ?>
        <?php if ($n > 1): ?><span hidden><?php foreach (array_slice($list, 1) as $x) echo doc_link($x, 'View', '', '', $nice($x)); ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <p class="doc-help"><?= icon('info') ?><span>Need to update a document, like a new insurance card? <a href="<?= e(url('contact.php')) ?>" data-open-chat>Message us</a> and we’ll update it for you.</span></p>
<?php endif; ?>
<?php dash_close(); page_footer();
