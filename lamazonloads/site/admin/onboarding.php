<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
$setErrors = [];
if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    require_full_admin_action('admin/onboarding.php'); // every action here is a setting (Telegram link, QR code)
    if ($action === 'settings') {
        $link = trim(post('telegram_link', 200));
        if (!preg_match('#^https://(t\.me|telegram\.me)/[A-Za-z0-9_+/\-]+$#', $link)) {
            $setErrors[] = 'Please enter a Telegram link like https://t.me/LLDriverOnboarding';
        } else {
            meta_set('telegram_link', $link);
            meta_set('onboarding_notify', !empty($_POST['notify']) ? '1' : '0');
            flash('success', 'Onboarding settings saved.');
            redirect('admin/onboarding.php#settings');
        }
    } elseif ($action === 'qr') {
        $f = $_FILES['qr'] ?? null;
        $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = $f && ($f['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        if (!isset($types[$mime]) || $f['size'] > 3 * 1024 * 1024 || !@getimagesize($f['tmp_name'])) {
            $setErrors[] = 'Please upload your QR code as a PNG, JPG or WebP image (up to 3 MB).';
        } else {
            $name = 'telegram-qr-' . bin2hex(random_bytes(6)) . '.' . $types[$mime];
            if (move_uploaded_file($f['tmp_name'], dirname(__DIR__) . '/media/' . $name)) {
                $old = meta_get('telegram_qr');
                if ($old !== '') @unlink(dirname(__DIR__) . '/media/' . basename($old));
                meta_set('telegram_qr', $name);
                flash('success', 'QR code updated. Drivers see it on their last onboarding step.');
            }
            redirect('admin/onboarding.php#settings');
        }
    } elseif ($action === 'qr_reset') {
        $old = meta_get('telegram_qr');
        if ($old !== '') @unlink(dirname(__DIR__) . '/media/' . basename($old));
        meta_set('telegram_qr', '');
        flash('success', 'Back to the built-in QR code.');
        redirect('admin/onboarding.php#settings');
    }
}

$counts = array_column(db_all('SELECT stage, COUNT(*) n FROM onboarding GROUP BY stage'), 'n', 'stage');
$stage = (string) ($_GET['stage'] ?? '');
if (!isset(ONB_STAGES[$stage]) && $stage !== 'all') {
    $stage = !empty($counts['review']) ? 'review' : 'all';
}
$rows = db_all('SELECT o.*, u.name, u.email, u.phone FROM onboarding o JOIN users u ON u.id = o.user_id'
    . ($stage !== 'all' ? ' WHERE o.stage = ?' : '') . " ORDER BY o.stage = 'review' DESC, o.updated_at DESC LIMIT 300", $stage !== 'all' ? [$stage] : []);
$qr = telegram_qr_url();

page_header('Onboarding');
admin_open('onboarding');
echo admin_head('Driver onboarding', 'Drivers upload their documents on the website. Review them here, approve, and the agreement and Telegram link go out automatically.',
    is_full_admin() ? '<a class="btn btn-ghost" href="' . e(url('admin/contracts.php')) . '">' . icon('file') . ' Contracts</a>' : '');
?>
<div class="onb-flow card">
  <?php foreach ([['upload', 'Driver uploads documents'], ['check', 'You review & approve'], ['edit', 'Driver signs agreement'], ['send', 'Telegram link sent']] as $i => [$ic, $t]): ?>
    <div><span><?= icon($ic) ?></span><b><?= $i + 1 ?>.</b> <?= e($t) ?></div>
  <?php endforeach; ?>
</div>

<nav class="filters" aria-label="Onboarding stage">
  <a href="<?= e(url('admin/onboarding.php?stage=all')) ?>"<?= $stage === 'all' ? ' class="on"' : '' ?>>All (<?= array_sum($counts) ?>)</a>
  <?php foreach (ONB_STAGES as $k => $l): ?><a href="<?= e(url('admin/onboarding.php?stage=' . $k)) ?>"<?= $stage === $k ? ' class="on"' : '' ?>><?= e($l) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a><?php endforeach; ?>
