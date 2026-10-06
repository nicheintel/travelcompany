<?php
/* Admin-only: runs one LiteAPI flight search (no booking, no charge), lists every airline in the answer
   and whether customers see it, and shows the raw answer. */
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$f = [
    'from' => strtoupper(trim((string) ($_POST['from'] ?? 'MNL'))),
    'to' => strtoupper(trim((string) ($_POST['to'] ?? 'CEB'))),
    'depart' => (string) ($_POST['depart'] ?? add_days(today(), 30)),
    'return' => (string) ($_POST['return'] ?? ''),
    'adults' => (int) ($_POST['adults'] ?? 1) ?: 1,
];
$result = null;
$airlines = [];

/** Shrinks a big answer: lists keep their first item plus a count, long text is shortened. */
function summarize(mixed $v, int $depth = 0): mixed
{
    if (!is_array($v)) return is_string($v) && strlen($v) > 40 ? substr($v, 0, 24) . '…' : $v;
    if ($depth > 14) return '…';
    if (array_is_list($v)) {
        return $v ? [summarize($v[0], $depth + 1), '(' . count($v) . ' items in total)'] : [];
    }
    $out = [];
    foreach ($v as $k => $item) $out[$k] = summarize($item, $depth + 1);
    return $out;
}

if (is_post()) {
    verify_csrf();
    $p = parse_flight_params($f + ['trip' => $f['return'] === '' ? 'oneway' : 'roundtrip']);
    $s = $p['search'];
    if (!$s) {
        $result = ['status' => 'Check the search', 'text' => 'Use two different 3-letter airport codes, e.g. MNL and CEB.'];
    } elseif (!liteapi_enabled()) {
        $result = ['status' => 'No key', 'text' => 'Add your LiteAPI key on Site settings first.'];
    } else {
        try {
            $res = http_json('POST', rtrim((string) config('liteapi_api_base'), '/') . '/flights/rates', ['X-API-Key: ' . config('liteapi_key')], liteapi_flight_body($s), 60);
            // Every airline in the answer: how many offers, the cheapest price customers would see, and why any are hidden.
            foreach (liteapi_flight_review(liteapi_journeys($res['json'] ?? []), $s) as [$offer, $why, $name]) {
                $a = &$airlines[$name];
                $a ??= ['offers' => 0, 'shown' => 0, 'from' => null, 'hidden' => []];
                $a['offers']++;
                if ($offer) {
                    $a['shown']++;
                    $a['from'] = min($a['from'] ?? PHP_INT_MAX, $offer['total']);
                } else {
                    $a['hidden'][$why] = ($a['hidden'][$why] ?? 0) + 1;
                }
                unset($a);
            }
            ksort($airlines);
            $data = !empty($_POST['summary']) ? summarize($res['json']) : $res['json'];
            $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '(no JSON in the answer)';
            $result = ['status' => 'HTTP ' . $res['status'], 'text' => mb_substr($pretty, 0, 20000) . (mb_strlen($pretty) > 20000 ? "\n… (cut at 20,000 characters)" : '')];
        } catch (Throwable $e) {
            $result = ['status' => 'Error', 'text' => $e->getMessage()];
        }
    }
}
$title = 'Flight API test';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('diagnostics');
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm';
?>
<div class="max-w-4xl space-y-6">
  <div>
    <nav class="mb-2 text-sm text-slate-500"><a href="<?= e(url('admin/diagnostics.php')) ?>" class="hover:text-brand-700">← Diagnostics</a></nav>
    <h1 class="text-2xl font-bold text-slate-900">LiteAPI flight search test</h1>
    <p class="text-slate-600">Runs one flight search with your LiteAPI key and shows every airline LiteAPI sends back, and whether customers see it. Nothing is booked or charged. Your key is never shown.</p>
  </div>
  <form method="post" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5"><?= csrf_field() ?>
    <div class="grid gap-3 sm:grid-cols-5">
      <label class="block text-sm font-medium text-slate-700">From (airport code)<input name="from" value="<?= e($f['from']) ?>" maxlength="3" class="<?= $input ?> uppercase"></label>
      <label class="block text-sm font-medium text-slate-700">To (airport code)<input name="to" value="<?= e($f['to']) ?>" maxlength="3" class="<?= $input ?> uppercase"></label>
      <label class="block text-sm font-medium text-slate-700">Departure<input type="date" name="depart" value="<?= e($f['depart']) ?>" class="<?= $input ?>"></label>
      <label class="block text-sm font-medium text-slate-700">Return <span class="font-normal text-slate-500">(optional)</span><input type="date" name="return" value="<?= e($f['return']) ?>" class="<?= $input ?>"></label>
      <label class="block text-sm font-medium text-slate-700">Adults<input type="number" name="adults" min="1" max="9" value="<?= (int) $f['adults'] ?>" class="<?= $input ?>"></label>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="summary" value="1"<?= !is_post() || !empty($_POST['summary']) ? ' checked' : '' ?> class="h-4 w-4 accent-brand-600"> Short raw answer (one example of each item)</label>
    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Run test search</button>
  </form>
  <?php if ($result): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="font-semibold text-slate-900">Airlines in LiteAPI's answer</h2>
      <?php if (!$airlines): ?>
        <p class="mt-2 text-sm text-slate-600">No flights came back for this search (<?= e($result['status']) ?>).</p>
      <?php else: ?>
        <p class="mt-1 text-sm text-slate-500">An airline that isn't in this list wasn't sent by LiteAPI for this route and date, so the website can't show it.</p>
        <table class="mt-3 w-full text-left text-sm">
          <thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-2">Airline</th><th class="py-2">Offers</th><th class="py-2">Customers see</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($airlines as $name => $a): ?>
              <tr>
                <td class="py-2 font-semibold text-slate-900"><?= e($name) ?></td>
                <td class="py-2 text-slate-700"><?= $a['offers'] ?></td>
                <td class="py-2 text-slate-700">
                  <?php if ($a['shown']): ?><span class="font-medium text-emerald-700"><?= $a['shown'] ?> shown, from <?= e(money($a['from'])) ?></span><?php endif; ?>
                  <?php foreach ($a['hidden'] as $why => $n): ?><span class="block text-amber-700"><?= $n ?> hidden: <?= e($why) ?></span><?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="font-semibold text-slate-900">Raw answer: <?= e($result['status']) ?></h2>
      <p class="mt-1 text-sm text-slate-500">Select all the text below (click inside, then Ctrl+A, Ctrl+C) and paste it to your developer.</p>
      <textarea readonly rows="16" class="mt-3 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs"><?= e($result['text']) ?></textarea>
    </section>
  <?php endif; ?>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
