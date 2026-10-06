<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$total = array_sum(array_map(fn ($c) => count($c[2]), FAQ));
$phone = (string) config('contact_phone');
$email = (string) config('contact_email');

// Lets Google show these questions and answers in search results
$ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach (FAQ as [, , $items]) {
    foreach ($items as [$q, $a]) {
        $ld['mainEntity'][] = ['@type' => 'Question', 'name' => trim($q, '“”'), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => str_replace("\n- ", "\n• ", $a)]];
    }
}

page_header('Driver FAQ', '', 'Answers for drivers and owner-operators: LamazonLoads loads, daily routes, Walmart routes, onboarding, dispatch fees, payment, referrals and support.');
page_hero('Help center', 'Frequently asked questions', "Everything drivers ask us about loads, daily routes, onboarding, dispatch and getting paid. Can't find it? Call or message us.");
?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<section class="section faq-page">
  <div class="container faq-layout">
    <aside class="faq-side">
      <div class="faq-search">
        <?= icon('search') ?>
        <input type="search" placeholder="Search <?= $total ?> answers…" aria-label="Search the FAQ" data-faq-search autocomplete="off">
      </div>
      <nav class="faq-nav" aria-label="FAQ sections">
        <?php foreach (FAQ as $key => [$title, $ic, $items]): ?>
          <a href="#<?= e($key) ?>" data-faq-link="<?= e($key) ?>"><?= icon($ic) ?><span><?= e($title) ?></span><small><?= count($items) ?></small></a>
        <?php endforeach; ?>
      </nav>
      <div class="faq-help card pad">
        <b>Still have a question?</b>
        <?php if ($phone !== ''): ?><a href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?><?= e($phone) ?></a><?php endif; ?>
        <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= icon('mail') ?><?= e($email) ?></a><?php endif; ?>
      </div>
    </aside>

    <div class="faq-main">
      <p class="faq-empty card pad" data-faq-empty hidden>No answers match “<span data-faq-term></span>”. Try another word, or <a href="<?= e(url('contact.php')) ?>">ask us directly</a>.</p>
      <?php $n = 0; foreach (FAQ as $key => [$title, $ic, $items]): ?>
        <section class="faq-group" id="<?= e($key) ?>" data-faq-group>
          <h2><span class="faq-ico"><?= icon($ic) ?></span><?= e($title) ?></h2>
          <div class="faq">
            <?php foreach ($items as $id => [$q, $a]): $n++; ?>
              <details id="<?= e($id) ?>" data-faq-item>
                <summary><span class="faq-q"><span class="faq-n"><?= $n ?></span><?= e($q) ?></span></summary>
                <div class="faq-a"><?= faq_answer_html($a) ?></div>
              </details>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <section class="quick-card" id="quick-response">
        <div class="quick-head">
          <div><span class="eyebrow">Looking for work?</span><h2>Quick driver response</h2>
            <p>Copy this, fill it in and send it to our team. It's the fastest way to get matched.</p></div>
          <button class="btn btn-accent" type="button" data-copy="quick-text"><?= icon('clipboard') ?><span data-copy-label>Copy template</span></button>
        </div>
        <pre id="quick-text"><?= e(implode("\n", FAQ_QUICK_RESPONSE)) ?></pre>
        <p class="quick-foot">LamazonLoads · <b>Why wait? Let's freight.</b></p>
      </section>
    </div>
  </div>
</section>
<?php cta_band(); page_footer();
