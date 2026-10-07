<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// The driver's onboarding: upload documents → our team reviews → sign the agreement (if any) → join Telegram.
$u = require_verified();
$uid = (int) $u['id'];
$row = onboarding_row($uid);
// Opened from the personal link in their email: unlock their onboarding (it stays hidden until then)
$key = (string) ($_GET['k'] ?? '');
if ($row && $key !== '') {
    if (preg_match('/^[a-f0-9]{32}$/', $key) && hash_equals((string) $row['access_token'], $key)) {
        if (!onboarding_unlocked($row)) {
            db_run('UPDATE onboarding SET opened_at = NOW() WHERE user_id = ?', [$uid]);
        }
        redirect('onboarding.php#steps');
    }
}
if ($row && !onboarding_unlocked($row)) {
    // Not opened from the email yet: point them to their inbox (and let them get the email again)
    if (is_post() && ($_POST['action'] ?? '') === 'resend_link') {
        csrf_check();
        if (rate_limited('onb_link', (string) $uid, 3, 3600)) {
            flash('error', 'We already sent it a few times. Please check your inbox and spam folder, or contact us.');
        } else {
            record_hit('onb_link', (string) $uid);
            $ok = onboarding_send_link($u);
            flash($ok ? 'success' : 'error', $ok ? 'Sent! Check your inbox at ' . $u['email'] . ' for “Your LamazonLoads onboarding link”.' : 'The email could not be sent. Please contact us.');
        }
        redirect('onboarding.php');
    }
    page_header('Check your email');
    dash_open('overview');
    ?>
    <div class="card onb-state onb-locked">
      <span class="onb-state-ico"><?= icon('mail') ?></span>
      <h2>Check your email for your next steps</h2>
      <p class="muted">We emailed your onboarding instructions to <b><?= e($u['email']) ?></b> from info@lamazonloads.com. Open that email and tap <b>Upload my documents</b> to start. If you don’t see it, check your spam or promotions folder.</p>
      <form method="post" action="<?= e(url('onboarding.php')) ?>" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="resend_link">
        <button class="btn btn-ghost" type="submit"><?= icon('mail') ?> Email me the link again</button></form>
    </div>
    <?php
    dash_close();
    page_footer();
    return;
}
$canEdit = $row && in_array($row['stage'], ['documents', 'changes'], true);
$errors = [];
$payErrors = [];
$signErrors = [];
$errItem = '';

if (is_post() && $row) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'upload' && $canEdit) {
        $kind = post('kind', 20);
        $errItem = $kind;
        $raw = $_FILES['files'] ?? null;
        $files = [];
        if (is_array($raw) && is_array($raw['name'] ?? null)) {
            foreach (array_keys($raw['name']) as $i) {
                if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $files[] = ['name' => $raw['name'][$i], 'type' => $raw['type'][$i], 'tmp_name' => $raw['tmp_name'][$i], 'error' => $raw['error'][$i], 'size' => $raw['size'][$i]];
                }
            }
        }
        if (!isset(ONB_ITEMS[$kind]) || $kind === 'payout') {
            $errors[] = 'Please choose what you are uploading.';
        } elseif (!$files) {
            $errors[] = 'Please choose a file to upload.';
        } else {
            foreach (array_slice($files, 0, 10) as $f) {
                [, $err] = save_upload($f, $uid, $kind);
                if ($err !== '') {
                    $errors[] = $err;
                    break;
                }
            }
        }
        if (!$errors) {
            auto_review_user($uid);
            flash('success', ONB_ITEMS[$kind][0] . (count($files) > 1 ? ' uploaded (' . count($files) . ' files).' : ' uploaded.'));
            redirect('onboarding.php#item-' . $kind);
        }
    } elseif ($action === 'remove' && $canEdit) {
        $doc = db_one("SELECT * FROM documents WHERE id = ? AND user_id = ? AND kind IN ('vehicle_photo', 'w9', 'insurance', 'license')", [(int) ($_POST['doc'] ?? 0), $uid]);
        if ($doc) {
            @unlink(__DIR__ . '/uploads/' . basename($doc['stored_name']));
            db_run('DELETE FROM documents WHERE id = ?', [$doc['id']]);
            flash('success', 'File removed.');
        }
        redirect('onboarding.php#item-' . ($doc['kind'] ?? ''));
    } elseif ($action === 'payout' && $canEdit) {
        $errItem = 'payout';
        $payErrors = payout_save($uid);
        if (!$payErrors) {
            flash('success', 'Payment details saved.');
            redirect('onboarding.php#item-payout');
        }
    } elseif ($action === 'submit' && $canEdit) {
        if (onboarding_submit($u)) {
            flash('success', 'Thank you! Your documents were sent to our team. We’ll email you as soon as they’ve been reviewed.');
            redirect('onboarding.php');
        }
        $errors[] = 'Please finish every item on the list first.';
    } elseif ($action === 'sign') {
        $signErrors = onboarding_sign($u);
        if (!$signErrors) {
            flash('success', 'Signed! Welcome to LamazonLoads. Your last step is below.');
            redirect('onboarding.php');
        }
    }
}

