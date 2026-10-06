<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $status = post('status', 20);
    if (isset(APP_STATUSES[$status])) {
        $appId = (int) ($_POST['id'] ?? 0);
        db_run('UPDATE applications SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?', [$status, post('admin_note', 2000), $appId]);
        $closed = auto_close_job((int) db_val('SELECT job_id FROM applications WHERE id = ?', [$appId]));
        flash('success', 'Application updated.' . ($closed ? ' You have approved enough people, so the job post was closed automatically.' : ''));
    }
    $back = safe_next(post('back', 300));
    redirect($back === 'account.php' ? 'admin/applications.php' : $back);
}

$status = (string) ($_GET['status'] ?? '');
$job = (string) ($_GET['job'] ?? '');
$sent = (string) ($_GET['sent'] ?? '');
$city = (string) ($_GET['city'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$where = [];
$args = [];
if (isset(APP_STATUSES[$status])) { $where[] = 'a.status = ?'; $args[] = $status; }
if ($job !== '' && ctype_digit($job)) { $where[] = 'a.job_id = ?'; $args[] = (int) $job; }
if (isset(ONBOARDING_EMAILS[$sent])) { $where[] = 'a.email_sent = ?'; $args[] = $sent; } elseif ($sent === 'none') { $where[] = "a.email_sent = ''"; }
if ($city !== '') { $where[] = 'a.walmart = 1 AND a.walmart_city = ?'; $args[] = $city; }
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ? OR a.phone LIKE ? OR a.location LIKE ? OR p.home_zip LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "$q%");
}
$apps = db_all('SELECT a.*, u.name, u.email, u.phone AS account_phone, j.title, p.equipment, p.home_zip
    FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN jobs j ON j.id = a.job_id LEFT JOIN driver_profiles p ON p.user_id = a.user_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.created_at DESC LIMIT 300', $args);
$jobs = db_all('SELECT id, title FROM jobs ORDER BY title');
$cityList = array_column(db_all("SELECT DISTINCT walmart_city FROM applications WHERE walmart = 1 AND walmart_city <> '' ORDER BY walmart_city"), 'walmart_city');
$self = 'admin/applications.php' . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . $_SERVER['QUERY_STRING'] : '');

page_header('Applications');
admin_open('applications');
?>
<h1>Applications</h1>
<form method="get" action="<?= e(url('admin/applications.php')) ?>" class="card pad app-filters">
  <div class="af-q"><label for="q">Search name, email, phone or location</label><input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">Any</option><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label for="sent">Email sent</label><select id="sent" name="sent"><option value="">Any</option><?php foreach (ONBOARDING_EMAILS as $k => $l): ?><option value="<?= e($k) ?>"<?= $sent === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?><option value="none"<?= $sent === 'none' ? ' selected' : '' ?>>None</option></select></div>
  <div><label for="city">Walmart city</label><select id="city" name="city"><option value="">Any</option><?php foreach ($cityList as $c): ?><option value="<?= e($c) ?>"<?= $city === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
  <div><label for="job">Opening</label><select id="job" name="job"><option value="">Any</option><option value="0"<?= $job === '0' ? ' selected' : '' ?>>Driver network (general)</option><?php foreach ($jobs as $j): ?><option value="<?= (int) $j['id'] ?>"<?= $job === (string) $j['id'] ? ' selected' : '' ?>><?= e($j['title']) ?></option><?php endforeach; ?></select></div>
  <div><button class="btn btn-primary" type="submit">Filter</button></div>
</form>
<p class="muted"><?= count($apps) ?> application<?= count($apps) === 1 ? '' : 's' ?><?= $where ? ' match' : '' ?>.</p>
<?php if (!$apps): ?><div class="card empty">No applications match.</div><?php endif; ?>
<?php foreach ($apps as $a):
    $name = trim($a['first_name'] . ' ' . $a['last_name']) ?: $a['name'];
    $phone = $a['phone'] !== '' ? $a['phone'] : $a['account_phone'];
    $vehicles = vehicles_label($a) ?: (EQUIPMENT[$a['equipment'] ?? ''] ?? '');
    $isNew = $a['vehicles'] !== ''; ?>
  <article class="card app-card<?= $a['status'] === 'new' ? ' is-new' : '' ?>">
    <header class="app-head">
      <div>
        <h3><a href="<?= e(url('admin/driver.php?id=' . (int) $a['user_id'])) ?>"><?= e($name) ?></a></h3>
        <small class="muted"><?= e(applicant_label($a)) ?> · Applied <?= e(fmt_date($a['created_at'], 'M j, Y g:i a')) ?></small>
      </div>
      <div class="app-badges">
        <?php if ($a['email_sent'] === 'dispatch'): ?><span class="mail-badge dispatch"><?= icon('mail') ?>Dispatch email sent</span>
        <?php elseif ($a['email_sent'] === 'walmart'): ?><span class="mail-badge walmart"><?= icon('mail') ?>Walmart email sent · <?= e($a['walmart_city']) ?></span>
        <?php else: ?><span class="mail-badge none"><?= icon('mail') ?>No onboarding email</span><?php endif; ?>
        <?= status_badge($a['status']) ?>
      </div>
    </header>
    <dl class="app-grid">
      <div><dt>Phone</dt><dd><?= $phone !== '' ? '<a href="' . e(tel_href($phone)) . '">' . e($phone) . '</a>' : '—' ?></dd></div>
      <div><dt>Email</dt><dd><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd></div>
      <div><dt>Location</dt><dd><?= e($a['location'] !== '' ? $a['location'] : ($a['home_zip'] ? 'ZIP ' . $a['home_zip'] : '—')) ?></dd></div>
      <div><dt>Vehicle</dt><dd><?= e($vehicles !== '' ? $vehicles : '—') ?></dd></div>
      <div><dt>Vehicle is</dt><dd><?= e(ownership_label($a) ?: '—') ?></dd></div>
      <div><dt>Walmart daily route</dt><dd><?= (int) $a['walmart'] ? '<b>' . e($a['walmart_city']) . '</b>' : ($isNew ? 'Not interested' : '—') ?></dd></div>
      <?php if ((int) $a['walmart']): ?><div><dt>Rate they asked for</dt><dd><?= e($a['rate_requested'] !== '' ? $a['rate_requested'] . ' / day' : 'Not given') ?></dd></div><?php endif; ?>
      <?php if ($a['email_sent_at']): ?><div><dt>Email sent</dt><dd><?= e(fmt_date($a['email_sent_at'], 'M j, g:i a')) ?></dd></div><?php endif; ?>
      <?php if ($a['resume_doc_id']): ?><div><dt>Resume</dt><dd><a href="<?= e(url('doc.php?id=' . (int) $a['resume_doc_id'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?> Open</a></dd></div><?php endif; ?>
    </dl>
    <?php if (trim((string) $a['message']) !== ''): ?><p class="app-msg">“<?= e($a['message']) ?>”</p><?php endif; ?>
    <?php if ($a['auto_note'] !== ''): ?><p class="hint">⚡ <?= e($a['auto_note']) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('admin/applications.php')) ?>" class="app-status">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
      <select name="status" aria-label="Status"><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <input type="text" name="admin_note" maxlength="2000" placeholder="Staff note (private)" value="<?= e($a['admin_note'] ?? '') ?>" aria-label="Staff note">
      <button class="btn btn-ghost btn-sm" type="submit">Save</button>
    </form>
  </article>
<?php endforeach; ?>
<?php dash_close(); page_footer();
