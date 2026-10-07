<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

/** The settings on this page, in groups. type: text|textarea|lines|email|url|phone|select */
$groups = [
    'brand' => ['Brand & headline', 'sparkles', 'Your business name and the first thing visitors read.', [
        'brand_name' => ['Brand name', 'text', 60, 'Shown in the logo, page titles and footer.'],
        'tagline' => ['Tagline', 'text', 120, 'A short line about what you do.'],
        'hero_title' => ['Big headline', 'text', 120, 'Put *stars* around words to color them orange, e.g. Let\'s turn your ideas into a *website*.'],
        'hero_text' => ['Text under the headline', 'textarea', 400, ''],
    ]],
    'contact' => ['Contact & social', 'phone', 'Empty fields are hidden on the website.', [
        'contact_email' => ['Email', 'email', 190, 'Where clients can email you.'],
        'contact_phone' => ['Phone', 'phone', 30, 'e.g. +63 917 123 4567'],
        'whatsapp' => ['WhatsApp number', 'phone', 30, 'With country code, e.g. +63 917 123 4567. Adds a "Chat with us" button.'],
        'messenger_url' => ['Messenger link', 'url', 250, 'e.g. https://m.me/yourpage (used for the chat button when there\'s no WhatsApp).'],
        'facebook_url' => ['Facebook page', 'url', 250, ''],
        'instagram_url' => ['Instagram', 'url', 250, ''],
        'tiktok_url' => ['TikTok', 'url', 250, ''],
        'location' => ['Location / service area', 'text', 120, 'e.g. Based in Cebu · serving clients nationwide'],
        'business_hours' => ['Business hours', 'text', 80, ''],
        'reply_time' => ['How fast you reply', 'text', 30, 'Used in sentences like "we\'ll reply within 24 hours".'],
    ]],
    'pricing' => ['Pricing', 'tag', 'Applies to all prices on the website and dashboard.', [
        'currency' => ['Currency symbol', 'text', 5, 'e.g. ₱, $, €, £'],
        'included_in_all' => ['"Every package includes" list', 'lines', 1000, 'One per line. Shown under the pricing packages. Leave empty to hide.'],
    ]],
    'system' => ['Dashboard', 'clock', '', [
        'timezone' => ['Time zone', 'select', 64, 'Times on the dashboard are shown in this time zone.'],
    ]],
];

$errors = [];
$values = [];
foreach ($groups as $g) foreach ($g[3] as $key => $f) $values[$key] = setting($key);

if (is_post()) {
    verify_csrf();
    foreach ($groups as $g) {
        foreach ($g[3] as $key => [$label, $type, $max]) {
            $v = post($key);
            if ($type !== 'textarea' && $type !== 'lines') $v = str_replace("\n", ' ', $v);
            if ($type === 'lines') $v = implode("\n", lines($v));
            if (mb_strlen($v) > $max) $errors[$key] = "$label must be $max characters or fewer.";
            elseif ($type === 'email' && $v !== '' && !valid_email($v)) $errors[$key] = 'Please enter a valid email address.';
            elseif ($type === 'phone' && $v !== '' && !preg_match('/^[0-9+()\-.\s]{6,30}$/', $v)) $errors[$key] = 'Use digits, spaces, + and dashes only.';
            elseif ($type === 'url') {
                $u = clean_url($v);
                if ($u === null) $errors[$key] = 'Enter a full web address, e.g. https://facebook.com/yourpage';
                else $v = $u;
            } elseif ($type === 'select' && !in_array($v, DateTimeZone::listIdentifiers(), true)) {
                $errors[$key] = 'Choose a time zone from the list.';
            }
            $values[$key] = $v;
        }
    }
    foreach (['brand_name', 'currency', 'hero_title', 'reply_time'] as $required) {
        if ($values[$required] === '' && !isset($errors[$required])) $errors[$required] = 'This one can\'t be empty.';
    }
    if (!$errors) {
        foreach ($values as $key => $v) save_setting($key, $v);
        flash('Settings saved. Your website is updated.');
        redirect(url('admin/settings.php'));
    }
}

$zones = DateTimeZone::listIdentifiers();
admin_start('Site settings', 'settings', '<a class="btn btn-ghost" href="' . e(url()) . '" target="_blank" rel="noopener">' . icon('external') . ' View website</a>');
?>
<form method="post" class="settings" novalidate>
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="alert alert-error"><?= icon('alert') ?><span>Some settings need fixing — see the highlighted fields.</span></div><?php endif ?>
  <?php foreach ($groups as $gid => [$title, $ic, $intro, $fields]): ?>
    <section class="card settings-group" id="<?= e($gid) ?>">
      <div class="sg-head">
        <span class="sg-icon"><?= icon($ic) ?></span>
        <div><h2><?= e($title) ?></h2><?php if ($intro): ?><p class="muted"><?= e($intro) ?></p><?php endif ?></div>
      </div>
      <div class="form-grid">
        <?php foreach ($fields as $key => [$label, $type, $max, $help]):
            $id = 's-' . $key; $err = $errors[$key] ?? null; $wide = in_array($type, ['textarea', 'lines'], true) || in_array($key, ['hero_title', 'hero_text'], true); ?>
          <div class="field<?= $wide ? ' field-full' : '' ?>">
            <label for="<?= $id ?>"><?= e($label) ?></label>
            <?php if ($type === 'textarea' || $type === 'lines'): ?>
              <textarea id="<?= $id ?>" name="<?= e($key) ?>" rows="<?= $type === 'lines' ? 6 : 3 ?>" maxlength="<?= $max ?>"<?= $err ? ' aria-invalid="true"' : '' ?>><?= e($values[$key]) ?></textarea>
            <?php elseif ($type === 'select'): ?>
              <select id="<?= $id ?>" name="<?= e($key) ?>">
                <?php foreach ($zones as $z): ?><option<?= $values[$key] === $z ? ' selected' : '' ?>><?= e($z) ?></option><?php endforeach ?>
              </select>
            <?php else: ?>
              <input id="<?= $id ?>" name="<?= e($key) ?>" type="<?= $type === 'email' ? 'email' : ($type === 'phone' ? 'tel' : 'text') ?>"<?= $type === 'url' ? ' inputmode="url" placeholder="https://"' : '' ?> maxlength="<?= $max ?>" value="<?= e($values[$key]) ?>"<?= $err ? ' aria-invalid="true"' : '' ?>>
            <?php endif ?>
            <?php if ($help): ?><small class="help"><?= e($help) ?></small><?php endif ?>
            <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    </section>
  <?php endforeach ?>
  <div class="save-bar">
    <span class="muted">Changes go live on your website as soon as you save.</span>
    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save settings</button>
  </div>
</form>
<?php admin_end();
