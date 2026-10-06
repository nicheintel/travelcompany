<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$topics = ['dispatch' => 'Freight dispatching', 'routes' => 'Daily routes', 'onboarding' => 'Driver onboarding', 'shipper' => 'I have freight to move', 'other' => 'Something else'];
$errors = [];
$u = current_user();
$val = ['name' => $u['name'] ?? '', 'email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? '', 'topic' => 'dispatch', 'message' => ''];

if (is_post()) {
    csrf_check();
    foreach ($val as $k => $_) {
        $val[$k] = post($k, $k === 'message' ? 4000 : 190);
    }
    $val['phone'] = format_phone($val['phone']);
    if (post('website') !== '') { // hidden field: only bots fill it in
        redirect('contact.php?sent=1');
    }
    if ($val['name'] === '') $errors[] = 'Please enter your name.';
    if (!filter_var($val['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!isset($topics[$val['topic']])) $val['topic'] = 'other';
    if (mb_strlen($val['message']) < 5) $errors[] = 'Please write a short message.';
    $recent = (int) db_val('SELECT COUNT(*) FROM messages WHERE email = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$val['email']]);
    if ($recent >= 5 || rate_limited('contact', client_ip(), 10, 3600)) $errors[] = 'You have sent several messages already. We will get back to you soon.';
    if (!$errors) {
        record_hit('contact', client_ip());
        db_run('INSERT INTO messages (name, email, phone, topic, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [mb_substr($val['name'], 0, 100), $val['email'], mb_substr($val['phone'], 0, 30), $val['topic'], $val['message']]);
        redirect('contact.php?sent=1');
    }
}

preload_photo('banner_contact');
page_header('Contact', 'contact', 'Contact LamazonLoads dispatch and driver support.');
page_hero('Contact us', 'Talk to dispatch', "Questions about dispatching, daily routes or onboarding? Send us a message and a real person will get back to you.", 'banner_contact', 'center 25%');
$email = (string) config('contact_email');
$phone = (string) config('contact_phone');
?>
<section class="section">
  <div class="container story level contact-layout">
    <div class="reveal level-col">
      <span class="eyebrow">Reach us</span>
      <h2>We're here to keep you moving</h2>
      <div class="card pad contact-card">
      <ul class="checklist">
        <?php if ($email !== ''): ?><li><span class="tick"><?= icon('mail') ?></span><span><b>Email</b><br><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li><span class="tick"><?= icon('phone') ?></span><span><b>Phone</b><br><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></span></li><?php endif; ?>
        <li><span class="tick"><?= icon('users') ?></span><span><b>Already a member?</b><br>Your dedicated support group details are shared after onboarding.</span></li>
        <li><span class="tick"><?= icon('chat') ?></span><span><b>Prefer to chat?</b><br>Tap the Chat button in the corner. We answer there too.</span></li>
      </ul>
      </div>
    </div>
    <div class="card form-card reveal">
      <?php if (isset($_GET['sent'])): ?>
        <div class="center">
          <div class="ico-lg" style="margin:0 auto 16px;background:linear-gradient(135deg,#12A150,#0B6B35)"><?= icon('check') ?></div>
          <h2>Message sent</h2>
          <p class="muted">Thanks for reaching out. Our team will get back to you soon.</p>
          <a class="btn btn-primary" href="<?= e(url('')) ?>">Back to home</a>
        </div>
      <?php else: ?>
        <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= e(url('contact.php')) ?>" class="form-grid" novalidate>
          <?= csrf_field() ?>
          <div style="position:absolute;left:-5000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <div><label for="name">Your name</label><input id="name" name="name" type="text" required maxlength="100" value="<?= e($val['name']) ?>" autocomplete="name"></div>
          <div><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= e($val['email']) ?>" autocomplete="email"></div>
          <div><label for="phone">Phone <span class="opt">(optional)</span></label><input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($val['phone']) ?>" autocomplete="tel"></div>
          <div><label for="topic">Topic</label><select id="topic" name="topic"><?php foreach ($topics as $k => $l): ?><option value="<?= e($k) ?>"<?= $val['topic'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="full"><label for="message">Message</label><textarea id="message" name="message" required maxlength="4000"><?= e($val['message']) ?></textarea></div>
          <div class="full"><button class="btn btn-accent btn-lg" type="submit">Send message <?= icon('arrow') ?></button></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
