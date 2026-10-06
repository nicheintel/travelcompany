<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$u = require_verified();
$maxMb = (int) config('max_upload_mb');
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
    if (!isset(DOC_KINDS[$kind])) {
        $errors[] = 'Please choose the document type.';
    } else {
        [$docId, $err] = save_upload($_FILES['file'] ?? null, (int) $u['id'], $kind);
        if ($err !== '') {
            $errors[] = $err;
        } else {
            auto_review_user((int) $u['id']);
            flash('success', DOC_KINDS[$kind] . ' uploaded.');
            redirect('documents.php');
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
    <div><label for="file">File (PDF, JPG or PNG; resumes also Word; up to <?= $maxMb ?> MB)</label><input id="file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
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
          <td><b><?= e(DOC_KINDS[$d['kind']] ?? $d['kind']) ?></b><?php if ($d['added_by']): ?><br><span class="doc-staff"><?= icon('shield') ?> Added by LamazonLoads staff</span><?php endif; ?></td>
          <td><?= doc_link($d, e($d['original_name'])) ?> <span class="muted">(<?= e(number_format($d['size'] / 1024, 0)) ?> KB)</span></td>
          <td><?= e(fmt_date($d['created_at'])) ?></td>
          <td>
            <div class="row-actions"><?= doc_link($d, icon('eye') . ' View', 'btn btn-primary btn-sm') ?>
            <form method="post" action="<?= e(url('documents.php')) ?>" class="inline-form" data-confirm="Remove this document?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Remove</button>
            </form></div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
