<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_full_admin();
$slotName = fn (string $slot): string => PHOTO_SLOTS[$slot] ?? (isset(PAGE_PHOTOS[$slot]) ? PAGE_PHOTOS[$slot][0] . ' → ' . PAGE_PHOTOS[$slot][1] : $slot);
if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $slot = (string) ($_POST['slot'] ?? '');
    $back = 'admin/photos.php';
    if ($action === 'upload' && (isset(PHOTO_SLOTS[$slot]) || isset(PAGE_PHOTOS[$slot]))) {
        $back .= $slot === 'gallery' ? '#gallery' : '?spot=' . $slot . '#spot-' . $slot;
        if ($slot === 'gallery' && (int) db_val("SELECT COUNT(*) FROM site_photos WHERE slot = 'gallery'") >= GALLERY_MAX) {
            flash('error', 'The gallery is full (' . GALLERY_MAX . ' photos). Remove one first.');
        } else {
            [$file, $err] = save_site_photo($_FILES['photo'] ?? null);
            if ($err !== '') {
                flash('error', $err);
            } else {
                if ($slot !== 'gallery') { // one photo per spot: the new one replaces the old one
                    foreach (db_all('SELECT file FROM site_photos WHERE slot = ?', [$slot]) as $old) {
                        delete_site_photo_file($old['file']);
                    }
                    db_run('DELETE FROM site_photos WHERE slot = ?', [$slot]);
                }
                db_run('INSERT INTO site_photos (slot, file, caption, created_at) VALUES (?, ?, ?, NOW())', [$slot, $file, post('caption', 160)]);
                flash('success', $slotName($slot) . ': photo updated. It shows on the website now.');
            }
        }
    } elseif ($action === 'delete') {
        $row = db_one('SELECT * FROM site_photos WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        if ($row) {
            delete_site_photo_file($row['file']);
            db_run('DELETE FROM site_photos WHERE id = ?', [$row['id']]);
            if ($row['slot'] === 'gallery') {
                flash('success', 'Photo removed from the gallery.');
                $back .= '#gallery';
            } else {
                flash('success', $slotName($row['slot']) . (isset(PAGE_PHOTOS[$row['slot']]) ? ': the original photo is back.' : ': photo removed. The drawing shows again.'));
                $back .= '?spot=' . $row['slot'] . '#spot-' . $row['slot'];
            }
        }
    }
    redirect($back);
}
$gallery = gallery_photos();
$openSpot = (string) ($_GET['spot'] ?? '');
$groups = [];
foreach (PAGE_PHOTOS as $key => $p) {
    $groups[$p[0]][$key] = $p;
}

/** "41 KB" for a file on disk. */
function photo_kb(string $file): string
{
    return is_file($file) ? max(1, (int) round(filesize($file) / 1024)) . ' KB' : '?';
}

/** Upload / restore controls for one spot. */
function photo_spot_forms(string $slot, ?array $current, string $uploadLabel, string $restoreLabel, string $confirm): void
{
    ?>
    <form method="post" action="<?= e(url('admin/photos.php')) ?>" enctype="multipart/form-data" class="ph-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="slot" value="<?= e($slot) ?>">
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="New photo">
      <input type="text" name="caption" maxlength="160" placeholder="Describe the photo (optional)" aria-label="Description">
      <button class="btn btn-primary btn-sm" type="submit"><?= icon('upload') ?> <?= e($uploadLabel) ?></button>
    </form>
    <?php if ($current): ?>
      <form method="post" action="<?= e(url('admin/photos.php')) ?>" data-confirm="<?= e($confirm) ?>" data-confirm-ok="<?= e($restoreLabel) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $current['id'] ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash') ?> <?= e($restoreLabel) ?></button>
      </form>
    <?php endif;
}

page_header('Site photos');
admin_open('photos');
?>
<?= admin_head('Site photos', 'Change any photo on the website, and go back to the original any time.') ?>
<div class="info-strip">
  <div><?= icon('upload') ?><span><b>Upload</b> a JPG, PNG or WebP up to 12 MB. Phone photos are turned the right way up and resized for you.</span></div>
  <div><?= icon('layers') ?><span><b>Fast loading:</b> every photo is saved as a compressed WebP in three sizes; phones get the smallest.</span></div>
  <div><?= icon('link') ?><span><b>Free to use:</b> the originals are Pexels and Unsplash stock photos; each shows its source link.</span></div>
</div>

