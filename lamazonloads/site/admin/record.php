<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// A record: a driver your team knows who doesn't have an account yet (Drivers & members → Add a record).
// Nothing here emails them; "Send onboarding email" opens that page with their details filled in.
$me = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$r = db_one('SELECT r.*, s.name AS added_name FROM member_records r LEFT JOIN users s ON s.id = r.added_by WHERE r.id = ?', [$id]);
if (!$r) {
    flash('info', 'That record wasn’t found. It may have been deleted.');
    redirect('admin/drivers.php');
}
$name = trim($r['first_name'] . ' ' . $r['last_name']);
if ($r['merged_user_id']) { // they signed up: the record is part of their account now
    flash('info', $name . ' signed up, so their record is now part of their account.');
    redirect('admin/driver.php?id=' . (int) $r['merged_user_id']);
}
$self = 'admin/record.php?id=' . $id;
$edit = $r;
$editErrors = [];
$editDup = null;

if (is_post()) {
    csrf_check();
    $action = as_str($_POST['action'] ?? '');
    if ($action === 'delete') {
        require_full_admin_action($self); // moderators don't delete
        db_run('DELETE FROM member_records WHERE id = ?', [$id]);
        flash('success', $name . '’s record was deleted.');
        redirect('admin/drivers.php');
    }
    if ($action === 'save') {
        [$editErrors, $editDup] = record_from_post($edit, $id);
        if (!$editErrors && !$editDup) {
            db_run('UPDATE member_records SET first_name = ?, last_name = ?, email = ?, phone = ?, city = ?, account_type = ?, vehicle = ?, vehicle_other = ?,
                onboarded = ?, notes = ?, updated_at = NOW() WHERE id = ?', [$edit['first_name'], $edit['last_name'], $edit['email'], $edit['phone'], $edit['city'],
                $edit['account_type'], $edit['vehicle'], $edit['vehicle_other'], $edit['onboarded'], $edit['notes'], $id]);
            flash('success', 'Record saved.');
            redirect($self);
        }
    }
}

$first = first_name($name, '') ?: trim((string) $r['first_name']) ?: $name; // "J.R." stays "J.R.", never "there"
$mails = db_all('SELECT m.*, s.name AS sender FROM onboarding_emails m LEFT JOIN users s ON s.id = m.sent_by WHERE m.email = ? ORDER BY m.id DESC LIMIT 10', [$r['email']]);
$vl = user_vehicles_label($r);
$type = explode(' (', ACCOUNT_TYPES[$r['account_type']] ?? 'Member')[0];

page_header($name);
admin_open('drivers', false);
?>
<a class="back-link" href="<?= e(url('admin/drivers.php')) ?>"><?= icon('chev-left') ?> All members</a>
<?= admin_head($name,
    '<span class="badge badge-noacct">No account yet</span> · ' . e($type) . ' · record added ' . e(fmt_date((string) $r['created_at'])) . ($r['added_name'] ? ' by ' . e(first_name((string) $r['added_name'])) : ''),
    '<a class="btn btn-ghost" href="mailto:' . e($r['email']) . '">' . icon('mail') . ' Email</a>'
    . ($r['phone'] !== '' ? '<a class="btn btn-ghost" href="' . e(tel_href((string) $r['phone'])) . '">' . icon('phone') . ' Call</a>' : '')
    . '<a class="btn btn-ghost" href="#edit" data-modal-open="edit">' . icon('edit') . ' Edit record</a>'
    . '<a class="btn btn-primary" href="' . e(url('admin/send-onboarding.php?email=' . rawurlencode((string) $r['email']) . '&first=' . rawurlencode($first))) . '">' . icon('send') . ' Send onboarding email</a>',
    'Record') ?>

<div class="rec-how"><?= icon('user') ?>
  <p>When <?= e($first) ?> signs up with <b><?= e($r['email']) ?></b> and confirms it, this record joins their account. What they type at sign-up is kept, and this record adds the rest<?= $r['onboarded'] !== '' ? ', so they show as onboarded right away and skip the uploads' : '' ?>.</p>
</div>

<div class="grid grid-2">
  <div class="card pad">
    <h3 class="mt-0">Contact</h3>
    <p class="mb-0"><?= icon('mail') ?> <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
      <br><?= icon('phone') ?> <?= $r['phone'] !== '' ? '<a href="' . e(tel_href((string) $r['phone'])) . '">' . e($r['phone']) . '</a>' : '<span class="muted">No phone yet</span>' ?>
      <br><?= icon('pin') ?> <?= $r['city'] !== '' ? e($r['city']) : '<span class="muted">No city yet</span>' ?></p>
  </div>
  <div class="card pad">
    <h3 class="mt-0">Driving</h3>
    <p class="mb-0"><?= icon('user') ?> <?= e($type) ?>
      <br><?= icon('truck') ?> <?= $vl !== '' ? e($vl) : '<span class="muted">No vehicle given</span>' ?>
      <br><?= icon('check') ?> <?= e(record_onboarded_label((string) $r['onboarded'])) ?></p>
  </div>
</div>

<?php if (trim((string) $r['notes']) !== ''): ?>
<div class="card pad">
  <h3 class="mt-0">Notes <span class="opt">(only your team sees them)</span></h3>
  <p class="mb-0 rec-notes"><?= nl2br(e((string) $r['notes'])) ?></p>
</div>
<?php endif; ?>

<div class="card pad">
  <h3 class="mt-0">Onboarding emails</h3>
  <?php if (!$mails): ?>
    <p class="muted mb-0">None sent yet. Send the Dispatch or Walmart email so <?= e($first) ?> can sign up and upload their documents.</p>
  <?php else: ?>
    <ul class="rec-mails">
      <?php foreach ($mails as $m): ?>
        <li><span class="mail-dot <?= e($m['type']) ?>"><?= e($m['type'] === 'walmart' ? 'Walmart' : 'Dispatch') ?></span>
          <span><?= e(fmt_date((string) $m['sent_at'], 'M j, Y · g:i a')) ?><?= $m['city'] !== '' ? ' · ' . e((string) $m['city']) : '' ?><?= $m['sender'] ? ' · by ' . e(first_name((string) $m['sender'])) : '' ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php if (is_full_admin()): ?>
<div class="card pad">
  <h3 class="mt-0">Record actions</h3>
  <form method="post" action="<?= e(url($self)) ?>" class="inline-form" data-confirm="Delete <?= e($name) ?>’s record? This can’t be undone." data-confirm-ok="Delete record" data-confirm-danger>
    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete record</button></form>
</div>
<?php endif; ?>

<div class="modal<?= $editErrors || $editDup ? ' is-open' : '' ?>" id="edit" data-modal data-modal-param="edit" role="dialog" aria-modal="true" aria-labelledby="edit-title"<?= $editErrors || $editDup ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="#" data-modal-close aria-label="Close"></a>
  <div class="modal-panel">
    <header class="modal-head">
      <div><span class="eyebrow">Edit record</span><h2 id="edit-title"><?= e($name) ?></h2></div>
      <a class="modal-x" href="#" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
    </header>
    <div class="modal-body">
      <?php if ($editDup): ?><p class="rec-dup"><?= icon('info') ?><span><?= record_dup_html($editDup) ?></span></p><?php endif; ?>
      <?php if ($editErrors): ?><ul class="errors"><?php foreach ($editErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url($self)) ?>" class="form-grid" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?= record_fields_html($edit, 'er') ?>
        <div class="full"><button class="btn btn-accent btn-block" type="submit">Save record</button></div>
      </form>
    </div>
  </div>
</div>
<?php dash_close(); page_footer();
