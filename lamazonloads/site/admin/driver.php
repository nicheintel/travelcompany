<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) {
    redirect('admin/drivers.php');
}

$edit = null;      // the "Edit information" form when it has problems
$editErrors = [];
$docErrors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'details') {
        // Staff fill in the member's details and driver profile (same checks as the member's own pages)
        $edit = profile_row($id) + ['name' => post('name', 100), 'phone' => format_phone(post('phone', 25)), 'account_type' => post('account_type', 30)];
        $editErrors = profile_from_post($edit);
        if ($edit['name'] === '') array_unshift($editErrors, 'Please enter their name.');
        if ($edit['phone'] !== '' && !preg_match('/^[0-9+()\-. ]{7,25}$/', $edit['phone'])) $editErrors[] = 'Please enter a valid phone number, or leave it empty.';
        if (!isset(ACCOUNT_TYPES[$edit['account_type']])) $editErrors[] = 'Please choose what describes them best.';
        if (!$editErrors) {
            db_run('UPDATE users SET name = ?, phone = ?, account_type = ? WHERE id = ?', [$edit['name'], $edit['phone'], $edit['account_type'], $id]);
            profile_save($id, $edit);
            flash('success', $edit['name'] . "'s information was saved.");
            redirect('admin/driver.php?id=' . $id);
        }
    } elseif ($action === 'upload') {
        // Documents the member emailed in: saved to their account like their own uploads, marked as added by staff
        $kind = post('kind', 20);
        if (!isset(DOC_KINDS[$kind])) {
            $docErrors[] = 'Please choose the document type.';
        } else {
            [$docId, $err] = save_upload($_FILES['file'] ?? null, $id, $kind);
            if ($err !== '') {
                $docErrors[] = str_replace('You have reached', 'This member has reached', $err);
            } else {
                db_run('UPDATE documents SET added_by = ? WHERE id = ?', [$me['id'], $docId]);
                auto_review_user($id);
                flash('success', DOC_KINDS[$kind] . ' added to ' . $u['name'] . "'s documents.");
                redirect('admin/driver.php?id=' . $id . '#documents');
            }
        }
    } elseif ($action === 'remove_doc') {
        $doc = db_one('SELECT * FROM documents WHERE id = ? AND user_id = ?', [(int) ($_POST['doc'] ?? 0), $id]);
        if ($doc) {
            @unlink(dirname(__DIR__) . '/uploads/' . basename($doc['stored_name']));
            db_run('DELETE FROM documents WHERE id = ?', [$doc['id']]);
            db_run('UPDATE applications SET resume_doc_id = NULL WHERE resume_doc_id = ?', [$doc['id']]);
            flash('success', (DOC_KINDS[$doc['kind']] ?? 'Document') . ' removed.');
        }
        redirect('admin/driver.php?id=' . $id . '#documents');
    } elseif ($action === 'admin' && $id !== (int) $me['id']) {
        $make = !empty($_POST['make']) ? 1 : 0;
        db_run('UPDATE users SET is_admin = ?, session_version = session_version + 1 WHERE id = ?', [$make, $id]);
        flash('success', $make ? $u['name'] . ' is now staff.' : $u['name'] . ' is no longer staff.');
    } elseif ($action === 'verify') {
        db_run('UPDATE users SET email_verified_at = NOW(), verify_token = NULL, verify_expires = NULL WHERE id = ?', [$id]);
        flash('success', $u['name'] . "'s email is now marked as confirmed.");
    } elseif ($action === 'resend') {
        $sent = send_verification($u);
        flash($sent ? 'success' : 'error', $sent ? 'Confirmation email sent to ' . $u['email'] . '.' : "The email couldn't be sent. Check the email settings, or mark the email as confirmed by hand.");
    } elseif ($action === 'password') {
        // For members who forgot their password: staff set a temporary one and share it by phone.
        $temp = temp_password();
        db_run('UPDATE users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1 WHERE id = ?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
        flash('info', "Temporary password for {$u['name']}: $temp (share it privately; they'll choose their own password when they sign in).");
    } elseif ($action === 'delete' && $id !== (int) $me['id']) {
        $told = !empty($_POST['notify']) && send_account_closed($u, 'staff');
        delete_member($id);
        flash('success', $u['name'] . ' was deleted.' . (!empty($_POST['notify']) ? ($told ? ' We emailed them that their account was closed.' : " The email to them couldn't be sent.") : ''));
        redirect('admin/drivers.php');
    }
    if (!$editErrors && !$docErrors) {
        redirect('admin/driver.php?id=' . $id);
    }
}