<?php $gi = 0; foreach ($groups as $group => $spots): $gi++;
    $custom = count(array_filter(array_keys($spots), fn ($k) => site_photo($k) !== null));
    $open = $openSpot !== '' ? isset($spots[$openSpot]) : $gi === 1; ?>
  <details class="card ph-group"<?= $open ? ' open' : '' ?>>
    <summary><span><b><?= e($group) ?></b> <span class="muted"><?= count($spots) ?> photos<?= $custom ? ' · ' . $custom . ' replaced' : '' ?></span></span><?= icon('arrow', 'ic ph-chev') ?></summary>
    <div class="ph-slots">
      <?php foreach ($spots as $key => [, $label, $stock, $where]): $cur = site_photo($key); [$set] = photo_set($key); $small = $set[0][0];
          $big = end($set); $src = STOCK_SOURCES[$stock] ?? ''; ?>
        <div class="ph-slot<?= $openSpot === $key ? ' ph-hl' : '' ?>" id="spot-<?= e($key) ?>">
          <div class="ph-preview ph-photo"><img src="<?= e($small) ?>" alt="" loading="lazy"></div>
          <div><b><?= e($label) ?></b> <span class="badge <?= $cur ? 'badge-approved' : 'badge-closed' ?>"><?= $cur ? 'Your photo' : 'Original' ?></span></div>
          <small class="muted"><?= e($where) ?></small>
          <div class="ph-meta">
            <span title="File sizes visitors download"><?= icon('download') ?>WebP · <?= e(photo_kb($set[0][2])) ?> phone · <?= e(photo_kb($big[2])) ?> computer</span>
            <?php if ($cur): ?>
              <span><?= icon('upload') ?>Your upload · <a href="<?= e($big[0]) ?>" target="_blank" rel="noopener">View full size</a></span>
            <?php elseif ($src !== ''): ?>
              <span><?= icon('link') ?>Source: <a href="<?= e($src) ?>" target="_blank" rel="noopener noreferrer"><?= str_contains($src, 'pexels') ? 'Pexels' : 'Unsplash' ?></a> · <a href="<?= e($big[0]) ?>" target="_blank" rel="noopener">View full size</a></span>
            <?php endif; ?>
          </div>
          <?php photo_spot_forms($key, $cur, $cur ? 'Replace again' : 'Replace photo', 'Restore original', 'Go back to the original photo?'); ?>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
<?php endforeach; ?>

<details class="card ph-group"<?= isset(FLEET[$openSpot]) ? ' open' : '' ?>>
  <summary><span><b>Meet the fleet</b> <span class="muted">vehicle showroom · drawings until you add photos</span></span><?= icon('arrow', 'ic ph-chev') ?></summary>
  <div class="ph-slots">
    <?php foreach (FLEET as $k => $v): $p = site_photo($k); ?>
      <div class="ph-slot<?= $openSpot === $k ? ' ph-hl' : '' ?>" id="spot-<?= e($k) ?>">
        <div class="ph-preview"><?= $p ? '<img src="' . e(media_url($p['file'])) . '" alt="">' : vehicle_svg($k) ?></div>
        <div><b><?= e($v['name']) ?></b> <span class="badge <?= $p ? 'badge-approved' : 'badge-closed' ?>"><?= $p ? 'Your photo' : 'Drawing' ?></span></div>
        <small class="muted">Wide, side-on photos look best.</small>
        <?php photo_spot_forms($k, $p, $p ? 'Replace photo' : 'Upload photo', 'Remove photo', 'Remove this photo? The drawing will show again.'); ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<div class="card pad" id="gallery">
  <h2 style="font-size:1.2rem">On the road gallery <span class="muted" style="font-size:.9rem;font-weight:500">(<?= count($gallery) ?> of <?= GALLERY_MAX ?>)</span></h2>
  <p class="muted">Shown on the Home and About pages once you add at least one photo: drivers, vans, deliveries, your team.</p>
  <form method="post" action="<?= e(url('admin/photos.php')) ?>" enctype="multipart/form-data" class="ph-form ph-form-row">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="slot" value="gallery">
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="Gallery photo">
    <input type="text" name="caption" maxlength="160" placeholder="Description (optional)" aria-label="Description">
    <button class="btn btn-primary btn-sm" type="submit"><?= icon('upload') ?> Add to gallery</button>
  </form>
  <?php if ($gallery): ?>
    <div class="ph-gallery">
      <?php foreach ($gallery as $p): ?>
        <figure>
          <img src="<?= e(media_url($p['file'])) ?>" alt="">
          <figcaption><?= e($p['caption'] !== '' ? $p['caption'] : 'No description') ?></figcaption>
          <form method="post" action="<?= e(url('admin/photos.php')) ?>" data-confirm="Remove this photo from the gallery?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Remove</button>
          </form>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
