<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$cat = as_str($_GET['category'] ?? '');
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
$showNetwork = network_enabled() && $cat === '' && (!$savedOnly || in_array(0, $savedIds, true));
// "♥ Saved (N)": only saved jobs that can still be shown (open posts, and the network application while it's on)
$savedCount = $savedIds ? (int) db_val("SELECT COUNT(*) FROM jobs WHERE status = 'open' AND id IN (" . implode(',', array_map('intval', $savedIds)) . ')')
    + (network_enabled() && in_array(0, $savedIds, true) ? 1 : 0) : 0;
$used = array_column(db_all("SELECT DISTINCT category FROM jobs WHERE status = 'open'"), 'category');

preload_photo('banner_careers');
page_header('Careers & opportunities', 'careers', 'Open opportunities at LamazonLoads: owner-operator dispatch, daily routes, dispatch team and driver support jobs.');
page_hero('Careers & opportunities', 'Open opportunities', 'Create an account, then apply in one click. Your profile travels with every application.', 'banner_careers', 'center 30%');
?>
<section class="section">
  <div class="container">
    <div class="filters" role="navigation" aria-label="Filter openings">
      <a href="<?= e(url('careers.php')) ?>"<?= $cat === '' && !$savedOnly ? ' class="on"' : '' ?>>All openings</a>
      <?php foreach (JOB_CATEGORIES as $k => $label): if (!in_array($k, $used, true)) continue; ?>
        <a href="<?= e(url('careers.php?category=' . $k)) ?>"<?= $cat === $k ? ' class="on"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($me): ?><a href="<?= e(url('careers.php?saved=1')) ?>"<?= $savedOnly ? ' class="on"' : '' ?>>♥ Saved (<?= $savedCount ?>)</a><?php endif; ?>
    </div>
    <h2 class="sr-only">Openings</h2>
    <div class="jc-grid">
      <?php foreach ($jobs as $j) echo job_card($j); ?>
      <?php if ($showNetwork) echo job_card(network_job()); ?>
    </div>
    <?php if (!$jobs && !$showNetwork): ?>
      <div class="card empty"><?php if ($savedOnly): ?>No saved jobs yet. Tap the ♡ on a job to save it for later. <a href="<?= e(url('careers.php')) ?>">See all openings</a>
        <?php elseif ($cat !== ''): ?>No openings in this category right now. <a href="<?= e(url('careers.php')) ?>">See all openings</a>
        <?php else: ?>No openings right now. New jobs are posted here first<?= $me ? '' : ': <a href="' . e(url('register.php')) . '">create an account</a> to be ready when they open' ?>.<?php endif; ?></div>
    <?php endif; ?>
  </div>
</section>
<?php cta_band(); page_footer();