$p = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$id]);
$docs = db_all('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC', [$id]);
$apps = db_all('SELECT a.*, j.title FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.user_id = ? ORDER BY a.created_at DESC', [$id]);
$steps = onboarding_steps($id);
$done = count(array_filter($steps, fn ($s) => $s[1]));
$self = 'admin/driver.php?id=' . $id;
$addedBy = $u['added_by'] ? ((string) db_val('SELECT name FROM users WHERE id = ?', [$u['added_by']]) ?: 'staff') : '';
$staffNames = array_column(db_all('SELECT DISTINCT u.id, u.name FROM documents d JOIN users u ON u.id = d.added_by WHERE d.user_id = ?', [$id]), 'name', 'id');
$edit ??= ($p ?? array_fill_keys(array_keys(PROFILE_FIELDS), '')) + ['name' => $u['name'], 'phone' => $u['phone'], 'account_type' => $u['account_type']];

page_header($u['name']);
admin_open('drivers');
?>
<a href="<?= e(url('admin/drivers.php')) ?>">&larr; All members</a>
<div class="admin-head" style="margin-top:10px">
  <h1 class="mt-0"><?= e($u['name']) ?></h1>
  <a class="btn btn-primary" href="#edit" data-modal-open="edit"><?= icon('edit') ?> Edit information</a>
</div>
<p class="muted"><?= e(ACCOUNT_TYPES[$u['account_type']] ?? '') ?> · joined <?= e(fmt_date($u['created_at'])) ?><?= $addedBy ? ' · added by ' . e($addedBy) : '' ?>
  · <?= is_verified($u) ? 'email confirmed' : '<span class="badge badge-reviewing">Email not confirmed</span>' ?>
  <?= !empty($u['must_change_password']) ? ' · <span class="badge badge-reviewing">Hasn\'t chosen a password yet</span>' : '' ?></p>
<div class="card pad onb-card">
  <h3 class="mt-0">Onboarding <span class="muted">(<?= $done ?>/<?= count($steps) ?>)</span></h3>
  <ul class="onb-list"><?php foreach ($steps as [$label, $ok]): ?><li class="<?= $ok ? 'ok' : '' ?>"><?= icon($ok ? 'check' : 'clock') ?><?= e($label) ?></li><?php endforeach; ?></ul>
</div>

