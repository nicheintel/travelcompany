<?php
/* Admin-only: checks the supplier connections and settings, showing the exact result. */
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$testResult = null;
if (is_post()) {
    verify_csrf();
    if (post('action') === 'test_email') {
        $mail = simple_email('Test email from ' . config('site_name'), 'Your emails are working!', explode(' ', $admin['name'])[0], [
            'This is a test from Admin → Diagnostics. Booking confirmations, payment links and password resets will be sent the same way.',
        ], [], app_url_or_local() . '/admin/diagnostics.php', 'Open Diagnostics');
        $error = deliver_email($admin['email'], $mail);
        $testResult = $error === null
            ? [true, email_method() === 'log' ? 'No email service is set up, so the test email was saved to storage/emails.log.php instead of being sent.' : "Test email sent to {$admin['email']} — check your inbox (and spam folder)."]
            : [false, "The test email couldn't be sent: $error"];
    }
}

/** Runs a small request and reports status, time and the supplier's own error message. */
function probe(callable $request): array
{
    $start = microtime(true);
    try {
        $res = $request();
        $ms = (int) round((microtime(true) - $start) * 1000);
        $ok = $res['status'] >= 200 && $res['status'] < 300;
        $err = $res['json']['errors'][0]['message'] ?? $res['json']['error']['message'] ?? (is_string($res['json']['error'] ?? null) ? $res['json']['error'] : null);
        return ['ok' => $ok, 'text' => "HTTP {$res['status']} in {$ms} ms" . ($err ? " — $err" : ''), 'json' => $res['json']];
    } catch (Throwable $e) {
        return ['ok' => false, 'text' => $e->getMessage(), 'json' => null];
    }
}

$checks = [];
$checks[] = ['PHP version', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '8.1', '>=') ? '' : ' — PHP 8.1 or newer is required')];
$checks[] = ['PHP curl extension', function_exists('curl_init'), function_exists('curl_init') ? 'On' : 'Off — enable extension=curl in php.ini and restart Apache'];
$checks[] = ['HTTPS certificates', true, ini_get('curl.cainfo') ? 'From php.ini (curl.cainfo)' : (ca_bundle() ? 'Using ' . ca_bundle() : 'System default')];
$checks[] = ['Database', true, 'Connected to ' . config('db_name')];

if (duffel_enabled()) {
    $mode = str_starts_with((string) config('duffel_access_token'), 'duffel_test_') ? 'TEST token — only "Duffel Airways" test flights' : 'LIVE token — real airlines';
    $r = probe(fn() => http_json('GET', rtrim((string) config('duffel_api_base'), '/') . '/air/airlines?limit=1', ['Authorization: Bearer ' . config('duffel_access_token'), 'Duffel-Version: v2'], null, 20));
    if (flights_on_hold()) {
        $checks[] = ['Duffel (flights)', false, 'TEST token while PayPal is LIVE — flights are hidden from customers ("coming soon") until you add a duffel_live_ token'];
    } else {
        $checks[] = ['Duffel (flights)', $r['ok'], $mode . ' · ' . $r['text']];
    }
} else {
    $checks[] = ['Duffel (flights)', false, 'No Duffel key yet — add it on the Site settings tab. Flight search is off.'];
}
if (liteapi_enabled()) {
    $r = probe(fn() => http_json('GET', rtrim((string) config('liteapi_api_base'), '/') . '/data/countries', ['X-API-Key: ' . config('liteapi_key')], null, 20));
    $liteMode = str_starts_with((string) config('liteapi_key'), 'sand_') ? 'SANDBOX key — test hotels, no real bookings' : 'PRODUCTION key — real hotels';
    $checks[] = ['LiteAPI (hotels)', $r['ok'], $liteMode . ' · ' . $r['text']];
} else {
    $checks[] = ['LiteAPI (hotels)', false, 'No LiteAPI key yet — add it on the Site settings tab. Hotel search is off.'];
}
$checks[] = ['Emails', email_method() !== 'log', match (email_method()) {
    'resend' => 'Sent with Resend',
    'smtp' => 'Sent from ' . config('smtp_user') . ' (' . config('smtp_host') . ')',
    default => 'NOT SENT — add your mailbox on Site settings → Sending emails. Until then emails are saved to storage/emails.log.php',
}];

