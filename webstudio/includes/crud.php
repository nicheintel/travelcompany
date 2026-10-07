<?php
/**
 * One editor for the simple lists on the dashboard (pricing packages, portfolio, testimonials,
 * FAQs): list, add, edit, show/hide, reorder and delete. Each page passes a description of its
 * fields; see admin/packages.php for an example.
 */
declare(strict_types=1);
defined('WS_APP') || exit;

/**
 * $cfg keys:
 *  table, active (sidebar key), title, singular, intro,
 *  fields: name => ['label', 'type' => text|textarea|lines|price|checkbox|select|theme|image|url|rating,
 *                   'required', 'max', 'help', 'options', 'placeholder', 'wide']
 *  title_field: field shown as the row title,
 *  row: fn(array $row): string — extra HTML under the row title (already escaped),
 *  thumb: fn(array $row): string — optional HTML on the left of the row.
 */
function crud_page(array $cfg): never
{
    require_admin();
    $table = $cfg['table'];
    $base = url('admin/' . basename((string) $_SERVER['SCRIPT_NAME']));

    if (is_post()) {
        verify_csrf();
        $action = post('action');
        $id = (int) post('id');
        $row = $id ? db_one("SELECT * FROM `$table` WHERE id = ?", [$id]) : null;
        if ($action === 'delete' && $row) {
            db_run("DELETE FROM `$table` WHERE id = ?", [$id]);
            if (!empty($row['image'])) delete_upload($row['image']);
            flash(ucfirst($cfg['singular']) . ' deleted.');
            redirect($base);
        }
        if ($action === 'toggle' && $row) {
            db_run("UPDATE `$table` SET is_active = 1 - is_active, updated_at = ? WHERE id = ?", [now_utc(), $id]);
            flash($row['is_active'] ? 'Hidden from the website.' : 'Now showing on the website.');
            redirect($base);
        }
        if (($action === 'up' || $action === 'down') && $row) {
            crud_move($table, $id, $action);
            redirect($base);
        }
        if ($action === 'save') {
            [$values, $errors] = crud_collect($cfg, $row);
            if ($errors) {
                crud_render($cfg, $base, $row, $values, $errors);
            }
            $values['updated_at'] = now_utc();
            if ($row) {
                $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($values)));
                db_run("UPDATE `$table` SET $sets WHERE id = ?", [...array_values($values), $id]);
                if (array_key_exists('image', $values) && !empty($row['image']) && $row['image'] !== $values['image']) {
                    delete_upload($row['image']);
                }
                flash(ucfirst($cfg['singular']) . ' saved.');
            } else {
                $values['created_at'] = now_utc();
                $values['sort_order'] = (int) db_value("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `$table`");
                $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($values)));
                $marks = implode(', ', array_fill(0, count($values), '?'));
                db_run("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($values));
                flash(ucfirst($cfg['singular']) . ' added.');
            }
            redirect($base);
        }
        redirect($base);
    }

    $editId = (int) param('edit');
    if ($editId || param('new') === '1') {
        $row = $editId ? db_one("SELECT * FROM `$table` WHERE id = ?", [$editId]) : null;
        if ($editId && !$row) redirect($base);
        crud_render($cfg, $base, $row, $row ?? [], []);
    }
    crud_list($cfg, $base);
}

/** Reads and checks the form. Returns [values for the database, errors]. */
function crud_collect(array $cfg, ?array $row): array
{
    $values = [];
    $errors = [];
    foreach ($cfg['fields'] as $name => $f) {
        $type = $f['type'] ?? 'text';
        $label = $f['label'];
        if ($type === 'checkbox') {
            $values[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }
        if ($type === 'image') {
            $values[$name] = $row[$name] ?? null;
            if (post($name . '_remove') === '1') $values[$name] = null;
            $file = $_FILES[$name] ?? null;
            if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = save_uploaded_image($file);
                if (str_ends_with($saved, '.jpg')) $values[$name] = $saved;
                else $errors[$name] = $saved;
            }
            continue;
        }
        $v = post($name);
        if ($type !== 'textarea' && $type !== 'lines') $v = str_replace("\n", ' ', $v);
        if (!empty($f['required']) && $v === '') {
            $errors[$name] = $label . ' is required.';
        } elseif (isset($f['max']) && mb_strlen($v) > $f['max']) {
            $errors[$name] = $label . ' must be ' . $f['max'] . ' characters or fewer.';
        }
        if ($type === 'price') {
            $clean = str_replace([',', ' ', setting('currency')], '', $v);
            if ($clean === '') {
                $v = null; // "Custom quote"
            } elseif (!is_numeric($clean) || (float) $clean < 0 || (float) $clean > 99999999) {
                $errors[$name] = 'Enter the price as a number, e.g. 12999 (or leave it empty for "Custom quote").';
            } else {
                $v = round((float) $clean, 2);
            }
        } elseif ($type === 'url') {
            $u = clean_url($v);
            if ($u === null) $errors[$name] = 'Enter a full web address, e.g. https://example.com';
            else $v = $u;
        } elseif ($type === 'select' || $type === 'theme') {
            $options = $type === 'theme' ? array_keys(THEMES) : array_keys($f['options']);
            if (!in_array($v, array_map('strval', $options), true)) $v = (string) $options[0];
        } elseif ($type === 'rating') {
            $v = max(1, min(5, (int) $v));
        } elseif ($type === 'lines') {
            $v = implode("\n", lines($v));
        }
        $values[$name] = $v;
    }
    return [$values, $errors];
}

