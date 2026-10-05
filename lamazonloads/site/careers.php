<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$cat = (string) ($_GET['category'] ?? '');
if (!isset(JOB_CATEGORIES[$cat])) {
    $cat = '';
}
$jobs = $cat === ''
    ? db_all("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC, id DESC")
    : db_all("SELECT * FROM jobs WHERE status = 'open' AND category = ? ORDER BY created_at DESC, id DESC", [$cat]);
$used = array_column(db_all("SELECT DISTINCT category FROM jobs WHERE status = 'open'"), 'category');

page_header('Careers & opportunities', 'careers', 'Open opportunities at LamazonLoads: owner-operator dispatch, daily routes, dispatch team and driver support jobs.');
page_hero('Careers & opportunities', 'Open opportunities', 'Create a free account, then apply in one click. Your profile and documents travel with every application.');
?>
<section class="section">
  <div class="container">
    <div class="filters" role="navigation" aria-label="Filter openings">
      <a href="<?= e(url('careers.php')) ?>"<?= $cat === '' ? ' class="on"' : '' ?>>All openings</a>
      <?php foreach (JOB_CATEGORIES as $k => $label): if (!in_array($k, $used, true)) continue; ?>
        <a href="<?= e(url('careers.php?category=' . $k)) ?>"<?= $cat === $k ? ' class="on"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="grid grid-3">
      <?php foreach ($jobs as $j) echo job_card($j); ?>
      <article class="card job-card reveal" style="border:2px dashed #C9DAFF;background:#FAFCFF">
        <div class="tags"><span class="tag tag-amber">Always open</span></div>
        <h3><a href="<?= e(url('job.php?id=0')) ?>">Join the LamazonLoads driver network</a></h3>
        <p>Don't see the right fit? Apply once to join our network and we'll reach out when loads, routes or openings match your equipment and area.</p>
        <a class="btn btn-primary btn-sm" href="<?= e(url('job.php?id=0')) ?>">Apply to the network <?= icon('arrow') ?></a>
      </article>
    </div>
    <?php if (!$jobs && $cat !== ''): ?><p class="muted mt">No openings in this category right now. <a href="<?= e(url('careers.php')) ?>">See all openings</a>.</p><?php endif; ?>
  </div>
</section>
<?php cta_band(); page_footer();
