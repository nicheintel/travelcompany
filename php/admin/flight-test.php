<?php
/* Admin-only: runs one LiteAPI flight search (no booking, no charge) and shows the raw answer. */
require dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();
$date = add_days(today(), 30);
$body = (string) ($_POST['body'] ?? json_encode([
    'legs' => [['origin' => 'MNL', 'destination' => 'CEB', 'date' => $date]],
    'adults' => 1,
    'currency' => 'USD',
], JSON_PRETTY_PRINT));
$result = null;
if (is_post()) {
    verify_csrf();
    $json = json_decode($body, true);
    if (!is_array($json)) {
        $result = ['status' => 'Invalid JSON', 'text' => 'The request must be valid JSON.'];
    } elseif (!liteapi_enabled()) {
        $result = ['status' => 'No key', 'text' => 'Add your LiteAPI key on Site settings first.'];
    } else {
        try {
            $res = http_json('POST', rtrim((string) config('liteapi_api_base'), '/') . '/flights/rates', ['X-API-Key: ' . config('liteapi_key')], $json, 60);
            $pretty = json_encode($res['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '(no JSON in the answer)';
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
?>
<div class="max-w-4xl space-y-6">
  <div>
    <nav class="mb-2 text-sm text-slate-500"><a href="<?= e(url('admin/diagnostics.php')) ?>" class="hover:text-brand-700">← Diagnostics</a></nav>
    <h1 class="text-2xl font-bold text-slate-900">LiteAPI flight search test</h1>
    <p class="text-slate-600">Runs one flight search with your LiteAPI key and shows LiteAPI's raw answer. Nothing is booked or charged. Your key is never shown.</p>
  </div>
  <form method="post" class="space-y-3 rounded-xl border border-slate-200 bg-white p-5"><?= csrf_field() ?>
    <label class="block text-sm font-medium text-slate-700">Search request
      <textarea name="body" rows="8" spellcheck="false" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"><?= e($body) ?></textarea></label>
    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Run test search</button>
  </form>
  <?php if ($result): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="font-semibold text-slate-900">Answer: <?= e($result['status']) ?></h2>
      <p class="mt-1 text-sm text-slate-500">Select all the text below (click inside, then Ctrl+A, Ctrl+C) and paste it to your developer.</p>
      <textarea readonly rows="24" class="mt-3 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs"><?= e($result['text']) ?></textarea>
    </section>
  <?php endif; ?>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
