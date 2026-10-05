<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$cat = (string) ($_GET['category'] ?? '');
if (!isset(JOB_CATEGORIES[$cat])) {
    $cat = '';
}
$me = current_user();
$savedOnly = $me && isset($_GET['saved']);
$savedIds = member_job_marks()['saved'];
$jobs = $cat === ''
    ? db_all("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC, id DESC")
    : db_all("SELECT * FROM jobs WHERE status = 'open' AND category = ? ORDER BY created_at DESC, id DESC", [$cat]);
if ($savedOnly) {
    $jobs = array_values(array_filter($jobs, fn ($j) => in_array((int) $j['id'], $savedIds, true)));
}
$showNetwork = $cat === '' && (!$savedOnly || in_array(0, $savedIds, true));
$used = array_column(db_all("SELECT DISTINCT category FROM jobs WHERE status = 'open'"), 'category');

page_header('Careers & opportunities', 'careers', 'Open opportunities at LamazonLoads: owner-operator dispatch, daily routes, dispatch team and driver support jobs.');
page_hero('Careers & opportunities', 'Open opportunities', 'Create a free account, then apply in one click. Your profile and documents travel with every application.');
?>
<section class="section">
  <div class="container">
    <div class="filters" role="navigation" aria-label="Filter openings">
      <a href="<?= e(url('careers.php')) ?>"<?= $cat === '' && !$savedOnly ? ' class="on"' : '' ?>>All openings</a>
      <?php foreach (JOB_CATEGORIES as $k => $label): if (!in_array($k, $used, true)) continue; ?>
        <a href="<?= e(url('careers.php?category=' . $k)) ?>"<?= $cat === $k ? ' class="on"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($me): ?><a href="<?= e(url('careers.php?saved=1')) ?>"<?= $savedOnly ? ' class="on"' : '' ?>>♥ Saved (<?= count($savedIds) ?>)</a><?php endif; ?>
    </div>
    <div class="jc-grid">
      <?php foreach ($jobs as $j) echo job_card($j); ?>
      <?php if ($showNetwork) echo job_card(network_job()); ?>
    </div>
    <?php if (!$jobs && !$showNetwork): ?>
      <div class="card empty"><?= $savedOnly ? 'No saved jobs yet. Tap the ♡ on a job to save it for later.' : 'No openings in this category right now.' ?> <a href="<?= e(url('careers.php')) ?>">See all openings</a></div>
    <?php endif; ?>
  </div>
</section>
<?php cta_band(); page_footer();
