<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// "Stop reminders" from the bottom of a follow-up email. The link carries a code made from the address, so only the
// person who got the email can use it. Stopping needs a tap on this page: some email apps open links to scan them.
$email = strtolower(trim(as_str($_GET['e'] ?? $_POST['e'] ?? '')));
$token = as_str($_GET['t'] ?? $_POST['t'] ?? '');
$valid = email_valid($email) && preg_match('/^[a-f0-9]{32}$/', $token) && hash_equals(followup_stop_token($email), $token);
$self = 'reminders.php?e=' . rawurlencode($email) . '&t=' . $token;

if ($valid && is_post()) {
    csrf_check();
    $action = as_str($_POST['action'] ?? '');
    if ($action === 'stop') {
        if (!followup_opted_out($email)) {
            followup_set_optout($email, true);
        }
        redirect($self . '&done=stopped');
    }
    if ($action === 'resume' && db_val('SELECT 1 FROM followup_optout WHERE email = ? AND by_staff IS NULL', [$email])) {
        followup_set_optout($email, false);
        redirect($self . '&done=resumed');
    }
    redirect($self);
}
$row = $valid ? db_one('SELECT * FROM followup_optout WHERE email = ?', [$email]) : null;
$done = as_str($_GET['done'] ?? '');

page_header('Reminder emails', '', '', 'page-auth');
?>
<div class="auth-wrap">
  <aside class="auth-side">
    <span class="logo-badge"><?= logo_html() ?></span>
    <h2 class="mt">Your inbox, your call.</h2>
    <p>We send a few short reminders when a step of your sign-up or onboarding is waiting. You can stop them anytime.</p>
    <p class="quote">Why wait? Let's freight.</p>
    <div class="road" aria-hidden="true"></div>
  </aside>
  <div class="auth-main">
    <div class="auth-box">
      <?php if (!$valid): ?>
        <div class="ss">
          <div class="ss-ico" aria-hidden="true"><?= icon('info') ?></div>
          <h1 class="ss-title">This link doesn’t work</h1>
          <p class="ss-lead">It may have been cut off when it was copied. Please open it again from the bottom of our email.</p>
          <?= ss_hint('Still stuck? <a href="' . e(url('contact.php')) . '">Contact us</a> and we’ll stop the reminders for you.') ?>
          <div class="ss-acts"><a class="btn btn-ghost btn-block" href="<?= e(url('')) ?>">Go to the homepage</a></div>
        </div>
      <?php elseif ($row): ?>
        <div class="ss">
          <div class="ss-ico" aria-hidden="true"><?= icon('check') ?></div>
          <h1 class="ss-title">Reminders stopped</h1>
          <p class="ss-lead">We won’t send any more reminder emails to this address.</p>
          <?= ss_chip($email) ?>
          <?= ss_hint('You’ll still get the emails you ask for, like password resets, and replies from our team.') ?>
          <?php if ($row['by_staff'] === null): ?>
            <form method="post" action="<?= e(url('reminders.php')) ?>" class="ss-acts">
              <?= csrf_field() ?>
              <input type="hidden" name="e" value="<?= e($email) ?>"><input type="hidden" name="t" value="<?= e($token) ?>">
              <button class="btn btn-ghost btn-block" type="submit" name="action" value="resume">Changed your mind? Turn them back on</button>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="ss">
          <div class="ss-ico" aria-hidden="true"><?= icon($done === 'resumed' ? 'check' : 'mail') ?></div>
          <?php if ($done === 'resumed'): ?>
            <h1 class="ss-title">Reminders are back on</h1>
            <p class="ss-lead">We’ll let you know when a step is waiting for you.</p>
            <?= ss_chip($email) ?>
            <div class="ss-acts"><?php if (db_val('SELECT 1 FROM users WHERE email = ?', [$email])): ?>
              <a class="btn btn-primary btn-block" href="<?= e(url('account.php')) ?>">Go to my account</a>
            <?php else: ?>
              <a class="btn btn-primary btn-block" href="<?= e(url('register.php?email=' . rawurlencode($email) . '&next=onboarding.php')) ?>">Create my account</a>
            <?php endif; ?></div>
          <?php else: ?>
            <h1 class="ss-title">Stop reminder emails?</h1>
            <p class="ss-lead">We’ll stop the reminders about unfinished steps for this address.</p>
            <?= ss_chip($email) ?>
            <?= ss_hint('You’ll still get the emails you ask for, like password resets, and replies from our team.') ?>
            <form method="post" action="<?= e(url('reminders.php')) ?>" class="ss-acts">
              <?= csrf_field() ?>
              <input type="hidden" name="e" value="<?= e($email) ?>"><input type="hidden" name="t" value="<?= e($token) ?>">
              <button class="btn btn-primary btn-block" type="submit" name="action" value="stop">Stop reminders</button>
              <a class="btn btn-ghost btn-block" href="<?= e(url('')) ?>">Keep getting them</a>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
