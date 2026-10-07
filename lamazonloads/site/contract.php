<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// A signed agreement: the driver sees their own; staff can open anyone's (?user=ID). Exactly what was signed, ready to print.
$me = require_login();
$uid = $me['is_admin'] && isset($_GET['user']) ? (int) $_GET['user'] : (int) $me['id'];
$row = onboarding_row($uid);
$owner = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
if (!$row || !$row['signed_at'] || !$owner) {
    http_response_code(404);
    page_header('Agreement not found');
    echo '<section class="section"><div class="container narrow"><div class="card pad"><h1>No signed agreement yet</h1><p class="muted">Once the agreement is signed, the signed copy shows up here.</p>'
        . '<a class="btn btn-primary" href="' . e(url($me['is_admin'] && $uid !== (int) $me['id'] ? 'admin/driver.php?id=' . $uid : 'onboarding.php')) . '">Back</a></div></div></section>';
    page_footer();
    exit;
}
$back = $me['is_admin'] && $uid !== (int) $me['id'] ? 'admin/driver.php?id=' . $uid . '#onboarding' : 'onboarding.php';
page_header((string) $row['contract_title'], '', '', 'page-contract');
?>
<section class="section contract-page">
  <div class="container contract-wrap">
    <div class="contract-tools no-print">
      <a class="back-link" href="<?= e(url($back)) ?>"><?= icon('chev-left') ?> Back</a>
      <button type="button" class="btn btn-primary btn-sm" data-print><?= icon('download') ?> Print or save as PDF</button>
    </div>
    <article class="card contract-doc">
      <header class="contract-doc-head"><?= logo_html() ?><span class="badge badge-open"><?= icon('check') ?> Signed <?= e(fmt_date((string) $row['signed_at'], 'M j, Y')) ?></span></header>
      <h1><?= e((string) $row['contract_title']) ?></h1>
      <div class="contract-body"><?= contract_html((string) $row['contract_text']) ?></div>
      <div class="sig-block">
        <div><small>Signature</small><img src="<?= e((string) $row['signature']) ?>" alt="Signature of <?= e((string) $row['signed_name']) ?>"></div>
        <div><small>Printed name</small><b><?= e((string) $row['signed_name']) ?></b></div>
        <div><small>Date</small><b><?= e(fmt_date((string) $row['signed_at'], 'F j, Y')) ?></b></div>
      </div>
      <?php if ($row['emergency_name']): ?>
        <h3>Emergency Contact Information</h3>
        <div class="sig-block sig-block-3">
          <div><small>Emergency contact name</small><b><?= e((string) $row['emergency_name']) ?></b></div>
          <div><small>Relationship</small><b><?= e((string) $row['emergency_relation']) ?></b></div>
          <div><small>Phone number</small><b><?= e((string) $row['emergency_phone']) ?></b></div>
        </div>
      <?php endif; ?>
      <p class="contract-meta">Signed electronically on lamazonloads.com by <?= e((string) $row['signed_name']) ?> (<?= e((string) $owner['email']) ?>) on <?= e(fmt_date((string) $row['signed_at'], 'F j, Y \a\t g:i a T')) ?>. Agreement version <?= (int) $row['contract_version'] ?>.</p>
    </article>
  </div>
</section>
<?php page_footer();