</nav>

<section class="card panel">
  <?php if (!$rows): ?>
    <div class="panel-body"><div class="empty"><?= $stage === 'review' ? 'Nothing to review right now.' : 'No drivers here yet. Onboarding starts automatically when someone applies and gets the Dispatch or Walmart email.' ?></div></div>
  <?php else: ?>
  <div class="panel-body flush ml">
    <div class="ml-row onl-row ml-head" aria-hidden="true"><span>Driver</span><span>Program</span><span>Checklist</span><span>Stage</span><span class="ml-end">Updated</span></div>
    <?php foreach ($rows as $r): [$d, $t] = onboarding_progress((int) $r['user_id']); ?>
      <a class="ml-row onl-row" href="<?= e(url('admin/driver.php?id=' . (int) $r['user_id'] . '#onboarding')) ?>">
        <span class="ml-who"><span class="ov-av" aria-hidden="true"><?= e(strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span><span><b><?= e($r['name']) ?></b><small class="ml-contact"><span><?= e($r['email']) ?></span><?php if ($r['phone'] !== ''): ?><span><?= e($r['phone']) ?></span><?php endif; ?></small></span></span>
        <span><span class="badge badge-track-<?= e($r['track']) ?>"><?= e(ONB_TRACKS[$r['track']] ?? $r['track']) ?></span></span>
        <span class="onl-prog"><span class="bar"><i style="width:<?= round($d / max(1, $t) * 100) ?>%"></i></span><small><?= $d ?>/<?= $t ?></small></span>
        <span><span class="badge badge-stage-<?= e($r['stage']) ?>"><?= e(ONB_STAGES[$r['stage']] ?? $r['stage']) ?></span></span>
        <span class="ml-date"><?= e(fmt_date((string) $r['updated_at'], 'M j')) ?></span>
        <span class="ar-go"><?= icon('arrow') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if (is_full_admin()): ?>
<section class="card panel" id="settings">
  <header class="panel-head"><h2><?= icon('send') ?>Telegram & notifications</h2></header>
  <div class="panel-body onb-settings">
    <?php if ($setErrors): ?><ul class="errors"><?php foreach ($setErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="<?= e(url('admin/onboarding.php')) ?>" class="onb-set-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <label for="telegram_link">Telegram link drivers get when they finish</label>
      <input id="telegram_link" name="telegram_link" type="url" maxlength="200" value="<?= e(telegram_link()) ?>">
      <label class="check mt"><input type="checkbox" name="notify" value="1"<?= meta_get('onboarding_notify', '1') === '1' ? ' checked' : '' ?>> Email <?= e(support_email()) ?> when a driver submits documents or signs</label>
      <button class="btn btn-primary mt" type="submit">Save settings</button>
    </form>
    <div class="onb-qr-box">
      <span class="onb-qr-label">QR code on the last step</span>
      <?php if ($qr !== ''): ?><img src="<?= e($qr) ?>" alt="Telegram QR code" width="140" height="140"><?php else: ?><p class="muted">No QR code for this link yet. Upload yours.</p><?php endif; ?>
      <form method="post" action="<?= e(url('admin/onboarding.php')) ?>" enctype="multipart/form-data" class="onb-qr-up">
        <?= csrf_field() ?><input type="hidden" name="action" value="qr">
        <input type="file" name="qr" accept="image/png,image/jpeg,image/webp" required aria-label="QR code image">
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('upload') ?> Upload my QR</button>
      </form>
      <?php if (meta_get('telegram_qr') !== ''): ?>
        <form method="post" action="<?= e(url('admin/onboarding.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="qr_reset"><button class="link-btn" type="submit">Use the built-in QR instead</button></form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php dash_close(); page_footer();