function crud_move(string $table, int $id, string $dir): void
{
    $rows = db_all("SELECT id FROM `$table` ORDER BY sort_order, id");
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $pos = array_search($id, $ids, true);
    $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
    if ($pos === false || !isset($ids[$swap])) return;
    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
    foreach ($ids as $i => $rid) {
        db_run("UPDATE `$table` SET sort_order = ? WHERE id = ?", [($i + 1) * 10, $rid]);
    }
}

function crud_list(array $cfg, string $base): never
{
    $rows = db_all("SELECT * FROM `{$cfg['table']}` ORDER BY sort_order, id");
    $add = '<a class="btn btn-primary" href="' . e($base . '?new=1') . '">' . icon('plus') . ' Add ' . e($cfg['singular']) . '</a>';
    admin_start($cfg['title'], $cfg['active'], $add);
    ?>
    <p class="page-intro"><?= e($cfg['intro']) ?></p>
    <?php if (!$rows): ?>
      <div class="empty card">
        <span class="empty-icon"><?= icon(ADMIN_NAV[$cfg['active']][1]) ?></span>
        <h2>Nothing here yet</h2>
        <p class="muted"><?= e($cfg['empty'] ?? 'Add your first one and it will appear on the website.') ?></p>
        <?= $add ?>
      </div>
    <?php else: ?>
      <div class="card list-card">
        <ul class="crud-list">
          <?php foreach ($rows as $i => $r): ?>
            <li class="crud-row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
              <div class="order-btns">
                <?php foreach (['up' => 'Move up', 'down' => 'Move down'] as $dir => $label):
                    $disabled = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $dir ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="icon-btn icon-btn-sm" type="submit" title="<?= $label ?>" aria-label="<?= $label ?>"<?= $disabled ? ' disabled' : '' ?>><?= icon('chevron-down', 'icon' . ($dir === 'up' ? ' flip' : '')) ?></button>
                  </form>
                <?php endforeach ?>
              </div>
              <?php if (isset($cfg['thumb'])): ?><div class="crud-thumb"><?= $cfg['thumb']($r) ?></div><?php endif ?>
              <a class="crud-main" href="<?= e($base . '?edit=' . $r['id']) ?>">
                <b><?= e($r[$cfg['title_field']]) ?></b>
                <?= isset($cfg['row']) ? $cfg['row']($r) : '' ?>
              </a>
              <form method="post" class="crud-toggle">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="pill-toggle<?= $r['is_active'] ? ' is-on' : '' ?>" title="<?= $r['is_active'] ? 'Showing on the website — click to hide' : 'Hidden — click to show on the website' ?>">
                  <span class="pt-dot"></span><?= $r['is_active'] ? 'Showing' : 'Hidden' ?>
                </button>
              </form>
              <div class="crud-actions">
                <a class="icon-btn" href="<?= e($base . '?edit=' . $r['id']) ?>" title="Edit" aria-label="Edit"><?= icon('edit') ?></a>
                <form method="post" data-confirm="Delete “<?= e($r[$cfg['title_field']]) ?>”? This can't be undone.">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button class="icon-btn icon-btn-danger" type="submit" title="Delete" aria-label="Delete"><?= icon('trash') ?></button>
                </form>
              </div>
            </li>
          <?php endforeach ?>
        </ul>
      </div>
      <p class="hint"><?= icon('eye') ?> Changes show on the website right away. <a href="<?= e(url() . ($cfg['anchor'] ?? '')) ?>" target="_blank" rel="noopener">See it live</a></p>
    <?php endif ?>
    <?php
    admin_end();
    exit;
}

