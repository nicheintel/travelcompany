<?php
/* Admin → Site settings: supplier keys, markup and email/payment settings, stored in the database. */
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();

const SECRET_FIELDS = [
    'duffel_access_token' => ['Duffel access token', 'Flights. Starts with duffel_test_ (test) or duffel_live_ (real airlines).'],
    'liteapi_key' => ['LiteAPI key', 'Hotels. Use the sandbox key while testing.'],
    'resend_api_key' => ['Resend API key', 'Optional. Sends real emails; without it emails go to storage/emails.log.php.'],
    'paypal_client_id' => ['PayPal Client ID', 'Online payments with PayPal or card. From developer.paypal.com → Apps & Credentials.'],
    'paypal_secret' => ['PayPal Secret', 'The Secret shown next to the Client ID (Sandbox or Live, matching the mode below).'],
    'paypal_webhook_id' => ['PayPal Webhook ID', 'Optional, recommended on a live site. From your PayPal app → Webhooks (URL: your-site/paypal-webhook.php).'],
    'stripe_secret_key' => ['Stripe secret key', 'Optional alternative to PayPal (not available to Philippine-registered businesses).'],
    'stripe_webhook_secret' => ['Stripe webhook secret', 'Optional. whsec_… from your Stripe webhook.'],
];
const PERCENT_FIELDS = [
    'flight_markup_rate' => ['Flight markup', 300],
    'hotel_markup_rate' => ['Hotel markup', 300],
    'member_discount_rate' => ['Member discount', 90],
];

function save_setting(string $name, string $value): void
{
    db_run('INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', [$name, $value, now_utc()]);
}

/** Shows only the start and last 4 characters of a saved key. */
function masked(string $v): string
{
    if ($v === '') return 'Not set';
    $prefix = preg_match('/^(duffel_(test|live)_|sk_(test|live)_|whsec_|re_)/', $v, $m) ? $m[1] : '';
    return 'Saved: ' . $prefix . '••••' . substr($v, -4);
}

$errors = [];
if (is_post()) {
    verify_csrf();
    foreach (SECRET_FIELDS as $name => $_) {
        if (config_fixed($name)) continue;
        $value = trim((string) ($_POST[$name] ?? ''));
        if (!empty($_POST["clear_$name"])) save_setting($name, '');
        elseif ($value !== '') {
            if (strlen($value) > 300 || preg_match('/\s/', $value)) $errors[$name] = 'That doesn\'t look like a valid key (no spaces).';
            else save_setting($name, $value);
        }
    }
    foreach (PERCENT_FIELDS as $name => [$label, $max]) {
        if (config_fixed($name)) continue;
        $raw = trim((string) ($_POST[$name] ?? ''));
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) $errors[$name] = "Enter a number from 0 to $max.";
        else save_setting($name, (string) round((float) $raw / 100, 4));
    }
    if (!config_fixed('admin_emails')) {
        $emails = array_values(array_filter(array_map('normalize_email', explode(',', (string) ($_POST['admin_emails'] ?? '')))));
        $bad = array_filter($emails, fn($e) => !valid_email($e));
        if ($bad) $errors['admin_emails'] = 'Not a valid email: ' . implode(', ', $bad);
        else save_setting('admin_emails', implode(', ', $emails));
    }
    if (!config_fixed('app_url')) {
        $site = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $parts = parse_url($site);
        if ($site !== '' && (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']))) {
            $errors['app_url'] = 'Enter the address like https://www.yourdomain.com';
        } else {
            save_setting('app_url', $site);
        }
    }
    if (!config_fixed('paypal_mode')) save_setting('paypal_mode', ($_POST['paypal_mode'] ?? '') === 'live' ? 'live' : 'sandbox');
    if (!config_fixed('email_from')) save_setting('email_from', mb_substr(trim((string) ($_POST['email_from'] ?? '')), 0, 200));
    if (!$errors) {
        flash('Settings saved. Check the Diagnostics tab to test your supplier connections.');
        redirect(url('admin/settings.php'));
    }
    config('__reset');
}

