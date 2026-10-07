<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Agreements drivers sign online after approval. Signed copies keep the exact version each driver signed.
$me = require_admin();
$track = isset(ONB_TRACKS[$_GET['t'] ?? '']) ? (string) $_GET['t'] : (isset(ONB_TRACKS[$_POST['track'] ?? '']) ? (string) $_POST['track'] : 'dispatch');
$c = contract_get($track);
$errors = [];
$form = $c;

if (is_post()) {
    csrf_check();
    $form['title'] = post('title', 190);
    $form['body'] = str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''));
    $form['required'] = !empty($_POST['required']) ? 1 : 0;
    $form['ask_emergency'] = !empty($_POST['ask_emergency']) ? 1 : 0;
    if ($form['title'] === '') $errors[] = 'Please give the agreement a title.';
    if (mb_strlen($form['body']) > 60000) $errors[] = 'The agreement is too long.';
    if ($form['required'] && trim($form['body']) === '') $errors[] = 'Add the agreement text before turning on signing.';
    if (!$errors) {
        $changed = $form['title'] !== $c['title'] || trim($form['body']) !== trim((string) $c['body']);
        db_run('UPDATE contracts SET title = ?, body = ?, required = ?, ask_emergency = ?, version = version + ?, updated_by = ?, updated_at = NOW() WHERE track = ?',
            [$form['title'], trim($form['body']), $form['required'], $form['ask_emergency'], $changed ? 1 : 0, $me['id'], $track]);
        flash('success', ONB_TRACKS[$track] . ' agreement saved' . ($changed ? ' (version ' . ((int) $c['version'] + 1) . '). Drivers who already signed keep the copy they signed.' : '.'));
        redirect('admin/contracts.php?t=' . $track);
    }
}
$editor = $c['updated_by'] ? (string) db_val('SELECT name FROM users WHERE id = ?', [$c['updated_by']]) : '';
$signed = (int) db_val('SELECT COUNT(*) FROM onboarding WHERE track = ? AND signed_at IS NOT NULL', [$track]);
$live = (int) $form['required'] === 1 && trim((string) $form['body']) !== '';

page_header('Contracts');
admin_open('contracts');
echo admin_head('Contracts', 'The agreement drivers sign online after you approve their documents. Edit it any time: drivers who already signed keep the exact copy they signed.');
?>
<nav class="filters" aria-label="Program">
  <?php foreach (ONB_TRACKS as $k => $l): $cc = contract_get($k); $on = (int) $cc['required'] === 1 && trim((string) $cc['body']) !== ''; ?>
    <a href="<?= e(url('admin/contracts.php?t=' . $k)) ?>"<?= $track === $k ? ' class="on"' : '' ?>><?= e($l) ?> · <?= $on ? 'Signing on' : 'No contract' ?></a>
  <?php endforeach; ?>
</nav>

<div class="ct-status card<?= $live ? ' is-live' : '' ?>">
  <span class="ct-ico"><?= icon($live ? 'check' : 'clock') ?></span>
  <div><b><?= $live ? 'Approved ' . e(ONB_TRACKS[$track]) . ' drivers sign this agreement, then get the Telegram link.' : 'No agreement to sign: approved ' . e(ONB_TRACKS[$track]) . ' drivers go straight to the Telegram link.' ?></b>
    <small>Version <?= (int) $c['version'] ?><?= $editor !== '' ? ' · last edited by ' . e($editor) : '' ?> · <?= e(fmt_date((string) $c['updated_at'], 'M j, Y g:i a')) ?> · signed by <?= $signed ?> driver<?= $signed === 1 ? '' : 's' ?></small></div>
</div>

<div class="ct-layout">
  <form method="post" action="<?= e(url('admin/contracts.php?t=' . $track)) ?>" class="card panel ct-form" novalidate>
    <header class="panel-head"><h2><?= icon('edit') ?>Edit agreement</h2></header>
    <div class="panel-body">
      <?= csrf_field() ?><input type="hidden" name="track" value="<?= e($track) ?>">
      <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <label for="title">Title</label>
      <input id="title" name="title" type="text" maxlength="190" value="<?= e((string) $form['title']) ?>">
      <label for="body" class="mt">Agreement text</label>
      <textarea id="body" name="body" rows="26" class="ct-body" placeholder="Paste the agreement here."><?= e((string) $form['body']) ?></textarea>
      <label class="check mt"><input type="checkbox" name="required" value="1"<?= (int) $form['required'] ? ' checked' : '' ?>> Drivers must sign this agreement after I approve them</label>
      <label class="check"><input type="checkbox" name="ask_emergency" value="1"<?= (int) $form['ask_emergency'] ? ' checked' : '' ?>> Ask for emergency contact information when signing</label>
      <button class="btn btn-accent mt" type="submit">Save agreement</button>
    </div>
  </form>
  <aside class="ct-side">
    <section class="card panel">
      <header class="panel-head"><h2><?= icon('star') ?>How to format</h2></header>
      <div class="panel-body ct-help">
        <p><code># Payments</code> starts a heading</p>
        <p><code>• Item</code> or <code>- Item</code> makes a bullet</p>
        <p>An empty line starts a new paragraph</p>
        <p class="mt"><b>Filled in automatically</b></p>
        <p><code>{driver_name}</code> printed name they type</p>
        <p><code>{company_name}</code> company (or N/A)</p>
        <p><code>{day}</code> <code>{month}</code> <code>{year}</code> date signed, e.g. 7th · October · 2026</p>
        <p><code>{email}</code> <code>{phone}</code> from their account</p>
        <p class="mt muted">The signature, printed name, date and emergency contact are added under the agreement automatically.</p>
      </div>
    </section>
  </aside>
</div>

<?php if (trim((string) $c['body']) !== ''): ?>
<section class="card panel">
  <header class="panel-head"><h2><?= icon('eye') ?>Preview (as a driver sees it)</h2><small>Sample driver: Jordan Driver</small></header>
  <div class="contract-body ct-preview"><?= contract_html(contract_fill((string) $c['body'], ['name' => 'Jordan Driver', 'email' => 'jordan@example.com', 'phone' => '(555) 123-4567'], 'Jordan Logistics LLC')) ?></div>
</section>
<?php endif; ?>
<?php dash_close(); page_footer();
