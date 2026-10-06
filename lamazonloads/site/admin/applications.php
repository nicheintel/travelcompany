<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
if (is_post()) {
    csrf_check();
    $status = post('status', 20);
    if (in_array($_POST['action'] ?? '', ['delete', 'delete_many'], true)) {
        // Delete one application (from its pop-up) or the ticked ones in the list. The member's account,
        // profile and documents (including a resume) stay; they can apply to that opening again.
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [$_POST['id'] ?? 0])))));
        $gone = 0;
        foreach (array_chunk($ids, 200) as $chunk) {
            $gone += db_run('DELETE FROM applications WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
        }
        flash($gone ? 'success' : 'info', $gone ? ($gone === 1 ? 'Application deleted.' : $gone . ' applications deleted.') : 'Nothing was deleted.');
    } elseif (isset(APP_STATUSES[$status])) {
        $appId = (int) ($_POST['id'] ?? 0);
        db_run('UPDATE applications SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?', [$status, post('admin_note', 2000), $appId]);
        $closed = auto_close_job((int) db_val('SELECT job_id FROM applications WHERE id = ?', [$appId]));
        flash('success', 'Application updated.' . ($closed ? ' You have approved enough people, so the job post was closed automatically.' : ''));
    }
    $back = safe_next(post('back', 300));
    redirect($back === 'account.php' ? 'admin/applications.php' : $back);
}