$title = 'Site settings';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('settings');
$input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100';
$fixedNote = '<p class="mt-1 text-xs text-amber-700">Set in config.local.php or an environment variable — change it there.</p>';
?>
<form method="post" class="max-w-3xl space-y-6" autocomplete="off">
  <?= csrf_field() ?>
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Site settings</h1>
    <p class="text-slate-600">Saved securely in your database. Keys are never shown in full after saving.</p>
  </div>
  <?php if ($errors): ?><?= alert_box('Please fix the highlighted fields.') ?><?php endif; ?>

  <section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Supplier keys</h2>
    <p class="mt-1 text-sm text-slate-500">Paste a new key to replace the saved one. Leave empty to keep it.</p>
    <div class="mt-5 space-y-5">
      <?php foreach (SECRET_FIELDS as $name => [$label, $help]): $fixed = config_fixed($name); ?>
        <div>
          <label for="s_<?= $name ?>" class="block text-sm font-medium text-slate-700"><?= e($label) ?> <span class="font-normal text-slate-400">· <?= e(masked((string) config($name))) ?></span></label>
          <input id="s_<?= $name ?>" name="<?= $name ?>" type="password" autocomplete="new-password" spellcheck="false" placeholder="Paste key here" class="<?= $input ?> mt-1 font-mono"<?= $fixed ? ' disabled' : '' ?>>
          <?php if (isset($errors[$name])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors[$name]) ?></p><?php endif; ?>
          <p class="mt-1 text-xs text-slate-500"><?= e($help) ?></p>
          <?php if ($fixed): ?><?= $fixedNote ?><?php elseif ((string) config($name) !== ''): ?>
            <label class="mt-1 inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="clear_<?= $name ?>" value="1" class="accent-red-600"> Remove saved key</label>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div>
        <label for="s_paypal_mode" class="block text-sm font-medium text-slate-700">PayPal mode</label>
        <select id="s_paypal_mode" name="paypal_mode" class="<?= $input ?> mt-1 sm:w-72"<?= config_fixed('paypal_mode') ? ' disabled' : '' ?>>
          <option value="sandbox"<?= config('paypal_mode') !== 'live' ? ' selected' : '' ?>>Sandbox — testing, no real money</option>
          <option value="live"<?= config('paypal_mode') === 'live' ? ' selected' : '' ?>>Live — real payments</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">Use Sandbox keys with Sandbox mode, and Live keys with Live mode.</p>
      </div>
    </div>
  </section>

  <section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Pricing</h2>
    <p class="mt-1 text-sm text-slate-500">Markup is added to supplier prices; the member discount then comes off the total.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      <?php foreach (PERCENT_FIELDS as $name => [$label, $max]): $fixed = config_fixed($name); ?>
        <div>
          <label for="s_<?= $name ?>" class="block text-sm font-medium text-slate-700"><?= e($label) ?></label>
          <div class="relative mt-1">
            <input id="s_<?= $name ?>" name="<?= $name ?>" type="number" min="0" max="<?= $max ?>" step="0.1" value="<?= e(is_post() ? (string) ($_POST[$name] ?? '') : (string) round((float) config($name) * 100, 2)) ?>" class="<?= $input ?> pr-8"<?= $fixed ? ' disabled' : '' ?>>
            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">%</span>
          </div>
          <?php if (isset($errors[$name])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors[$name]) ?></p><?php endif; ?>
          <?php if ($fixed): ?><?= $fixedNote ?><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Admins &amp; email</h2>
    <div class="mt-5 space-y-4">
      <div>
        <label for="s_app_url" class="block text-sm font-medium text-slate-700">Site address</label>
        <input id="s_app_url" name="app_url" type="url" value="<?= e(is_post() ? (string) ($_POST['app_url'] ?? '') : (string) config('app_url')) ?>" placeholder="https://www.yourdomain.com" class="<?= $input ?> mt-1"<?= config_fixed('app_url') ? ' disabled' : '' ?>>
        <?php if (isset($errors['app_url'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['app_url']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-500">Your website's address, used for links in emails. Leave empty on your own computer. On a live site it's filled in the first time you open this dashboard there — use https:// once SSL is on.</p>
        <?php if (config_fixed('app_url')): ?><?= $fixedNote ?><?php endif; ?>
      </div>
      <div>
        <label for="s_admin_emails" class="block text-sm font-medium text-slate-700">Admin emails <span class="font-normal text-slate-400">· comma-separated</span></label>
        <input id="s_admin_emails" name="admin_emails" type="text" value="<?= e(is_post() ? (string) ($_POST['admin_emails'] ?? '') : (string) config('admin_emails')) ?>" placeholder="you@example.com" class="<?= $input ?> mt-1"<?= config_fixed('admin_emails') ? ' disabled' : '' ?>>
        <?php if (isset($errors['admin_emails'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['admin_emails']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-500">Always admins, in addition to people you make admin on the Users tab.</p>
        <?php if (config_fixed('admin_emails')): ?><?= $fixedNote ?><?php endif; ?>
      </div>
      <div>
        <label for="s_email_from" class="block text-sm font-medium text-slate-700">Send emails from</label>
        <input id="s_email_from" name="email_from" type="text" value="<?= e((string) config('email_from')) ?>" placeholder="TravelCompany &lt;hello@yourdomain.com&gt;" class="<?= $input ?> mt-1"<?= config_fixed('email_from') ? ' disabled' : '' ?>>
        <p class="mt-1 text-xs text-slate-500">Used with Resend; the domain must be verified in Resend.</p>
      </div>
    </div>
  </section>

  <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">Save settings</button>
</form>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
