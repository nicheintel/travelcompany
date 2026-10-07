<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$brand = setting('brand_name');
$email = setting('contact_email');
page_start(['title' => 'Privacy notice', 'description' => 'How ' . $brand . ' uses the details you send us.']);
?>
<section class="simple-page">
  <div class="container narrow prose">
    <p class="eyebrow">Privacy</p>
    <h1>Privacy notice</h1>
    <p class="lead">We keep this simple: we only collect what we need to reply to you and build your website.</p>

    <h2>What we collect</h2>
    <p>When you send a request, we receive the details you type in the form: your name, email, phone number, business details, budget, timeline, any web address you share and your message. We also record the date and the internet (IP) address it was sent from, to stop spam.</p>

    <h2>How we use it</h2>
    <ul>
      <li>To reply to your request and prepare your quote.</li>
      <li>To keep in touch about your project, and to show your progress on your private tracking page.</li>
    </ul>
    <p>We don't sell your details or share them with anyone for marketing. This website doesn't use advertising or tracking cookies; it only uses one cookie that keeps the form secure.</p>

    <h2>How long we keep it</h2>
    <p>We keep your request for as long as we work together and for a reasonable time after, for our records. You can ask us to delete it at any time.</p>

    <h2>Your choices</h2>
    <p>You can ask to see, correct or delete the details we hold about you<?= $email !== '' ? ' — just email <a href="mailto:' . e($email) . '">' . e($email) . '</a>' : ' — just contact us' ?>.</p>

    <a class="btn btn-orange" href="<?= e(url()) ?>">Back to home <?= icon('arrow-right') ?></a>
  </div>
</section>
<?php page_end();
