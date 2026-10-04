<?php
/*
 * PayPal → us (optional but recommended on a live site): confirms payments even if the
 * customer closes the browser before returning. In developer.paypal.com → your app →
 * Webhooks, add https://YOUR-SITE/paypal-webhook.php with the events
 * CHECKOUT.ORDER.APPROVED and PAYMENT.CAPTURE.COMPLETED, then paste the Webhook ID
 * in Admin → Site settings.
 */
require __DIR__ . '/includes/bootstrap.php';

if (!paypal_enabled() || (string) config('paypal_webhook_id') === '') json_out(['error' => 'PayPal webhook not configured'], 500);
$body = (string) file_get_contents('php://input');
$event = json_decode($body, true);
if (!is_array($event)) json_out(['error' => 'Bad request'], 400);

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_PAYPAL_')) $headers[str_replace('_', '-', substr($k, 5))] = $v;
}
try {
    if (!paypal_verify_webhook($headers, $event)) json_out(['error' => 'Invalid signature'], 400);
    $r = $event['resource'] ?? [];
    if (($event['event_type'] ?? '') === 'CHECKOUT.ORDER.APPROVED') {
        paypal_capture((string) ($r['id'] ?? ''), (string) ($r['purchase_units'][0]['custom_id'] ?? ''));
    } elseif (($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
        paypal_capture((string) ($r['supplementary_data']['related_ids']['order_id'] ?? ''), (string) ($r['custom_id'] ?? ''));
    }
} catch (Throwable $e) {
    error_log('[paypal] webhook failed: ' . $e->getMessage());
    json_out(['error' => 'Temporary error'], 500); // PayPal retries later
}
json_out(['received' => true]);