const APPS_PER_PAGE = 50;
$f = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'sent' => (string) ($_GET['sent'] ?? ''),
    'city' => (string) ($_GET['city'] ?? ''),
    'job' => (string) ($_GET['job'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
/** This page's address with the current filters (plus/minus some values). */
$link = function (array $extra = []) use ($f, $page): string {
    $p = array_filter(array_merge($f, ['page' => $page > 1 ? $page : ''], $extra), fn ($v) => $v !== '' && $v !== null);
    return 'admin/applications.php' . ($p ? '?' . http_build_query($p) : '');
};
$appSql = 'SELECT a.*, u.name, u.email, u.phone AS account_phone, j.title, p.equipment, p.home_zip
    FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN jobs j ON j.id = a.job_id LEFT JOIN driver_profiles p ON p.user_id = a.user_id';

/** Full details of one application, shown in the pop-up. */
function app_detail_html(array $a, string $back): string
{
    $name = trim($a['first_name'] . ' ' . $a['last_name']) ?: $a['name'];
    $phone = $a['phone'] !== '' ? $a['phone'] : $a['account_phone'];
    $vehicles = vehicles_label($a) ?: (EQUIPMENT[$a['equipment'] ?? ''] ?? '');
    $isNew = $a['vehicles'] !== '';
    $mail = match ($a['email_sent']) {
        'dispatch' => '<span class="mail-badge dispatch">' . icon('mail') . 'Dispatch email sent</span>',
        'walmart' => '<span class="mail-badge walmart">' . icon('mail') . 'Walmart email sent · ' . e($a['walmart_city']) . '</span>',
        default => '<span class="mail-badge none">' . icon('mail') . 'No onboarding email</span>',
    };
    $rows = [
        ['Phone', $phone !== '' ? '<a href="' . e(tel_href($phone)) . '">' . e($phone) . '</a>' : '—'],
        ['Email', '<a href="mailto:' . e($a['email']) . '">' . e($a['email']) . '</a>'],
        ['Location', e($a['location'] !== '' ? $a['location'] : ($a['home_zip'] ? 'ZIP ' . $a['home_zip'] : '—'))],
        ['Vehicle', e($vehicles !== '' ? $vehicles : '—')],
        ['Vehicle is', e(ownership_label($a) ?: '—')],
        ['Walmart daily route', (int) $a['walmart'] ? '<b>' . e($a['walmart_city']) . '</b>' : ($isNew ? 'Not interested' : '—')],
    ];
    if ((int) $a['walmart']) {
        $rows[] = ['Rate they asked for', e($a['rate_requested'] !== '' ? $a['rate_requested'] . ' / day' : 'Not given')];
    }
    if ($a['email_sent_at']) {
        $rows[] = ['Email sent', e(fmt_date($a['email_sent_at'], 'M j, g:i a'))];
    }
    if ($a['resume_doc_id'] && ($resume = db_one('SELECT * FROM documents WHERE id = ?', [$a['resume_doc_id']]))) {
        $rows[] = ['Resume', doc_link($resume, icon('file') . ' View', '', 'Resume')];
    }
    $html = '<header class="modal-head"><div><span class="eyebrow">' . e(applicant_label($a)) . '</span><h2 id="app-title">' . e($name) . '</h2>'
        . '<p class="modal-sub">Applied ' . e(fmt_date($a['created_at'], 'M j, Y g:i a')) . '</p></div>'
        . '<a class="modal-x" href="' . e(url($back)) . '" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a></header>'
        . '<div class="modal-body"><div class="app-badges mb">' . $mail . status_badge($a['status']) . '</div><dl class="app-grid">';
    foreach ($rows as [$k, $v]) {
        $html .= '<div><dt>' . e($k) . '</dt><dd>' . $v . '</dd></div>';
    }
    $html .= '</dl>';
    if (trim((string) $a['message']) !== '') {
        $html .= '<p class="app-msg">“' . e($a['message']) . '”</p>';
    }
    if ($a['auto_note'] !== '') {
        $html .= '<p class="hint">⚡ ' . e($a['auto_note']) . '</p>';
    }
    $opts = '';
    foreach (APP_STATUSES as $k => $l) {
        $opts .= '<option value="' . e($k) . '"' . ($a['status'] === $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    $html .= '<form method="post" action="' . e(url('admin/applications.php')) . '" class="app-status">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<select name="status" aria-label="Status">' . $opts . '</select>'
        . '<input type="text" name="admin_note" maxlength="2000" placeholder="Staff note (private)" value="' . e((string) ($a['admin_note'] ?? '')) . '" aria-label="Staff note">'
        . '<button class="btn btn-primary btn-sm" type="submit">Save</button></form>'
        . '<div class="app-links"><a href="' . e(url('admin/driver.php?id=' . (int) $a['user_id'])) . '">' . icon('user') . 'Full member profile &amp; documents</a>'
        . ($phone !== '' ? '<a href="' . e(tel_href($phone)) . '">' . icon('phone') . 'Call</a>' : '')
        . '<a href="mailto:' . e($a['email']) . '">' . icon('mail') . 'Email</a>'
        . '<form method="post" action="' . e(url('admin/applications.php')) . '" class="inline-form app-del" data-confirm="Delete this application? This can’t be undone. Their account and documents stay.">' . csrf_field()
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<button class="link-btn" type="submit">' . icon('trash') . 'Delete application</button></form></div></div>';
    return $html;
}

// One application (opened from the list)
$id = (int) ($_GET['id'] ?? 0);
$open = $id ? db_one($appSql . ' WHERE a.id = ?', [$id]) : null;
if (isset($_GET['partial'])) {
    if (!$open) {
        http_response_code(404);
        exit;
    }
    echo app_detail_html($open, $link());
    exit;
}

$where = [];
$args = [];
if (isset(APP_STATUSES[$f['status']])) { $where[] = 'a.status = ?'; $args[] = $f['status']; }
if ($f['job'] !== '' && ctype_digit($f['job'])) { $where[] = 'a.job_id = ?'; $args[] = (int) $f['job']; }
if (isset(ONBOARDING_EMAILS[$f['sent']])) { $where[] = 'a.email_sent = ?'; $args[] = $f['sent']; } elseif ($f['sent'] === 'none') { $where[] = "a.email_sent = ''"; }
if ($f['city'] !== '') { $where[] = 'a.walmart = 1 AND a.walmart_city = ?'; $args[] = $f['city']; }
if ($f['q'] !== '') {
    $q = $f['q'];
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ? OR a.phone LIKE ? OR a.location LIKE ? OR p.home_zip LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "$q%");
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) db_val('SELECT COUNT(*) FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN driver_profiles p ON p.user_id = a.user_id' . $whereSql, $args);
$pages = max(1, (int) ceil($total / APPS_PER_PAGE));
$page = min($page, $pages);
$apps = db_all($appSql . $whereSql . ' ORDER BY a.created_at DESC LIMIT ' . APPS_PER_PAGE . ' OFFSET ' . (($page - 1) * APPS_PER_PAGE), $args);
$jobs = db_all('SELECT id, title FROM jobs ORDER BY title');
$cityList = array_column(db_all("SELECT DISTINCT walmart_city FROM applications WHERE walmart = 1 AND walmart_city <> '' ORDER BY walmart_city"), 'walmart_city');
$newCount = (int) db_val("SELECT COUNT(*) FROM applications WHERE status = 'new'");

page_header('Applications');
admin_open('applications');
?>
<h1>Applications</h1>
<form method="get" action="<?= e(url('admin/applications.php')) ?>" class="card pad app-filters">
  <div class="af-q"><label for="q">Search name, email, phone or location</label><input id="q" name="q" type="search" value="<?= e($f['q']) ?>"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">Any</option><?php foreach (APP_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label for="sent">Email sent</label><select id="sent" name="sent"><option value="">Any</option><?php foreach (ONBOARDING_EMAILS as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['sent'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?><option value="none"<?= $f['sent'] === 'none' ? ' selected' : '' ?>>None</option></select></div>
  <div><label for="city">Walmart city</label><select id="city" name="city"><option value="">Any</option><?php foreach ($cityList as $c): ?><option value="<?= e($c) ?>"<?= $f['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
  <div><label for="job">Opening</label><select id="job" name="job"><option value="">Any</option><option value="0"<?= $f['job'] === '0' ? ' selected' : '' ?>>Driver network (general)</option><?php foreach ($jobs as $j): ?><option value="<?= (int) $j['id'] ?>"<?= $f['job'] === (string) $j['id'] ? ' selected' : '' ?>><?= e($j['title']) ?></option><?php endforeach; ?></select></div>
  <div><button class="btn btn-primary" type="submit">Filter</button></div>
</form>
<p class="muted app-count"><b><?= $total ?></b> application<?= $total === 1 ? '' : 's' ?><?= $where ? ' match' : '' ?><?= $newCount ? ' · <b>' . $newCount . '</b> new' : '' ?><?= $where ? ' · <a href="' . e(url('admin/applications.php')) . '">Clear filters</a>' : '' ?></p>

<?php if (!$apps): ?><div class="card empty">No applications match.</div><?php else: ?>
<form method="post" action="<?= e(url('admin/applications.php')) ?>" id="bulk" data-bulk data-confirm="Delete the selected applications? This can’t be undone.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_many"><input type="hidden" name="back" value="<?= e($link()) ?>">
</form>
<div class="card app-list" role="list">
  <div class="app-item app-item-head"><label class="ar-check"><input type="checkbox" data-bulk-all aria-label="Select all on this page"><span class="ar-all">Select all</span></label>
  <div class="app-row app-row-head" aria-hidden="true"><span>Applicant</span><span>Opening</span><span>Vehicle</span><span>Date</span><span>Email sent</span><span>Status</span><span></span></div></div>
  <?php foreach ($apps as $a):
      $name = trim($a['first_name'] . ' ' . $a['last_name']) ?: $a['name'];
      $veh = vehicles_label($a) ?: (EQUIPMENT[$a['equipment'] ?? ''] ?? '—'); ?>
  <div class="app-item" role="listitem">
    <label class="ar-check"><input type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>" form="bulk" data-bulk-box aria-label="Select <?= e($name) ?>"></label>
    <a class="app-row<?= $a['status'] === 'new' ? ' is-new' : '' ?>" href="<?= e(url($link(['id' => $a['id']]))) ?>" data-app-open>
      <span class="ar-name"><b><?= e($name) ?></b><small><?= e($a['email']) ?></small></span>
      <span class="ar-job"><?= e(applicant_label($a)) ?></span>
      <span class="ar-veh"><?= e($veh) ?></span>
      <span class="ar-date"><?= e(fmt_date($a['created_at'], 'M j')) ?></span>
      <span class="ar-mail"><?= match ($a['email_sent']) { 'dispatch' => '<span class="mail-dot dispatch">Dispatch</span>', 'walmart' => '<span class="mail-dot walmart">Walmart · ' . e($a['walmart_city']) . '</span>', default => '<span class="mail-dot none">None</span>' } ?></span>
      <span class="ar-status"><?= status_badge($a['status']) ?></span>
      <span class="ar-go"><?= icon('arrow') ?></span>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<div class="bulk-bar" data-bulk-bar hidden>
  <span><b data-bulk-count>0</b> selected</span>
  <button class="btn btn-ghost btn-sm" type="button" data-bulk-clear>Clear</button>
  <button class="btn btn-danger btn-sm" type="submit" form="bulk"><?= icon('trash') ?> Delete selected</button>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($link(['page' => $page - 1 > 1 ? $page - 1 : '']))) ?>">&larr; Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($link(['page' => $page + 1]))) ?>">Older &rarr;</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>

<div class="modal app-modal<?= $open ? ' is-open' : '' ?>" id="app" data-modal data-modal-param="id" role="dialog" aria-modal="true" aria-labelledby="app-title"<?= $open ? '' : ' aria-hidden="true"' ?>>
  <a class="modal-backdrop" href="<?= e(url($link())) ?>" data-modal-close aria-label="Close"></a>
  <div class="modal-panel" data-modal-content><?= $open ? app_detail_html($open, $link()) : '' ?></div>
</div>
<?php dash_close(); page_footer();
