<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();
$maxMb = (int) config('max_upload_mb');
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
$errors = [];

if (is_post()) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $doc = db_one('SELECT * FROM documents WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $u['id']]);
        if ($doc) {
            @unlink(__DIR__ . '/uploads/' . $doc['stored_name']);
            db_run('DELETE FROM documents WHERE id = ?', [$doc['id']]);
            flash('success', 'Document removed.');
        }
        redirect('documents.php');
    }
    $kind = post('kind', 20);
    $f = $_FILES['file'] ?? null;
    if (!isset(DOC_KINDS[$kind])) {
        $errors[] = 'Please choose the document type.';
    } elseif (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a file to upload.';
    } elseif (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > $maxMb * 1024 * 1024) {
        $errors[] = "That file is too big. The limit is {$maxMb} MB.";
    } elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $errors[] = 'The upload did not finish. Please try again.';
    } elseif ((int) db_val('SELECT COUNT(*) FROM documents WHERE user_id = ?', [$u['id']]) >= 40) {
        $errors[] = 'You have reached the document limit. Remove old documents first.';
    } else {
        // Check what the file really is, not just its name.
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset($allowed[$mime])) {
            $errors[] = 'Please upload a PDF, JPG or PNG file.';
        } else {
            $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $stored)) {
                $errors[] = 'Could not save the file. Please make sure the uploads folder is writable.';
            } else {
                $name = mb_substr(preg_replace('/[^\w .()\-]+/u', '_', basename((string) $f['name'])) ?: 'document', 0, 190);
                db_run('INSERT INTO documents (user_id, kind, stored_name, original_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                    [$u['id'], $kind, $stored, $name, $mime, (int) $f['size']]);
                flash('success', DOC_KINDS[$kind] . ' uploaded.');
                redirect('documents.php');
            }
        }
    }
}

$docs = db_all('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$u['id']]);
$have = array_column($docs, 'kind');

page_header('Documents');
dash_open('documents');
?>
<h1>Documents</h1>
<p class="muted">Upload your onboarding documents once. They're private: only you and LamazonLoads staff can open them.</p>
<?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="card form-card">
  <h3 class="mt-0"><?= icon('upload') ?> Upload a document</h3>
  <form method="post" action="<?= e(url('documents.php')) ?>" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <div><label for="kind">Document type</label>
      <select id="kind" name="kind" required><option value="">Choose…</option>
        <?php foreach (DOC_KINDS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?><?= in_array($k, $have, true) ? ' ✓' : '' ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="file">File (PDF, JPG or PNG, up to <?= $maxMb ?> MB)</label><input id="file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"></div>
    <div class="full"><button class="btn btn-accent" type="submit"><?= icon('upload') ?> Upload</button></div>
  </form>
</div>

<div class="card pad">
  <h3 class="mt-0">Your documents</h3>
  <?php if (!$docs): ?>
    <div class="empty">No documents yet. Start with your W-9, certificate of insurance and driver's license.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><b><?= e(DOC_KINDS[$d['kind']] ?? $d['kind']) ?></b></td>
          <td><a href="<?= e(url('doc.php?id=' . (int) $d['id'])) ?>"><?= e($d['original_name']) ?></a> <span class="muted">(<?= e(number_format($d['size'] / 1024, 0)) ?> KB)</span></td>
          <td><?= e(fmt_date($d['created_at'])) ?></td>
          <td>
            <form method="post" action="<?= e(url('documents.php')) ?>" class="inline-form" data-confirm="Remove this document?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
