<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$id = (int) (param('id') ?: post('id'));
$request = db_one('SELECT * FROM requests WHERE id = ?', [$id]);
if (!$request) {
    flash('That request doesn\'t exist anymore.', 'error');
    redirect(url('admin/requests.php'));
}
$self = url('admin/request.php', ['id' => $id]);

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $adminId = (int) $admin['id'];

    if ($action === 'status') {
        $new = post('status');
        $message = mb_substr(post('client_note'), 0, 2000);
        if (isset(STATUSES[$new]) && $new !== $request['status']) {
            db_run(
                'UPDATE requests SET status = ?, launched_at = IF(? = \'launched\' AND launched_at IS NULL, ?, launched_at), updated_at = ? WHERE id = ?',
                [$new, $new, now_utc(), now_utc(), $id],
            );
            add_event($id, $adminId, 'status', 'Status: ' . status_label($request['status']) . ' → ' . status_label($new));
        }
        if ($message !== '') {
            db_run('UPDATE requests SET client_note = ?, client_note_at = ?, updated_at = ? WHERE id = ?', [$message, now_utc(), now_utc(), $id]);
            add_event($id, $adminId, 'client', 'Update for the client: ' . $message);
        }
        flash($message !== '' ? 'Saved. Your client sees the update on their tracking page.' : 'Status updated.');
        redirect($self);
    }

    if ($action === 'quote') {
        $raw = str_replace([',', ' ', setting('currency')], '', post('quoted_amount'));
        if ($raw === '') {
            db_run('UPDATE requests SET quoted_amount = NULL, updated_at = ? WHERE id = ?', [now_utc(), $id]);
            add_event($id, $adminId, 'quote', 'Quote removed');
            flash('Quote removed.');
        } elseif (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 99999999) {
            flash('Enter the quote as a number, e.g. 12999.', 'error');
        } else {
            db_run('UPDATE requests SET quoted_amount = ?, updated_at = ? WHERE id = ?', [round((float) $raw, 2), now_utc(), $id]);
            add_event($id, $adminId, 'quote', 'Quote set to ' . money((float) $raw));
            flash('Quote saved.' . ($request['status'] === 'new' || $request['status'] === 'contacted' ? ' Tip: set the status to "Quote sent" so your client sees it.' : ''));
        }
        redirect($self);
    }

    if ($action === 'note') {
        $note = mb_substr(post('note'), 0, 4000);
        if ($note !== '') {
            add_event($id, $adminId, 'note', $note);
            db_run('UPDATE requests SET updated_at = ? WHERE id = ?', [now_utc(), $id]);
            flash('Note added.');
        }
        redirect($self . '#activity');
    }

    if ($action === 'delete') {
        db_run('DELETE FROM request_events WHERE request_id = ?', [$id]);
        db_run('DELETE FROM requests WHERE id = ?', [$id]);
        flash('Request ' . $request['ref'] . ' deleted.');
        redirect(url('admin/requests.php'));
    }
    redirect($self);
}

$events = db_all('SELECT e.*, a.name AS admin_name FROM request_events e LEFT JOIN admins a ON a.id = e.admin_id WHERE e.request_id = ? ORDER BY e.id DESC', [$id]);
$phone = phone_digits($request['phone']);
$first = first_name($request['name']);
$brand = setting('brand_name');
$trackLink = track_url($request);
$waText = "Hi $first! This is $brand — thank you for your website request ({$request['ref']}). ";
$mailSubject = 'Your website request ' . $request['ref'] . ' — ' . $brand;
$mailBody = "Hi $first,\n\nThank you for reaching out to $brand about your website!\n\n\n\nYou can follow your project here: $trackLink\n\nBest regards,\n" . $admin['name'];

admin_start($request['name'], 'requests', '<a class="btn btn-ghost" href="' . e(url('admin/requests.php')) . '">' . icon('arrow-left') . ' All requests</a>');
?>
<section class="req-head card">
  <span class="avatar avatar-lg"><?= e(initials($request['name'])) ?></span>
  <div class="req-title">
    <div class="req-badges"><?= status_badge($request['status']) ?><span class="ref"><?= e($request['ref']) ?></span></div>
    <h2><?= e($request['name']) ?><?= $request['business_name'] !== '' ? ' <span>· ' . e($request['business_name']) . '</span>' : '' ?></h2>
    <p class="muted">Received <?= e(fmt_dt($request['created_at'])) ?> (<?= e(time_ago($request['created_at'])) ?>)</p>
  </div>
  <div class="req-contact">
    <a class="btn btn-primary" href="mailto:<?= e($request['email']) ?>?subject=<?= rawurlencode($mailSubject) ?>&amp;body=<?= rawurlencode($mailBody) ?>"><?= icon('mail') ?> Email</a>
    <?php if ($phone !== ''): ?>
      <a class="btn btn-whatsapp" href="https://wa.me/<?= e($phone) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp</a>
      <a class="btn btn-ghost" href="tel:<?= e(preg_replace('/[^\d+]/', '', $request['phone'])) ?>"><?= icon('phone') ?> Call</a>
    <?php endif ?>
  </div>
</section>