$profile = db_one('SELECT * FROM driver_profiles WHERE user_id = ?', [$uid]) ?? [];
page_header('Onboarding');
dash_open('onboarding');

if (!$row): ?>
  <h1>Onboarding</h1>
  <div class="card pad onb-empty">
    <span class="onb-empty-ico"><?= icon('clipboard') ?></span>
    <h3>Your onboarding starts when you apply</h3>
    <p class="muted">Apply to an opening and this page becomes your checklist: upload your documents, get approved, sign your agreement and join our team on Telegram.</p>
    <div class="row-actions" style="justify-content:center">
      <a class="btn btn-accent" href="<?= e(url('careers.php')) ?>">See open opportunities <?= icon('arrow') ?></a>
      <?php if (network_enabled()): ?><a class="btn btn-ghost" href="<?= e(url('job.php?id=0')) ?>">Join the driver network</a><?php endif; ?>
    </div>
  </div>
<?php dash_close(); page_footer(); return; endif;

$items = onboarding_items($uid);
$done = count(array_filter($items, fn ($i) => $i[2]));
$total = count($items);
$steps = onboarding_steps_view($row);
$stage = $row['stage'];
?>
<div class="onb-top">
  <div>
    <span class="eyebrow"><?= e(ONB_TRACKS[$row['track']] ?? '') ?></span>
    <h1 class="mb-0">Your onboarding</h1>
  </div>
  <?php if ($canEdit): ?><span class="onb-count"><b><?= $done ?></b> of <?= $total ?> done</span><?php endif; ?>
</div>

<ol class="onb-steps" id="steps" aria-label="Onboarding steps">
  <?php $n = 0; foreach ($steps as [$label, $state]): $n++; ?>
    <li class="is-<?= e($state) ?>"<?= $state === 'now' ? ' aria-current="step"' : '' ?>><span class="onb-dot"><?= $state === 'done' ? icon('check') : $n ?></span><span><?= e($label) ?></span></li>
  <?php endforeach; ?>
</ol>