// Security
$site = (string) config('app_url');
$local = is_local_request();
$checks[] = ['Site address', $site !== '' || $local, $site !== '' ? $site . ' — used for links in emails' : ($local ? 'Detected automatically on this computer' : 'Not set — set it on Site settings so emails contain working links')];
$checks[] = ['HTTPS (secure connection)', $local || request_is_https(), $local ? 'Not needed on your own computer' : (request_is_https() ? 'On' : 'OFF — turn on SSL for your domain at your hosting company, then use an https:// site address')];
$checks[] = ['Database password', $local || (string) config('db_pass') !== '', (string) config('db_pass') !== '' ? 'Set' : ($local ? 'Empty (fine for XAMPP on your own computer)' : 'EMPTY — use a database user with a strong password on a live server')];
if (paypal_enabled()) {
    $r = probe(function () {
        paypal_token();
        return ['status' => 200, 'json' => []];
    });
    $checks[] = ['Online payments', $r['ok'], 'PayPal · ' . (paypal_live() ? 'LIVE — real money' : 'Sandbox — test payments only') . ' · ' . ($r['ok'] ? 'signed in to PayPal' : $r['text'])];
} else {
    $checks[] = ['Online payments', true, stripe_enabled() ? 'Stripe on' : 'Off — pay later only (add PayPal on Site settings)'];
}
if ($pe = last_payment_error()) {
    $checks[] = ['Last payment problem', false, gmdate('M j, g:i A', strtotime($pe['at'] . ' UTC')) . ' UTC' . " · trip {$pe['ref']} · {$pe['message']}"];
}
$checks[] = ['Demo mode', !demo_mode(), demo_mode() ? 'ON — made-up flights/hotels are shown. Turn off for customers!' : 'Off'];
$checks[] = ['Markup', true, 'Flights +' . round(markup_rate('flight') * 100) . '%, hotels +' . round(markup_rate('hotel') * 100) . '%, member discount ' . round(member_discount_rate() * 100) . '%'];

$title = 'Diagnostics';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('diagnostics');
?>
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Diagnostics</h1>
    <p class="text-slate-600">Checks your settings and the connections to your suppliers. Keys are never shown here.</p>
  </div>
  <?php if ($testResult): ?>
    <div class="rounded-xl p-4 text-sm font-medium <?= $testResult[0] ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-red-50 text-red-700 ring-1 ring-red-200' ?>" role="status"><?= e($testResult[1]) ?></div>
  <?php endif; ?>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($checks as [$name, $ok, $detail]): ?>
          <tr>
            <td class="w-10 px-4 py-3"><span class="grid h-6 w-6 place-items-center rounded-full text-xs font-bold text-white <?= $ok ? 'bg-emerald-500' : 'bg-red-500' ?>"><?= $ok ? '✓' : '!' ?></span></td>
            <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900"><?= e($name) ?></td>
            <td class="px-4 py-3 text-slate-700"><?= e($detail) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <a href="<?= e(url('admin/flight-test.php')) ?>" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">LiteAPI flight search test</a>
    <span class="text-sm text-slate-600">Checks whether LiteAPI flights work on your account (no booking).</span>
  </div>
  <form method="post" class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4"><?= csrf_field() ?><input type="hidden" name="action" value="test_email">
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Send test email</button>
    <span class="text-sm text-slate-600">Sends a test email to <?= e($admin['email']) ?>.</span>
  </form>
  <p class="text-sm text-slate-500">After changing Site settings, refresh this page. Then try a real search:
    <a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('flights.php', ['from' => 'JFK', 'to' => 'LHR'])) ?>">New York → London flights</a> ·
    <a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('hotels.php', ['to' => 'BKK'])) ?>">Hotels in Bangkok</a></p>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
