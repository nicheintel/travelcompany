<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $slot = (string) ($_POST['slot'] ?? '');
    if ($action === 'upload' && isset(PHOTO_SLOTS[$slot])) {
        if ($slot === 'gallery' && (int) db_val("SELECT COUNT(*) FROM site_photos WHERE slot = 'gallery'") >= GALLERY_MAX) {
            flash('error', 'The gallery is full (' . GALLERY_MAX . ' photos). Remove one first.');
        } else {
            [$file, $err] = save_site_photo($_FILES['photo'] ?? null);
            if ($err !== '') {
                flash('error', $err);
            } else {
                if ($slot !== 'gallery') { // one photo per vehicle: the new one replaces the old one
                    foreach (db_all('SELECT file FROM site_photos WHERE slot = ?', [$slot]) as $old) {
                        delete_site_photo_file($old['file']);
                    }
                    db_run('DELETE FROM site_photos WHERE slot = ?', [$slot]);
                }
                db_run('INSERT INTO site_photos (slot, file, caption, created_at) VALUES (?, ?, ?, NOW())', [$slot, $file, post('caption', 160)]);
                flash('success', PHOTO_SLOTS[$slot] . ': photo added. It shows on the website now.');
            }
        }
    } elseif ($action === 'delete') {
        $row = db_one('SELECT * FROM site_photos WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        if ($row) {
            delete_site_photo_file($row['file']);
            db_run('DELETE FROM site_photos WHERE id = ?', [$row['id']]);
            flash('success', $row['slot'] === 'gallery' ? 'Photo removed from the gallery.' : PHOTO_SLOTS[$row['slot']] . ': photo removed. The drawing shows again.');
        }
    }
    redirect('admin/photos.php');
}
$gallery = gallery_photos();

page_header('Site photos');
admin_open('photos');
?>
<h1>Site photos</h1>
<p class="muted">Upload real photos of your vehicles and your team on the road. JPG, PNG or WebP, up to 12 MB. Photos from your phone are turned the right way up and resized automatically.</p>

<div class="card pad">
  <h2 style="font-size:1.2rem">Vehicles in “Meet the fleet”</h2>
  <p class="muted">Until you upload a photo, the website shows the LamazonLoads drawing. Wide, side-on photos look best.</p>
  <div class="ph-slots">
    <?php foreach (FLEET as $k => $v): $p = site_photo($k); ?>
      <div class="ph-slot">
        <div class="ph-preview"><?= $p ? '<img src="' . e(media_url($p['file'])) . '" alt="">' : vehicle_svg($k) ?></div>
        <b><?= e($v['name']) ?></b> <span class="badge <?= $p ? 'badge-approved' : 'badge-closed' ?>"><?= $p ? 'Your photo' : 'Drawing' ?></span>
        <form method="post" action="<?= e(url('admin/photos.php')) ?>" enctype="multipart/form-data" class="ph-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="slot" value="<?= e($k) ?>">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="Photo for <?= e($v['name']) ?>">
          <input type="text" name="caption" maxlength="160" placeholder="Description (optional)" aria-label="Description">
          <button class="btn btn-primary btn-sm" type="submit"><?= icon('upload') ?> <?= $p ? 'Replace photo' : 'Upload photo' ?></button>
        </form>
        <?php if ($p): ?>
          <form method="post" action="<?= e(url('admin/photos.php')) ?>" data-confirm="Remove this photo? The drawing will show again.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Remove</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card pad">
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