<?php if ($canEdit): ?>
  <?php if ($stage === 'changes' && trim((string) $row['review_note']) !== ''): ?>
    <div class="onb-note"><?= icon('chat') ?><div><b>Our team asked for a few changes</b><p><?= nl2br(e((string) $row['review_note'])) ?></p></div></div>
  <?php endif; ?>
  <p class="muted onb-lead">Upload each item below. Photos from your phone are fine (JPG or PNG), and so are PDFs. Only you and LamazonLoads staff can see them.</p>
  <?php if ($errors && $errItem === ''): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

  <div class="onb-list">
    <?php foreach ($items as $k => [$label, $hint, $ok, $docs]): ?>
      <section class="card onb-item<?= $ok ? ' is-done' : '' ?>" id="item-<?= e($k) ?>">
        <header class="onb-item-head">
          <span class="onb-tick"><?= $ok ? icon('check') : icon($k === 'payout' ? 'dollar' : 'upload') ?></span>
          <div><h3><?= e($label) ?></h3><p><?= e($hint) ?></p></div>
          <span class="badge <?= $ok ? 'badge-open' : 'badge-reviewing' ?>"><?= $ok ? 'Done' : 'Needed' ?></span>
        </header>
        <?php if ($errItem === $k && ($errors || $payErrors)): ?><ul class="errors"><?php foreach (array_merge($errors, $payErrors) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>

        <?php if ($k === 'payout'):
            $pm = $errItem === 'payout' ? post('payout_method', 20) : (string) ($profile['payout_method'] ?? '');
            $pm = $pm !== '' ? $pm : 'zelle'; ?>
          <form method="post" action="<?= e(url('onboarding.php')) ?>#item-payout" class="payout-form" data-payout novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="payout">
            <fieldset class="pay-methods"><legend class="sr-only">How should we pay you?</legend>
              <?php foreach (PAYOUT_METHODS as $mk => $ml): ?>
                <label class="pay-opt"><input type="radio" name="payout_method" value="<?= e($mk) ?>"<?= $pm === $mk ? ' checked' : '' ?>>
                  <span><b><?= e($ml) ?></b><?= $mk === 'zelle' ? '<small class="pay-pref">Preferred</small>' : ($mk === 'direct_deposit' ? '<small>When available</small>' : '') ?></span></label>
              <?php endforeach; ?>
            </fieldset>
            <div class="form-grid">
              <div><label for="payout_name">Name on the account</label><input id="payout_name" name="payout_name" type="text" maxlength="120" autocomplete="name" value="<?= e($errItem === 'payout' ? post('payout_name', 120) : (string) ($profile['payout_name'] ?? $u['name'])) ?>"></div>
              <div data-pay-handle><label for="payout_handle" data-pay-label>Zelle email or phone number</label><input id="payout_handle" name="payout_handle" type="text" maxlength="190" value="<?= e($errItem === 'payout' ? post('payout_handle', 190) : (string) ($profile['payout_handle'] ?? '')) ?>"></div>
              <p class="hint full" data-pay-dd hidden>Direct deposit is offered when available. Our team will contact you to set it up securely; we never ask for bank numbers on the website.</p>
            </div>
            <button class="btn btn-primary btn-sm mt" type="submit"><?= $ok ? 'Update payment details' : 'Save payment details' ?></button>
          </form>
        <?php else: ?>
          <?php if ($docs): ?>
            <ul class="onb-files">
              <?php foreach ($docs as $d): ?>
                <li><?= icon('file') ?><?= doc_link($d, e($d['original_name'])) ?><?php if ($d['added_by']): ?><span class="doc-staff"><?= icon('shield') ?> Added by LamazonLoads staff</span><?php endif; ?>
                  <form method="post" action="<?= e(url('onboarding.php')) ?>" class="inline-form" data-confirm="Remove this file?"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="doc" value="<?= (int) $d['id'] ?>"><button class="link-btn onb-remove" type="submit" aria-label="Remove <?= e($d['original_name']) ?>"><?= icon('trash') ?></button></form></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <form method="post" action="<?= e(url('onboarding.php')) ?>#item-<?= e($k) ?>" enctype="multipart/form-data" class="onb-upload">
            <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="kind" value="<?= e($k) ?>">
            <label class="onb-drop"><input type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png,image/*"<?= $k === 'vehicle_photo' ? ' multiple' : '' ?> required data-onb-file>
              <span><?= icon('upload') ?> <b><?= $docs ? ($k === 'vehicle_photo' ? 'Add more photos' : 'Upload a new file') : ($k === 'vehicle_photo' ? 'Choose photos' : 'Choose a file') ?></b><small data-onb-name>PDF, JPG or PNG, up to <?= (int) config('max_upload_mb') ?> MB</small></span></label>
            <button class="btn btn-accent btn-sm" type="submit">Upload</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="card onb-submit<?= $done === $total ? ' is-ready' : '' ?>">
    <div>
      <b><?= $done === $total ? 'All set! Send your documents to our team' : 'Finish ' . ($total - $done) . ' more item' . ($total - $done === 1 ? '' : 's') . ' to continue' ?></b>
      <p class="muted mb-0"><?= $done === $total ? 'We’ll review them and email you, usually within one business day.' : 'Your progress is saved. You can come back any time.' ?></p>
    </div>
    <form method="post" action="<?= e(url('onboarding.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="submit">
      <button class="btn btn-accent btn-lg" type="submit"<?= $done === $total ? '' : ' disabled' ?>>Submit for review <?= icon('arrow') ?></button></form>
  </div>

<?php elseif ($stage === 'review'): ?>
  <div class="card onb-state">
    <span class="onb-state-ico"><?= icon('clock') ?></span>
    <h2>We’re reviewing your documents</h2>
    <p class="muted">Thanks for sending everything<?= $row['submitted_at'] ? ' on ' . e(fmt_date((string) $row['submitted_at'], 'M j')) : '' ?>. Our team usually reviews within one business day, and we’ll email you at <b><?= e($u['email']) ?></b> as soon as it’s done.</p>
  </div>
  <section class="card pad">
    <h3 class="mt-0">What you sent</h3>
    <ul class="onb-summary">
      <?php foreach ($items as $k => [$label, , $ok, $docs]): ?>
        <li><?= icon('check') ?><b><?= e($label) ?></b><span class="muted"><?= $k === 'payout' ? e(payout_label($profile)) : count($docs) . ' file' . (count($docs) === 1 ? '' : 's') ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>

<?php elseif ($stage === 'contract'):
    $c = contract_get($row['track']);
    $company = $signErrors ? post('signed_company', 120) : (string) ($profile['company_name'] ?? '');
    $signName = $signErrors ? post('signed_name', 120) : (string) $u['name']; ?>
  <div class="onb-note onb-note-ok"><?= icon('check') ?><div><b>You’re approved!</b><p>Please read your agreement below, then sign at the bottom. It only takes a couple of minutes.</p></div></div>
  <section class="card contract-card">
    <header class="contract-head"><span class="eyebrow">Agreement</span><h2><?= e($c['title']) ?></h2></header>
    <div class="contract-body" tabindex="0"><?= contract_html(contract_fill((string) $c['body'], ['name' => $signName] + $u, $company)) ?></div>
  </section>
  <form method="post" action="<?= e(url('onboarding.php')) ?>" class="card form-card sign-form" id="sign" novalidate data-sign-form>
    <?= csrf_field() ?><input type="hidden" name="action" value="sign">
    <h3 class="mt-0">Sign your agreement</h3>
    <?php if ($signErrors): ?><ul class="errors"><?php foreach ($signErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <div class="form-grid">
      <div><label for="signed_name">Printed name (your full legal name)</label><input id="signed_name" name="signed_name" type="text" maxlength="120" autocomplete="name" value="<?= e($signName) ?>" required></div>
      <div><label for="signed_company">Company name <span class="opt">(if any)</span></label><input id="signed_company" name="signed_company" type="text" maxlength="120" value="<?= e($company) ?>"></div>
    </div>
    <?php if ((int) $c['ask_emergency'] === 1): ?>
      <h4 class="sign-sub">Emergency contact information</h4>
      <div class="form-grid">
        <div><label for="emergency_name">Emergency contact name</label><input id="emergency_name" name="emergency_name" type="text" maxlength="120" value="<?= e(post('emergency_name', 120)) ?>"></div>
        <div><label for="emergency_relation">Relationship</label><input id="emergency_relation" name="emergency_relation" type="text" maxlength="60" placeholder="Spouse, parent, friend…" value="<?= e(post('emergency_relation', 60)) ?>"></div>
        <div><label for="emergency_phone">Phone number</label><input id="emergency_phone" name="emergency_phone" type="tel" maxlength="30" placeholder="(555) 123-4567" value="<?= e(post('emergency_phone', 30)) ?>"></div>
      </div>
    <?php endif; ?>
    <h4 class="sign-sub">Signature</h4>
    <div class="sig-pad" data-sig>
      <canvas aria-label="Draw your signature here" role="img"></canvas>
      <span class="sig-hint" data-sig-hint>Sign here with your finger or mouse</span>
      <button type="button" class="link-btn sig-clear" data-sig-clear>Clear</button>
    </div>
    <input type="hidden" name="signature" data-sig-input>
    <p class="hint">Date: <b><?= e(date('F j, Y')) ?></b></p>
    <label class="check sign-agree"><input type="checkbox" name="agree" value="1"> I have read and agree to the <?= e($c['title']) ?>. I understand that drawing my signature and typing my name above is my electronic signature.</label>
    <button class="btn btn-accent btn-lg btn-block mt" type="submit"><?= icon('edit') ?> Sign agreement</button>
  </form>

<?php else: /* done */ ?>
  <div class="card tg-card">
    <div class="tg-text">
      <span class="eyebrow">You’re all set</span>
      <h2>Welcome to LamazonLoads, <?= e(onb_first($u)) ?>!</h2>
      <p>Your last step: join our driver onboarding group on Telegram. Our team will share your next steps there.</p>
      <div class="row-actions">
        <a class="btn btn-accent btn-lg" href="<?= e(telegram_link()) ?>" target="_blank" rel="noopener"><?= icon('send') ?> Join <?= e(telegram_handle()) ?></a>
        <?php if ($row['signed_at']): ?><a class="btn btn-ghost" href="<?= e(url('contract.php')) ?>"><?= icon('file') ?> My signed agreement</a><?php endif; ?>
      </div>
      <p class="hint">No Telegram yet? Get the free app at telegram.org, then search <b><?= e(telegram_handle()) ?></b>.</p>
    </div>
    <?php if (($qr = telegram_qr_url()) !== ''): ?>
      <figure class="tg-qr"><img src="<?= e($qr) ?>" alt="QR code to join <?= e(telegram_handle()) ?> on Telegram" width="200" height="200"><figcaption>Or scan with your phone camera</figcaption></figure>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php dash_close(); page_footer();
