<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$fields = ['title' => 150, 'category' => 30, 'location' => 120, 'equipment' => 120, 'pay' => 120, 'schedule' => 120, 'summary' => 300, 'description' => 8000, 'requirements' => 4000, 'status' => 10];
$statuses = ['open' => 'Open (visible)', 'draft' => 'Draft (hidden)', 'closed' => 'Closed (hidden)'];
$errors = [];
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$job = $editId ? db_one('SELECT * FROM jobs WHERE id = ?', [$editId]) : null;
if ($editId && !$job) {
    redirect('admin/jobs.php');
}
$form = $job ?? array_merge(array_fill_keys(array_keys($fields), ''), ['category' => 'owner_operator', 'status' => 'open']);

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        db_run('DELETE FROM applications WHERE job_id = ? AND job_id <> 0', [$id]);
        db_run('DELETE FROM jobs WHERE id = ?', [$id]);
        flash('success', 'Job post deleted.');
        redirect('admin/jobs.php');
    }
    if ($action === 'status') {
        $s = post('status', 10);
        if (isset($statuses[$s])) {
            db_run('UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ?', [$s, $id]);
            flash('success', 'Job post is now ' . strtolower(strtok($statuses[$s], ' ')) . '.');
        }
        redirect('admin/jobs.php');
    }
    foreach ($fields as $k => $max) {
        $form[$k] = post($k, $max);
    }
    if ($form['title'] === '') $errors[] = 'Please enter a title.';
    if (!isset(JOB_CATEGORIES[$form['category']])) $errors[] = 'Please choose a category.';
    if (!isset($statuses[$form['status']])) $form['status'] = 'draft';
    if ($form['summary'] === '') $errors[] = 'Please write a one-line summary (shown on the job cards).';
    if (!$errors) {
        $vals = array_values(array_intersect_key(array_merge($fields, $form), $fields));
        if ($id) {
            $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
            db_run("UPDATE jobs SET $set, updated_at = NOW() WHERE id = ?", array_merge($vals, [$id]));
            flash('success', 'Job post saved.');
        } else {
            db_run('INSERT INTO jobs (' . implode(', ', array_keys($fields)) . ', created_at, updated_at) VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ', NOW(), NOW())', $vals);
            flash('success', 'Job post created.');
        }
        redirect('admin/jobs.php');
    }
    $editId = $id ?: null;
}

$showForm = $editId !== null || isset($_GET['new']) || $errors;
$jobs = db_all('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) apps FROM jobs j ORDER BY FIELD(j.status, \'open\', \'draft\', \'closed\'), j.created_at DESC');

page_header('Job posts');
admin_open('jobs');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <h1 class="mb-0">Job posts</h1>
  <?php if (!$showForm): ?><a class="btn btn-accent" href="<?= e(url('admin/jobs.php?new=1')) ?>">+ New job post</a><?php endif; ?>
</div>
<p class="muted">Open posts appear on the Careers page and the home page. Members apply with one click.</p>

<?php if ($showForm): ?>
<form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="card form-card" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($editId ?? 0) ?>">
  <h3 class="mt-0"><?= $editId ? 'Edit job post' : 'New job post' ?></h3>
  <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="form-grid">
    <div class="full"><label for="title">Title</label><input id="title" name="title" type="text" maxlength="150" value="<?= e($form['title']) ?>" placeholder="Box Truck Driver: Local Daily Route"></div>
    <div><label for="category">Category</label><select id="category" name="category"><?php foreach (JOB_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= $form['category'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label for="status">Visibility</label><select id="status" name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $form['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label for="location">Location</label><input id="location" name="location" type="text" maxlength="120" value="<?= e($form['location']) ?>" placeholder="Atlanta, GA / Remote / Nationwide"></div>
    <div><label for="equipment">Equipment</label><input id="equipment" name="equipment" type="text" maxlength="120" value="<?= e($form['equipment']) ?>" placeholder="26 ft Box Truck"></div>
    <div><label for="pay">Pay</label><input id="pay" name="pay" type="text" maxlength="120" value="<?= e($form['pay']) ?>" placeholder="$ per route / per load"></div>
    <div><label for="schedule">Schedule</label><input id="schedule" name="schedule" type="text" maxlength="120" value="<?= e($form['schedule']) ?>" placeholder="Mon–Fri, 6am start"></div>
    <div class="full"><label for="summary">One-line summary</label><input id="summary" name="summary" type="text" maxlength="300" value="<?= e($form['summary']) ?>"></div>
    <div class="full"><label for="description">Description</label><textarea id="description" name="description" maxlength="8000" rows="7"><?= e($form['description']) ?></textarea><p class="hint">Leave an empty line between paragraphs.</p></div>
    <div class="full"><label for="requirements">Requirements <span class="opt">(one per line)</span></label><textarea id="requirements" name="requirements" maxlength="4000" rows="5"><?= e($form['requirements']) ?></textarea></div>
  </div>
  <div class="row-actions mt"><button class="btn btn-accent" type="submit">Save job post</button><a class="btn btn-ghost" href="<?= e(url('admin/jobs.php')) ?>">Cancel</a></div>
</form>
<?php endif; ?>

<div class="card pad">
  <?php if (!$jobs): ?><div class="empty">No job posts yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Applicants</th><th></th></tr></thead>
    <tbody><?php foreach ($jobs as $j): ?>
      <tr>
        <td><b><?= e($j['title']) ?></b><br><span class="muted"><?= e($j['location']) ?></span></td>
        <td><?= e(JOB_CATEGORIES[$j['category']] ?? $j['category']) ?></td>
        <td><span class="badge badge-<?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span></td>
        <td><a href="<?= e(url('admin/applications.php?job=' . (int) $j['id'])) ?>"><?= (int) $j['apps'] ?></a></td>
        <td><div class="row-actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/jobs.php?edit=' . (int) $j['id'])) ?>">Edit</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('job.php?id=' . (int) $j['id'])) ?>">View</a>
          <form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
            <input type="hidden" name="status" value="<?= $j['status'] === 'open' ? 'closed' : 'open' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $j['status'] === 'open' ? 'Close' : 'Open' ?></button></form>
          <form method="post" action="<?= e(url('admin/jobs.php')) ?>" class="inline-form" data-confirm="Delete this job post and its applications?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php dash_close(); page_footer();
