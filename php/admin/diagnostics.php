<?php
/* Admin-only: checks the supplier connections and settings, showing the exact result. */
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

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
    $checks[] = ['Duffel (flights)', $r['ok'], $mode . ' · ' . $r['text']];
} else {
    $checks[] = ['Duffel (flights)', false, 'No Duffel key yet — add it on the Site settings tab. Flight search is off.'];
}
if (liteapi_enabled()) {
    $r = probe(fn() => http_json('GET', rtrim((string) config('liteapi_api_base'), '/') . '/data/countries', ['X-API-Key: ' . config('liteapi_key')], null, 20));
    $checks[] = ['LiteAPI (hotels)', $r['ok'], $r['text']];
} else {
    $checks[] = ['LiteAPI (hotels)', false, 'No LiteAPI key yet — add it on the Site settings tab. Hotel search is off.'];
}
$checks[] = ['Emails', true, config('resend_api_key') ? 'Sent with Resend' : 'Not sent — written to storage/emails.log'];
if (paypal_enabled()) {
    $r = probe(function () {
        paypal_token();
        return ['status' => 200, 'json' => []];
    });
    $checks[] = ['Online payments', $r['ok'], 'PayPal · ' . (paypal_live() ? 'LIVE — real money' : 'Sandbox — test payments only') . ' · ' . ($r['ok'] ? 'signed in to PayPal' : $r['text'])];
} else {
    $checks[] = ['Online payments', true, stripe_enabled() ? 'Stripe on' : 'Off — pay later only (add PayPal on Site settings)'];
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
  <p class="text-sm text-slate-500">After changing Site settings, refresh this page. Then try a real search:
    <a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('flights.php', ['from' => 'JFK', 'to' => 'LHR'])) ?>">New York → London flights</a> ·
    <a class="font-semibold text-brand-700 hover:underline" href="<?= e(url('hotels.php', ['to' => 'BKK'])) ?>">Hotels in Bangkok</a></p>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