<div class="req-grid">
  <div class="req-main">
    <section class="card">
      <h2 class="card-title">Their idea</h2>
      <div class="message"><?= nl($request['message']) ?></div>
      <dl class="details">
        <div><dt>Email</dt><dd><a href="mailto:<?= e($request['email']) ?>"><?= e($request['email']) ?></a></dd></div>
        <div><dt>Phone / WhatsApp</dt><dd><?= $request['phone'] !== '' ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $request['phone'])) . '">' . e($request['phone']) . '</a>' : '—' ?></dd></div>
        <div><dt>Business</dt><dd><?= e($request['business_name'] ?: '—') ?></dd></div>
        <div><dt>Type of business</dt><dd><?= e($request['business_type'] ?: '—') ?></dd></div>
        <div><dt>Package</dt><dd><?= e($request['package_name'] ?: 'Not sure yet') ?></dd></div>
        <div><dt>Budget</dt><dd><?= $request['budget'] !== '' ? e(setting('currency') . ' ' . $request['budget']) : '—' ?></dd></div>
        <div><dt>Timeline</dt><dd><?= e($request['timeline'] ?: '—') ?></dd></div>
        <div><dt>Current website</dt><dd><?= $request['website_url'] !== '' ? '<a href="' . e($request['website_url']) . '" target="_blank" rel="noopener noreferrer">' . e(preg_replace('~^https?://~', '', $request['website_url'])) . ' ' . icon('external') . '</a>' : '—' ?></dd></div>
      </dl>
    </section>

    <section class="card" id="activity">
      <h2 class="card-title">Notes &amp; activity</h2>
      <form method="post" class="note-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="note"><input type="hidden" name="id" value="<?= $id ?>">
        <textarea name="note" rows="3" maxlength="4000" placeholder="Add a private note — e.g. &quot;Called, wants a pink theme, send quote Friday&quot;" required aria-label="Private note"></textarea>
        <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus') ?> Add note</button>
      </form>
      <ol class="timeline">
        <?php foreach ($events as $ev): ?>
          <li class="tl tl-<?= e($ev['kind']) ?>">
            <span class="tl-icon"><?= icon(['created' => 'inbox', 'status' => 'activity', 'note' => 'note', 'quote' => 'tag', 'client' => 'send'][$ev['kind']] ?? 'activity') ?></span>
            <div>
              <p class="tl-body"><?= nl($ev['body']) ?></p>
              <small><?= e(fmt_dt($ev['created_at'])) ?><?= $ev['admin_name'] ? ' · ' . e($ev['admin_name']) : ($ev['kind'] === 'created' ? ' · from the website' : '') ?></small>
            </div>
          </li>
        <?php endforeach ?>
      </ol>
    </section>
  </div>

  <aside class="req-side">
    <section class="card">
      <h2 class="card-title">Status</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="status-pick" role="radiogroup" aria-label="Status">
          <?php foreach (STATUSES as $key => $s): ?>
            <label class="sp-opt">
              <input type="radio" name="status" value="<?= e($key) ?>"<?= $request['status'] === $key ? ' checked' : '' ?>>
              <span><span class="dot dot-<?= e($s['tone']) ?>"></span><?= e($s['label']) ?></span>
            </label>
          <?php endforeach ?>
        </div>
        <div class="field">
          <label for="client_note">Update for your client <span class="opt">(optional)</span></label>
          <textarea id="client_note" name="client_note" rows="3" maxlength="2000" placeholder="e.g. Your homepage design is ready — check your email!"></textarea>
          <small class="help">Shown on their tracking page.</small>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Save</button>
      </form>
    </section>

    <section class="card">
      <h2 class="card-title">Quote</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="quote"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="field">
          <label for="quoted_amount">Price you quoted (<?= e(setting('currency')) ?>)</label>
          <input id="quoted_amount" name="quoted_amount" type="text" inputmode="decimal" placeholder="e.g. 12999" value="<?= $request['quoted_amount'] !== null ? e(rtrim(rtrim(number_format((float) $request['quoted_amount'], 2, '.', ''), '0'), '.')) : '' ?>">
          <small class="help">Shown to the client once the status is "Quote sent" or later.</small>
        </div>
        <button class="btn btn-ghost btn-block" type="submit">Save quote</button>
      </form>
    </section>

    <section class="card">
      <h2 class="card-title">Client tracking page</h2>
      <p class="muted small">Your client can follow progress here — no account needed. Share it in your messages.</p>
      <div class="copy-row">
        <input id="track-link" type="text" readonly value="<?= e($trackLink) ?>" aria-label="Tracking link">
        <button class="icon-btn" type="button" data-copy="track-link" title="Copy link" aria-label="Copy link"><?= icon('copy') ?></button>
        <a class="icon-btn" href="<?= e($trackLink) ?>" target="_blank" rel="noopener" title="Open" aria-label="Open tracking page"><?= icon('external') ?></a>
      </div>
      <?php if ($request['client_note']): ?>
        <div class="client-note"><small>Current update shown to the client</small><p><?= nl($request['client_note']) ?></p></div>
      <?php endif ?>
    </section>

    <form method="post" class="danger-zone" data-confirm="Delete request <?= e($request['ref']) ?> from <?= e($request['name']) ?>? This can't be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-danger-ghost btn-sm" type="submit"><?= icon('trash') ?> Delete request</button>
    </form>
  </aside>
</div>
<?php admin_end();
