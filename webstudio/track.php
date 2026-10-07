<?php
/**
 * A client's private page for following their website request. The link (with its random
 * token) is shown after sending the form; there's no account to sign in to.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Referrer-Policy: no-referrer'); // don't leak the private link to other sites
header('X-Robots-Tag: noindex, nofollow');

$token = param('t');
$request = preg_match('/^[a-f0-9]{32}$/', $token) ? db_one('SELECT * FROM requests WHERE track_token = ?', [$token]) : null;
$justSent = param('new') === '1' || param('sent') === '1';

if (!$request && param('sent') === '1') {
    page_start(['title' => 'Request sent', 'noindex' => true]);
    echo '<section class="simple-page"><div class="container narrow center"><div class="success-burst">' . icon('check') . '</div>'
        . '<h1>Thank you! Your request was sent.</h1><p class="lead">We\'ll get back to you within ' . e(setting('reply_time')) . '.</p>'
        . '<a class="btn btn-orange" href="' . e(url()) . '">Back to home ' . icon('arrow-right') . '</a></div></section>';
    page_end();
    exit;
}
if (!$request) {
    not_found();
}

$status = $request['status'];
$closed = $status === 'closed';
$currentIndex = (int) array_search($status, CLIENT_STEPS, true);
$stepHelp = [
    'new' => 'We have your request and will review it shortly.',
    'contacted' => 'We\'ll talk about your business, goals and style.',
    'quoted' => 'Your price, timeline and plan.',
    'in_progress' => 'We design and build your website with you.',
    'launched' => 'Your website is live for the world to see.',
];
$link = track_url($request);
$email = setting('contact_email');
$phone = setting('contact_phone');
$chat = chat_link();

page_start(['title' => 'Your website request', 'noindex' => true, 'dark_top' => true]);
?>
<section class="track-hero">
  <?php if ($justSent): ?><div class="confetti" data-confetti aria-hidden="true"></div><?php endif ?>
  <div class="container">
    <?php if ($justSent): ?>
      <div class="success-burst"><?= icon('check') ?></div>
      <h1>Thank you, <?= e(first_name($request['name'])) ?>! <span class="hl">Your request is in.</span></h1>
      <p>We'll review your idea and get back to you within <?= e(setting('reply_time')) ?>. Bookmark this page to follow your project anytime.</p>
    <?php else: ?>
      <span class="track-ref"><?= icon('briefcase') ?> <?= e($request['ref']) ?></span>
      <h1>Hi <?= e(first_name($request['name'])) ?>, here's <span class="hl">your project.</span></h1>
      <p>Follow your website request from idea to launch.</p>
    <?php endif ?>
  </div>
</section>

<section class="track-body">
  <div class="container track-grid">
    <div>
      <div class="card">
        <h2>Project progress</h2>
        <p class="muted">Reference <b><?= e($request['ref']) ?></b> · sent <?= e(fmt_dt($request['created_at'], 'M j, Y')) ?></p>
        <?php if ($closed): ?>
          <div class="closed-box">This request is closed. If you'd like to pick it up again, just get in touch — we're happy to help.</div>
        <?php else: ?>
          <ol class="timeline">
            <?php foreach (CLIENT_STEPS as $i => $step):
                $state = $i < $currentIndex || $status === 'launched' ? 'is-done' : ($i === $currentIndex ? 'is-current' : ''); ?>
              <li class="tl-step <?= $state ?>">
                <span class="tl-dot"><?= $state === 'is-done' ? icon('check') : $i + 1 ?></span>
                <div class="tl-text">
                  <b><?= e(STATUSES[$step]['client']) ?><?php if ($state === 'is-current'): ?><span class="tl-now">Now</span><?php endif ?></b>
                  <small><?= e($stepHelp[$step]) ?></small>
                </div>
              </li>
            <?php endforeach ?>
          </ol>
        <?php endif ?>
        <?php if (!empty($request['client_note'])): ?>
          <div class="update-box">
            <b>Latest update from our team</b>
            <p><?= nl($request['client_note']) ?></p>
            <small><?= e(fmt_dt($request['client_note_at'])) ?></small>
          </div>
        <?php endif ?>
        <?php if ($request['quoted_amount'] !== null && in_array($status, ['quoted', 'in_progress', 'launched'], true)): ?>
          <div class="update-box"><b>Your quote: <?= e(money($request['quoted_amount'])) ?></b></div>
        <?php endif ?>
      </div>

      <div class="card">
        <h2>Your private link</h2>
        <p class="muted">Anyone with this link can see this page, so only share it with people you trust.</p>
        <div class="copy-row">
          <input id="track-link" type="text" value="<?= e($link) ?>" readonly aria-label="Your private link">
          <button class="btn btn-primary btn-sm" type="button" data-copy="track-link"><?= icon('copy') ?> <span>Copy link</span></button>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>Your request</h2>
        <dl class="summary">
          <?php if ($request['business_name'] !== ''): ?><div><dt>Business</dt><dd><?= e($request['business_name']) ?><?= $request['business_type'] !== '' ? ' · ' . e($request['business_type']) : '' ?></dd></div><?php endif ?>
          <?php if ($request['package_name'] !== ''): ?><div><dt>Package</dt><dd><?= e($request['package_name']) ?></dd></div><?php endif ?>
          <?php if ($request['timeline'] !== ''): ?><div><dt>Timeline</dt><dd><?= e($request['timeline']) ?></dd></div><?php endif ?>
          <div><dt>Your idea</dt><dd><?= nl($request['message']) ?></dd></div>
        </dl>
      </div>
      <?php if ($email !== '' || $phone !== '' || $chat): ?>
        <div class="card">
          <h2>Questions?</h2>
          <p class="muted">Quote your reference <b><?= e($request['ref']) ?></b> when you contact us.</p>
          <div class="contact-list">
            <?php if ($chat): ?><a href="<?= e($chat[1]) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Message us on <?= e($chat[0]) ?></a><?php endif ?>
            <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= icon('phone') ?> <?= e($phone) ?></a><?php endif ?>
            <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>?subject=<?= rawurlencode('Website request ' . $request['ref']) ?>"><?= icon('mail') ?> <?= e($email) ?></a><?php endif ?>
          </div>
        </div>
      <?php endif ?>
    </div>
  </div>
</section>
<?php page_end();