function crud_render(array $cfg, string $base, ?array $row, array $values, array $errors): never
{
    $isNew = !$row;
    $hasImage = (bool) array_filter($cfg['fields'], fn($f) => ($f['type'] ?? '') === 'image');
    admin_start(($isNew ? 'Add ' : 'Edit ') . $cfg['singular'], $cfg['active'], '<a class="btn btn-ghost" href="' . e($base) . '">' . icon('arrow-left') . ' Back</a>');
    ?>
    <form method="post" class="card form-card"<?= $hasImage ? ' enctype="multipart/form-data"' : '' ?> novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php if ($row): ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><?php endif ?>
      <?php if ($errors): ?><div class="alert alert-error"><?= icon('alert') ?><span>Please fix the highlighted fields.</span></div><?php endif ?>
      <div class="form-grid">
        <?php foreach ($cfg['fields'] as $name => $f):
            $type = $f['type'] ?? 'text';
            $v = $values[$name] ?? ($f['default'] ?? '');
            $id = 'f-' . $name;
            $err = $errors[$name] ?? null;
            $wide = !empty($f['wide']) || in_array($type, ['textarea', 'lines', 'image', 'theme'], true); ?>
          <div class="field<?= $wide ? ' field-full' : '' ?><?= $type === 'checkbox' ? ' field-check' : '' ?>">
            <?php if ($type === 'checkbox'): ?>
              <label class="switch"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= (array_key_exists($name, $values) ? (int) $values[$name] : (int) ($f['default'] ?? 0)) ? ' checked' : '' ?>><span class="switch-ui"></span><span><b><?= e($f['label']) ?></b><?php if (!empty($f['help'])): ?><small><?= e($f['help']) ?></small><?php endif ?></span></label>
            <?php else: ?>
              <label for="<?= $id ?>"><?= e($f['label']) ?><?= !empty($f['required']) ? ' <em>*</em>' : '' ?></label>
              <?php if ($type === 'textarea' || $type === 'lines'): ?>
                <textarea id="<?= $id ?>" name="<?= e($name) ?>" rows="<?= $type === 'lines' ? 8 : 5 ?>"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?> placeholder="<?= e($f['placeholder'] ?? '') ?>"><?= e((string) $v) ?></textarea>
              <?php elseif ($type === 'select'): ?>
                <select id="<?= $id ?>" name="<?= e($name) ?>">
                  <?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e((string) $ov) ?>"<?= (string) $v === (string) $ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?>
                </select>
              <?php elseif ($type === 'rating'): ?>
                <select id="<?= $id ?>" name="<?= e($name) ?>">
                  <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= (int) ($v ?: 5) === $s ? ' selected' : '' ?>><?= str_repeat('★', $s) . str_repeat('☆', 5 - $s) ?> (<?= $s ?>)</option><?php endfor ?>
                </select>
              <?php elseif ($type === 'theme'): ?>
                <div class="theme-pick" role="radiogroup" aria-label="<?= e($f['label']) ?>">
                  <?php foreach (THEMES as $key => $t): ?>
                    <label class="theme-opt" style="--ta:<?= e($t['a']) ?>;--tb:<?= e($t['b']) ?>">
                      <input type="radio" name="<?= e($name) ?>" value="<?= e($key) ?>"<?= ($v ?: 'royal') === $key ? ' checked' : '' ?>>
                      <span class="theme-swatch"></span><small><?= e($t['name']) ?></small>
                    </label>
                  <?php endforeach ?>
                </div>
              <?php elseif ($type === 'image'): ?>
                <?php if (!empty($v)): ?>
                  <div class="image-current">
                    <img src="<?= e(uploaded_image_url((string) $v)) ?>" alt="">
                    <label class="check"><input type="checkbox" name="<?= e($name) ?>_remove" value="1"> Remove this picture</label>
                  </div>
                <?php endif ?>
                <input id="<?= $id ?>" class="file-input" type="file" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp">
              <?php else: ?>
                <input id="<?= $id ?>" type="text"<?= $type === 'price' ? ' inputmode="decimal"' : ($type === 'url' ? ' inputmode="url"' : '') ?> name="<?= e($name) ?>" value="<?= e((string) $v) ?>"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?> placeholder="<?= e($f['placeholder'] ?? '') ?>">
              <?php endif ?>
              <?php if (!empty($f['help'])): ?><small class="help"><?= e($f['help']) ?></small><?php endif ?>
            <?php endif ?>
            <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isNew ? 'Add ' . e($cfg['singular']) : 'Save changes' ?></button>
        <a class="btn btn-ghost" href="<?= e($base) ?>">Cancel</a>
      </div>
    </form>
    <?php
    admin_end();
    exit;
}

// ---------- Photo uploads ----------

/**
 * Saves an uploaded picture as a new JPEG (max 1600px wide) in uploads/. Re-saving it means
 * only real images end up on the server. Returns the file name, or an error message.
 */
function save_uploaded_image(array $file): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'That picture is too big. Please use one under ' . ini_get('upload_max_filesize') . 'B.';
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) return 'The upload didn\'t work. Please try again.';
    if ((int) $file['size'] > 10 * 1024 * 1024) return 'That picture is too big. Please use one under 10 MB.';
    if (!function_exists('imagecreatefromstring')) {
        return 'Picture uploads need PHP\'s GD extension. In XAMPP: open php.ini, remove the ; before extension=gd, then restart Apache.';
    }
    $info = @getimagesize((string) $file['tmp_name']);
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Please upload a JPG, PNG or WebP picture.';
    }
    if ($info[0] * $info[1] > 40_000_000) return 'That picture has too many pixels. Please use a smaller one.';
    $src = @imagecreatefromstring((string) file_get_contents((string) $file['tmp_name']));
    if (!$src) return 'That picture couldn\'t be read. Please try another one.';
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, 1600 / $w);
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // transparent PNGs get a white background
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $ok = imagejpeg($dst, dirname(__DIR__) . '/uploads/' . $name, 85);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? $name : 'The picture couldn\'t be saved. Check that the uploads folder can be written to.';
}

function delete_upload(string $file): void
{
    if (preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) {
        @unlink(dirname(__DIR__) . '/uploads/' . $file);
    }
}