<div class="grid grid-2">
  <div class="card pad">
    <h3 class="mt-0">Contact</h3>
    <p class="mb-0"><?= icon('mail') ?> <a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a><br><?= icon('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $u['phone'])) ?>"><?= e($u['phone']) ?></a></p>
  </div>
  <div class="card pad">
    <h3 class="mt-0">Equipment &amp; area</h3>
    <?php if (!$p): ?><p class="muted mb-0">Profile not filled in yet.</p><?php else: ?>
      <p class="mb-0"><b><?= e(EQUIPMENT[$p['equipment']] ?? '—') ?></b><?= $p['vehicle'] ? ' · ' . e($p['vehicle']) : '' ?><br>
      ZIP <?= e($p['home_zip'] ?: '—') ?><?= $p['service_radius'] ? ' · runs ' . e($p['service_radius']) : '' ?><br>
      <?= e(AVAILABILITY[$p['availability']] ?? '') ?><?= $p['years_experience'] ? ' · ' . e($p['years_experience']) . ' yrs experience' : '' ?></p>
    <?php endif; ?>
  </div>
</div>
<?php if ($p): ?>
<div class="card pad">
  <h3 class="mt-0">Business &amp; insurance</h3>
  <div class="job-meta" style="margin:0">
    <div><small>Company</small><b><?= e($p['company_name'] ?: '—') ?></b></div>
    <div><small>MC #</small><b><?= e($p['mc_number'] ?: '—') ?></b></div>
    <div><small>USDOT #</small><b><?= e($p['dot_number'] ?: '—') ?></b></div>
    <div><small>Insurance</small><b><?= e($p['insurance_provider'] ?: '—') ?></b></div>
    <div><small>Insurance expires</small><b<?= $p['insurance_expires'] && $p['insurance_expires'] < date('Y-m-d') ? ' style="color:var(--red)"' : '' ?>><?= e($p['insurance_expires'] ? fmt_date($p['insurance_expires']) : '—') ?></b></div>
  </div>
  <?php if ($p['about']): ?><p class="mt mb-0"><b>Notes:</b> <?= nl2br(e($p['about'])) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card pad" id="documents">
  <h3 class="mt-0">Documents</h3>
  <form method="post" action="<?= e(url($self)) ?>" enctype="multipart/form-data" class="doc-add">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div><label for="kind">Add a document they emailed you</label><?= select_html('kind', DOC_KINDS, $docErrors ? post('kind', 20) : '', 'Document type…') ?></div>
    <div><label for="file">File (PDF, JPG or PNG; resumes also Word; up to <?= (int) config('max_upload_mb') ?> MB)</label><input id="file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
    <div><button class="btn btn-accent" type="submit"><?= icon('upload') ?> Add document</button></div>
  </form>
  <?php if ($docErrors): ?><ul class="errors"><?php foreach ($docErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if (!$docs): ?><p class="muted mb-0">No documents yet.</p><?php else: ?>
  <div class="table-wrap"><table><tbody><?php foreach ($docs as $d): ?>
    <tr><td><b><?= e(DOC_KINDS[$d['kind']] ?? $d['kind']) ?></b><?php if ($d['added_by']): ?><br><span class="doc-staff" title="Added by <?= e($staffNames[$d['added_by']] ?? 'staff') ?>"><?= icon('shield') ?> Added by LamazonLoads staff</span><?php endif; ?></td>
      <td><a href="<?= e(url('doc.php?id=' . (int) $d['id'])) ?>" target="_blank" rel="noopener"><?= e($d['original_name']) ?></a></td><td><?= e(fmt_date($d['created_at'])) ?></td>
      <td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('doc.php?id=' . (int) $d['id'] . '&download=1')) ?>"><?= icon('download') ?> Download</a>
        <form method="post" action="<?= e(url($self)) ?>" class="inline-form" data-confirm="Remove this document from their account?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_doc"><input type="hidden" name="doc" value="<?= (int) $d['id'] ?>"><button class="btn btn-danger btn-sm" type="submit" aria-label="Remove <?= e(DOC_KINDS[$d['kind']] ?? 'document') ?>"><?= icon('trash') ?></button></form></div></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="card pad">
  <h3 class="mt-0">Applications</h3>
  <?php if (!$apps): ?><p class="muted mb-0">No applications yet.</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Opening</th><th>Message</th><th>Status &amp; note</th></tr></thead><tbody><?php foreach ($apps as $a): ?>
    <tr><td><?= e(applicant_label($a)) ?><br><span class="muted"><?= e(fmt_date($a['created_at'])) ?></span>
      <?php if ($a['resume_doc_id']): ?><br><a href="<?= e(url('doc.php?id=' . (int) $a['resume_doc_id'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?> Resume</a><?php endif; ?>
      <?php if ($a['auto_note'] !== ''): ?><br><span class="hint">⚡ <?= e($a['auto_note']) ?></span><?php endif; ?></td><td style="max-width:320px"><?= nl2br(e($a['message'] ?? '')) ?></td>
      <td><form method="post" action="<?= e(url('admin/applications.php')) ?>" style="display:grid;gap:6px;min-width:200px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
        <select name="status" aria-label="Status"><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <input type="text" name="admin_note" maxlength="2000" placeholder="Staff note (private)" value="<?= e($a['admin_note'] ?? '') ?>" aria-label="Staff note">
        <button class="btn btn-ghost btn-sm" type="submit">Save</button></form>
        <form method="post" action="<?= e(url('admin/applications.php')) ?>" class="inline-form app-del-row" data-confirm="Delete this application? This can’t be undone. Their account and documents stay.">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
          <button class="link-btn" type="submit"><?= icon('trash') ?> Delete application</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="card pad">
  <h3 class="mt-0">Account actions</h3>
  <div class="row-actions">
    <?php if (!is_verified($u)): ?>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><button class="btn btn-primary btn-sm" type="submit">Mark email as confirmed</button></form>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-ghost btn-sm" type="submit">Resend confirmation email</button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url($self)) ?>" class="inline-form" data-confirm="Create a new temporary password for this member?"><?= csrf_field() ?><input type="hidden" name="action" value="password"><button class="btn btn-ghost btn-sm" type="submit">Reset password</button></form>
    <?php if ($id !== (int) $me['id']): ?>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin"><input type="hidden" name="make" value="<?= $u['is_admin'] ? '' : '1' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_admin'] ? 'Remove staff access' : 'Make staff (admin)' ?></button></form>
      <form method="post" action="<?= e(url($self)) ?>" class="inline-form del-member" data-confirm="Delete this member, their documents and applications? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="btn btn-danger btn-sm" type="submit">Delete member</button>
        <label class="check-inline"><input type="checkbox" name="notify" value="1" checked> Email them that their account was closed</label></form>
    <?php endif; ?>
  </div>
</div>

<div class="modal<?= $editErrors ? ' is-open' : '' ?>" id="edit" data-modal data-modal-param="edit" role="dialog" aria-modal="true" aria-labelledby="edit-title"<?= $editErrors ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>
  <div class="modal-panel modal-wide">
    <header class="modal-head">
      <div><span class="eyebrow">Edit information</span><h2 id="edit-title"><?= e($u['name']) ?></h2></div>
      <a class="modal-x" href="#" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <?php if ($editErrors): ?><ul class="errors"><?php foreach ($editErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url($self)) ?>" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="details">
        <h3 class="mt-0">Contact</h3>
        <div class="form-grid">
          <div><label for="ed-name">Full name</label><input id="ed-name" name="name" type="text" maxlength="100" value="<?= e($edit['name']) ?>"></div>
          <div><label>Email</label><input type="email" value="<?= e($u['email']) ?>" disabled></div>
          <div><label for="ed-phone">Phone</label><input id="ed-phone" name="phone" type="tel" maxlength="25" placeholder="(555) 123-4567" value="<?= e($edit['phone']) ?>"></div>
          <div><label for="account_type">I am a…</label><?= select_html('account_type', ACCOUNT_TYPES, (string) $edit['account_type']) ?></div>
        </div>
        <h3 class="mt">Equipment &amp; area</h3>
        <div class="form-grid">
          <div><label for="equipment">Equipment</label><?= select_html('equipment', EQUIPMENT, (string) $edit['equipment']) ?></div>
          <div><label for="vehicle">Year, make &amp; model</label><input id="vehicle" name="vehicle" type="text" maxlength="120" placeholder="2021 Ford Transit 250 High Roof" value="<?= e($edit['vehicle']) ?>"></div>
          <div><label for="home_zip">Home ZIP code</label><input id="home_zip" name="home_zip" type="text" inputmode="numeric" maxlength="10" value="<?= e($edit['home_zip']) ?>"></div>
          <div><label for="service_radius">How far they'll run</label><input id="service_radius" name="service_radius" type="text" maxlength="40" placeholder="Local, 300 mi, OTR…" value="<?= e($edit['service_radius']) ?>"></div>
          <div><label for="availability">Availability</label><?= select_html('availability', AVAILABILITY, (string) $edit['availability']) ?></div>
          <div><label for="years_experience">Years of experience</label><input id="years_experience" name="years_experience" type="text" maxlength="10" value="<?= e($edit['years_experience']) ?>"></div>
        </div>
        <h3 class="mt">Business &amp; insurance</h3>
        <div class="form-grid">
          <div class="full"><label for="company_name">Company name</label><input id="company_name" name="company_name" type="text" maxlength="120" value="<?= e($edit['company_name']) ?>"></div>
          <div><label for="mc_number">MC number</label><input id="mc_number" name="mc_number" type="text" maxlength="20" value="<?= e($edit['mc_number']) ?>"></div>
          <div><label for="dot_number">USDOT number</label><input id="dot_number" name="dot_number" type="text" inputmode="numeric" maxlength="20" value="<?= e($edit['dot_number']) ?>"></div>
          <div><label for="insurance_provider">Insurance company</label><input id="insurance_provider" name="insurance_provider" type="text" maxlength="120" value="<?= e($edit['insurance_provider']) ?>"></div>
          <div><label for="insurance_expires">Insurance expires</label><input id="insurance_expires" name="insurance_expires" type="date" min="2000-01-01" max="2099-12-31" value="<?= e((string) ($edit['insurance_expires'] ?? '')) ?>"></div>
          <div class="full"><label for="about">Notes for dispatch</label><textarea id="about" name="about" maxlength="2000" placeholder="Preferred lanes, home time, hazmat / TWIC, liftgate, pallet jack…"><?= e((string) ($edit['about'] ?? '')) ?></textarea></div>
        </div>
        <button class="btn btn-accent btn-block mt" type="submit">Save information</button>
      </form>
    </div>
  </div>
</div>
<?php dash_close(); page_footer();
