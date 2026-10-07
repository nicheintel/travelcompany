<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$packages = active_packages();
$projects = active_projects();
$testimonials = active_testimonials();
$faqs = active_faqs();

// The request form: values and errors when it was sent back with a problem (see request.php).
$old = $_SESSION['quote_old'] ?? [];
$errors = $_SESSION['quote_errors'] ?? [];
unset($_SESSION['quote_old'], $_SESSION['quote_errors']);
$val = fn(string $k): string => (string) ($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? '<p class="field-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$aria = fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
$chosenPackage = $val('package') !== '' ? $val('package') : param('package');

$priced = array_filter($packages, fn($p) => $p['price'] !== null);
$fromPrice = $priced ? min(array_map(fn($p) => (float) $p['price'], $priced)) : null;
$categories = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'], $projects))));
$month = (new DateTimeImmutable('now', site_timezone()))->format('F');
$email = setting('contact_email');
$phone = setting('contact_phone');
$chat = chat_link();

page_start(['home' => true]);
?>

<!-- ============ HERO ============ -->
<section class="hero" id="top">
  <div class="hero-bg" aria-hidden="true">
    <div class="hero-grid"></div>
    <div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div>
  </div>
  <div class="container hero-inner">
    <div class="hero-copy">
      <p class="pill"><span class="pulse-dot"></span> Now booking website projects for <?= e($month) ?></p>
      <h1 class="hero-title"><?= highlight_stars(setting('hero_title')) ?></h1>
      <p class="hero-text"><?= e(setting('hero_text')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-orange btn-lg btn-shine" href="#quote">Request your website <?= icon('arrow-right') ?></a>
        <a class="btn btn-glass btn-lg" href="#pricing">See pricing</a>
      </div>
      <ul class="hero-checks">
        <li><?= icon('check') ?> Free consultation</li>
        <li><?= icon('check') ?> Mobile-friendly</li>
        <li><?= icon('check') ?> No hidden fees</li>
      </ul>
      <?php if ($fromPrice !== null): ?>
        <p class="hero-from">Websites from <b><?= e(money($fromPrice)) ?></b> · one-time payment</p>
      <?php endif ?>
    </div>

    <div class="hero-visual" aria-hidden="true" data-demo>
      <div class="mock-browser">
        <div class="mock-bar">
          <span class="dots"><i></i><i></i><i></i></span>
          <span class="mock-url"><?= icon('lock') ?><span data-demo="url">yourbakery.com</span></span>
        </div>
        <div class="mock-site">
          <div class="ms-nav"><span class="ms-logo"></span><span class="ms-links"><i></i><i></i><i></i></span><span class="ms-navbtn"></span></div>
          <div class="ms-hero">
            <div class="ms-copy" data-demo-swap>
              <span class="ms-kicker" data-demo="kicker">Fresh every morning</span>
              <b class="ms-title" data-demo="title">Baked with love, served daily.</b>
              <span class="ms-line"></span><span class="ms-line ms-short"></span>
              <span class="ms-cta" data-demo="cta">Order now</span>
            </div>
            <div class="ms-art">
              <span class="ms-sun"></span><span class="ms-blob"></span>
              <span class="ms-emoji" data-demo="emoji" data-demo-swap>🥐</span>
            </div>
          </div>
          <div class="ms-cards"><span><i></i><b></b><b></b></span><span><i></i><b></b><b></b></span><span><i></i><b></b><b></b></span></div>
        </div>
      </div>

      <div class="mock-phone">
        <span class="mp-notch"></span>
        <div class="mp-screen">
          <span class="mp-nav"></span>
          <div class="mp-hero"><b data-demo="title" data-demo-swap>Baked with love, served daily.</b><span class="mp-cta"></span></div>
          <span class="mp-card"></span><span class="mp-card"></span>
        </div>
      </div>

      <div class="float-card fc-inquiry">
        <span class="fc-icon fc-orange"><?= icon('bell') ?></span>
        <span><b>New customer inquiry</b><small>from your website · just now</small></span>
      </div>
      <div class="float-card fc-score">
        <svg viewBox="0 0 44 44" class="ring"><circle cx="22" cy="22" r="18" class="ring-bg"/><circle cx="22" cy="22" r="18" class="ring-fg"/></svg>
        <span><b>98</b><small>Speed score</small></span>
      </div>
      <div class="float-card fc-live"><span class="live-dot"></span> Live &amp; on Google</div>
    </div>
  </div>

  <div class="container">
    <ul class="hero-stats">
      <li><b>5–14 days</b><span>Typical launch time</span></li>
      <li><b>100%</b><span>Mobile-friendly designs</span></li>
      <li><b><?= e(setting('currency')) ?>0</b><span>Hidden fees</span></li>
      <li><b><?= e(setting('reply_time')) ?></b><span>Reply to every request</span></li>
    </ul>
  </div>
</section>

<!-- ============ BUSINESS TYPES ============ -->
<section class="marquee-band" aria-label="Businesses we build websites for">
  <p class="marquee-label">Websites for every kind of business</p>
  <div class="marquee">
    <div class="marquee-track">
      <?php $types = ['Bakeries', 'Cafés & restaurants', 'Salons & spas', 'Dental & medical clinics', 'Real estate', 'Online shops', 'Gyms & coaches', 'Tutors & schools', 'Contractors', 'Photographers', 'Churches & non-profits', 'Travel agencies', 'Freelancers', 'Startups'];
      for ($copy = 0; $copy < 2; $copy++): ?>
        <div class="marquee-group"<?= $copy ? ' aria-hidden="true"' : '' ?>>
          <?php foreach ($types as $t): ?><span><?= e($t) ?></span><?php endforeach ?>
        </div>
      <?php endfor ?>
    </div>
  </div>
</section>

<!-- ============ SERVICES ============ -->
<section class="section" id="services">
  <div class="container">
    <div class="section-head reveal">
      <p class="eyebrow">What we do</p>
      <h2>Everything your business needs to <span class="hl">shine online</span></h2>
      <p class="lead">From your very first website to a full online store — designed to look professional, load fast and bring you customers.</p>
    </div>
    <div class="services">
      <?php
      $services = [
          ['monitor', 'Business websites', 'A polished multi-page website that tells your story, builds trust and turns visitors into paying customers.'],
          ['layout', 'Landing pages', 'One focused, high-converting page for your product, promo or event — live in just days.'],
          ['bag', 'Online stores', 'Sell day and night with product pages, a cart, checkout and online payments.'],
          ['refresh', 'Website redesign', 'Give an outdated website a fresh, modern, mobile-friendly look that matches your brand today.'],
          ['search', 'SEO & Google setup', 'Get found on Google Search and Google Maps with the right foundations from day one.'],
          ['wrench', 'Care & updates', 'Content updates, backups and small changes handled for you, so your site stays fresh and safe.'],
      ];
      foreach ($services as $i => [$ic, $title, $text]): ?>
        <article class="service-card reveal" style="--d:<?= $i * 70 ?>ms">
          <span class="service-icon"><?= icon($ic) ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($text) ?></p>
          <a href="#quote" class="service-link">Get a quote <?= icon('arrow-right') ?></a>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<!-- ============ WHY US ============ -->
<section class="section section-soft why">
  <div class="container why-grid">
    <div class="devices reveal" aria-hidden="true">
      <div class="dev-laptop">
        <div class="dev-screen">
          <div class="dv-nav"><i></i><i></i><i></i></div>
          <div class="dv-hero"><b></b><b></b><span></span></div>
          <div class="dv-row"><span></span><span></span><span></span></div>
        </div>
        <div class="dev-base"></div>
      </div>
      <div class="dev-tablet"><div class="dev-screen"><div class="dv-hero"><b></b><span></span></div><div class="dv-row"><span></span><span></span></div></div></div>
      <div class="dev-phone"><div class="dev-screen"><div class="dv-hero"><b></b><span></span></div><div class="dv-row"><span></span></div></div></div>
      <div class="why-badge"><?= icon('smartphone') ?><span><b>Looks perfect</b><small>on every screen</small></span></div>
    </div>
    <div class="why-copy">
      <div class="reveal">
        <p class="eyebrow">Why choose us</p>
        <h2>Professional quality. <span class="hl">Small-business prices.</span></h2>
        <p class="lead">You don't need a big budget to look like a big brand. We keep things simple, honest and fast — so you can focus on running your business.</p>
      </div>
      <ul class="why-list">
        <?php
        $why = [
            ['coins', 'Affordable, clear pricing', 'Fixed packages with everything listed. You know the price before we start.'],
            ['zap', 'Fast turnaround', 'Most websites go live in 1–2 weeks, not months.'],
            ['smartphone', 'Mobile-first design', 'Most of your customers are on their phones — your site will look great there.'],
            ['headset', 'Real, friendly support', 'Talk to a real person who knows your project, before and after launch.'],
            ['key', 'You own everything', 'Your domain, your content, your website. No lock-in.'],
        ];
        foreach ($why as $i => [$ic, $title, $text]): ?>
          <li class="reveal" style="--d:<?= $i * 70 ?>ms">
            <span class="why-icon"><?= icon($ic) ?></span>
            <div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</section>

<!-- ============ WORK ============ -->
<?php if ($projects): ?>
<section class="section" id="work">
  <div class="container">
    <div class="section-head reveal">
      <p class="eyebrow">Our work</p>
      <h2>Designs that make businesses <span class="hl">look their best</span></h2>
      <p class="lead">A look at the kind of websites we create — every one custom-made for the business behind it.</p>
    </div>
    <?php if (count($categories) > 1): ?>
      <div class="filters reveal" role="group" aria-label="Filter projects" data-filters>
        <button type="button" class="chip is-active" data-filter="*" aria-pressed="true">All</button>
        <?php foreach ($categories as $c): ?>
          <button type="button" class="chip" data-filter="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?></button>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <div class="work-grid" data-work-grid>
      <?php foreach ($projects as $i => $p):
          $theme = THEMES[$p['theme']] ?? THEMES['royal'];
          $img = uploaded_image_url($p['image']);
          $words = explode(' ', $p['title']); ?>
        <article class="work-card reveal" data-category="<?= e($p['category']) ?>" style="--d:<?= ($i % 3) * 80 ?>ms;--ta:<?= e($theme['a']) ?>;--tb:<?= e($theme['b']) ?>;--tc:<?= e($theme['accent']) ?>">
          <div class="work-frame">
            <div class="work-bar"><span class="dots"><i></i><i></i><i></i></span></div>
            <div class="work-shot">
              <?php if ($img): ?>
                <img src="<?= e($img) ?>" alt="Screenshot of the <?= e($p['title']) ?> website" loading="lazy">
              <?php else: ?>
                <div class="wp" aria-hidden="true">
                  <div class="wp-nav"><b><?= e($words[0]) ?></b><span><i></i><i></i><i></i></span></div>
                  <div class="wp-hero">
                    <strong><?= e($p['title']) ?></strong>
                    <span class="wp-line"></span><span class="wp-line wp-short"></span>
                    <span class="wp-btn"></span>
                  </div>
                  <div class="wp-cards"><span></span><span></span><span></span></div>
                </div>
              <?php endif ?>
            </div>
            <?php if ($p['url'] !== ''): ?>
              <a class="work-visit" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">Visit website <?= icon('external') ?></a>
            <?php endif ?>
          </div>
          <div class="work-meta">
            <p class="work-cat"><?= e($p['category']) ?><?php if ($p['is_concept']): ?> <span class="tag-concept">Design concept</span><?php endif ?></p>
            <h3><?= e($p['title']) ?></h3>
            <p><?= e($p['summary']) ?></p>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ============ PROCESS ============ -->
<section class="section section-dark" id="process">
  <div class="dark-glow" aria-hidden="true"></div>
  <div class="container">
    <div class="section-head reveal">
      <p class="eyebrow eyebrow-light">How it works</p>
      <h2>From idea to launch in <span class="hl">4 simple steps</span></h2>
      <p class="lead">No confusing tech talk. We guide you through every step and keep you updated the whole way.</p>
    </div>
    <ol class="steps" data-steps>
      <?php
      $steps = [
          ['bulb', 'Tell us your idea', 'Fill in the short form or message us. We\'ll talk about your business, goals and style — free.', 'Day 1'],
          ['pen', 'See your design', 'We design your homepage first so you can see it, share feedback and request changes early.', 'Days 2–4'],
          ['code', 'We build it', 'We build every page, connect your forms and chat, set up SEO and test it on all devices.', 'Days 5–12'],
          ['rocket', 'Launch & grow', 'Your website goes live on your domain. We show you around and stay here for support.', 'Launch day'],
      ];
      foreach ($steps as $i => [$ic, $title, $text, $when]): ?>
        <li class="step reveal" style="--d:<?= $i * 120 ?>ms">
          <span class="step-num"><?= icon($ic) ?><b><?= $i + 1 ?></b></span>
          <span class="step-when"><?= e($when) ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($text) ?></p>
        </li>
      <?php endforeach ?>
    </ol>
  </div>
</section>

<!-- ============ PRICING ============ -->
<section class="section" id="pricing">
  <div class="container">
    <div class="section-head reveal">
      <p class="eyebrow">Pricing</p>
      <h2>Simple, honest pricing. <span class="hl">No surprises.</span></h2>
      <p class="lead">Pick the package that fits your business today. You can always grow it later.</p>
    </div>
    <div class="pricing count-<?= min(count($packages), 4) ?>">
      <?php foreach ($packages as $i => $p): $featured = (bool) $p['is_featured']; ?>
        <article class="price-card<?= $featured ? ' is-featured' : '' ?> reveal" style="--d:<?= $i * 90 ?>ms">
          <?php if ($featured): ?><span class="ribbon"><?= icon('star') ?> Most popular</span><?php endif ?>
          <h3><?= e($p['name']) ?></h3>
          <p class="price-tagline"><?= e($p['tagline']) ?></p>
          <p class="price">
            <?php if ($p['price'] === null): ?>
              <b class="price-custom">Custom quote</b>
            <?php else: ?>
              <b><?= e(money($p['price'])) ?></b><?php if ($p['price_note'] !== ''): ?><span><?= e($p['price_note']) ?></span><?php endif ?>
            <?php endif ?>
          </p>
          <a class="btn <?= $featured ? 'btn-orange btn-shine' : 'btn-outline' ?> btn-block" href="<?= e(url('', ['package' => $p['id']])) ?>#quote" data-pick-package="<?= (int) $p['id'] ?>">Choose <?= e($p['name']) ?> <?= icon('arrow-right') ?></a>
          <ul class="features">
            <?php foreach (lines($p['features']) as $f): ?>
              <li><?= icon('check') ?><span><?= e($f) ?></span></li>
            <?php endforeach ?>
          </ul>
        </article>
      <?php endforeach ?>
    </div>

    <?php $included = lines(setting('included_in_all')); if ($included): ?>
      <div class="included reveal">
        <p><b>Every package includes</b></p>
        <ul><?php foreach ($included as $item): ?><li><?= icon('check-circle') ?><?= e($item) ?></li><?php endforeach ?></ul>
      </div>
    <?php endif ?>

    <div class="custom-strip reveal">
      <span class="custom-icon"><?= icon('sparkles') ?></span>
      <div>
        <h3>Need something bigger?</h3>
        <p>Booking systems, membership sites, web apps, or a mix of everything — tell us what you need and we'll put together a custom quote.</p>
      </div>
      <a class="btn btn-primary" href="<?= e(url('', ['package' => 'custom'])) ?>#quote" data-pick-package="custom">Ask for a custom quote <?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<?php if ($testimonials): ?>
<section class="section section-soft" id="reviews">
  <div class="container">
    <div class="section-head reveal">
      <p class="eyebrow">Happy clients</p>
      <h2>What our clients <span class="hl">say about us</span></h2>
    </div>
    <div class="reviews">
      <?php foreach ($testimonials as $i => $t): ?>
        <figure class="review reveal" style="--d:<?= ($i % 3) * 80 ?>ms">
          <div class="stars" aria-label="<?= (int) $t['rating'] ?> out of 5 stars">
            <?php for ($s = 1; $s <= 5; $s++): ?><span class="<?= $s <= (int) $t['rating'] ? 'on' : '' ?>"><?= icon('star') ?></span><?php endfor ?>
          </div>
          <blockquote><p>“<?= e($t['quote']) ?>”</p></blockquote>
          <figcaption>
            <span class="avatar"><?= e(initials($t['name'])) ?></span>
            <span><b><?= e($t['name']) ?></b><?php if ($t['role'] !== ''): ?><small><?= e($t['role']) ?></small><?php endif ?></span>
          </figcaption>
        </figure>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ============ FAQ ============ -->
<?php if ($faqs): ?>
<section class="section<?= $testimonials ? '' : ' section-soft' ?>" id="faq">
  <div class="container faq-grid">
    <div class="faq-intro reveal">
      <p class="eyebrow">FAQ</p>
      <h2>Questions? <span class="hl">We've got answers.</span></h2>
      <p class="lead">Can't find what you're looking for? Send us a message and we'll get back to you within <?= e(setting('reply_time')) ?>.</p>
      <a class="btn btn-primary" href="#quote">Ask us anything <?= icon('arrow-right') ?></a>
    </div>
    <div class="faq-list">
      <?php foreach ($faqs as $i => $f): ?>
        <details class="faq reveal" style="--d:<?= $i * 50 ?>ms"<?= $i === 0 ? ' open' : '' ?>>
          <summary><span><?= e($f['question']) ?></span><span class="faq-icon"><?= icon('plus') ?></span></summary>
          <div class="faq-body"><p><?= nl($f['answer']) ?></p></div>
        </details>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ============ REQUEST FORM ============ -->
<section class="section quote-section" id="quote">
  <div class="container">
    <div class="quote-card reveal">
      <aside class="quote-side">
        <div class="quote-side-glow" aria-hidden="true"></div>
        <p class="eyebrow eyebrow-light">Free quote</p>
        <h2>Let's build something <span class="hl">great together.</span></h2>
        <p>Tell us about your business and we'll reply within <?= e(setting('reply_time')) ?> with ideas and a free, no-obligation quote.</p>
        <ol class="next-steps">
          <li><span>1</span><div><b>Send your request</b><small>Takes about 2 minutes</small></div></li>
          <li><span>2</span><div><b>Free consultation</b><small>We learn about your business and goals</small></div></li>
          <li><span>3</span><div><b>Get your quote &amp; plan</b><small>Clear price and timeline — no pressure</small></div></li>
        </ol>
        <?php if ($email !== '' || $phone !== '' || $chat): ?>
          <div class="quote-contact">
            <p>Prefer to talk directly?</p>
            <?php if ($chat): ?><a href="<?= e($chat[1]) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Message us on <?= e($chat[0]) ?></a><?php endif ?>
            <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= icon('phone') ?> <?= e($phone) ?></a><?php endif ?>
            <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= icon('mail') ?> <?= e($email) ?></a><?php endif ?>
          </div>
        <?php endif ?>
        <div class="quote-promise"><?= icon('shield') ?><span><b>No obligation. No spam.</b><small>Just honest advice and a clear price for your business.</small></span></div>
      </aside>

      <form class="quote-form" method="post" action="<?= e(url('request.php')) ?>" novalidate data-quote-form>
        <?= csrf_field() ?>
        <?php if ($errors): ?>
          <div class="form-alert" role="alert" tabindex="-1" data-form-alert><?= icon('alert') ?> Please check the highlighted fields below.</div>
        <?php endif ?>
        <div class="hp" aria-hidden="true">
          <label>Leave this field empty <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="q-name">Your name <em>*</em></label>
            <input id="q-name" name="name" type="text" required maxlength="80" autocomplete="name" value="<?= e($val('name')) ?>"<?= $aria('name') ?>>
            <?= $err('name') ?>
          </div>
          <div class="field">
            <label for="q-email">Email <em>*</em></label>
            <input id="q-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($val('email')) ?>"<?= $aria('email') ?>>
            <?= $err('email') ?>
          </div>
          <div class="field">
            <label for="q-phone">Phone / WhatsApp</label>
            <input id="q-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= e($val('phone')) ?>"<?= $aria('phone') ?>>
            <?= $err('phone') ?>
          </div>
          <div class="field">
            <label for="q-business">Business name</label>
            <input id="q-business" name="business_name" type="text" maxlength="100" autocomplete="organization" value="<?= e($val('business_name')) ?>">
          </div>
          <div class="field">
            <label for="q-type">Type of business</label>
            <select id="q-type" name="business_type">
              <option value="">Choose one…</option>
              <?php foreach (BUSINESS_TYPES as $t): ?><option<?= $val('business_type') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label for="q-package">Package</label>
            <select id="q-package" name="package" data-package-select>
              <option value="">Not sure yet — help me choose</option>
              <?php foreach ($packages as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= $chosenPackage === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?><?= $p['price'] !== null ? ' — ' . e(money($p['price'])) : '' ?></option>
              <?php endforeach ?>
              <option value="custom"<?= $chosenPackage === 'custom' ? ' selected' : '' ?>>Custom project</option>
            </select>
          </div>
          <div class="field">
            <label for="q-budget">Budget (<?= e(setting('currency')) ?>)</label>
            <select id="q-budget" name="budget">
              <option value="">Choose a range…</option>
              <?php foreach (BUDGETS as $b): ?><option<?= $val('budget') === $b ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label for="q-timeline">When do you need it?</label>
            <select id="q-timeline" name="timeline">
              <option value="">Choose one…</option>
              <?php foreach (TIMELINES as $t): ?><option<?= $val('timeline') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="field field-full">
            <label for="q-site">Current website or Facebook page <span class="opt">(if any)</span></label>
            <input id="q-site" name="website_url" type="text" inputmode="url" maxlength="250" placeholder="e.g. facebook.com/yourbusiness" value="<?= e($val('website_url')) ?>"<?= $aria('website_url') ?>>
            <?= $err('website_url') ?>
          </div>
          <div class="field field-full">
            <label for="q-message">Tell us about your idea <em>*</em></label>
            <textarea id="q-message" name="message" rows="5" required maxlength="3000" placeholder="What does your business do? What should your website help you with? Any websites you like?"<?= $aria('message') ?>><?= e($val('message')) ?></textarea>
            <?= $err('message') ?>
          </div>
        </div>
        <button class="btn btn-orange btn-lg btn-block btn-shine" type="submit" data-submit>
          <span>Send my request</span> <?= icon('send') ?>
        </button>
        <p class="form-note"><?= icon('lock') ?> We only use your details to reply to your request. <a href="<?= e(url('privacy.php')) ?>">Privacy notice</a></p>
      </form>
    </div>
  </div>
</section>

<?php
$org = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'ProfessionalService',
    'name' => setting('brand_name'),
    'description' => setting('tagline'),
    'url' => app_url() . '/',
    'email' => $email ?: null,
    'telephone' => $phone ?: null,
    'areaServed' => setting('location') ?: null,
]);
?>
<script type="application/ld+json"><?= json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php page_end();
