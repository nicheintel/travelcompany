<?php
/*
 * Stripe → us. In Stripe → Developers → Webhooks, add https://YOUR-SITE/stripe-webhook.php
 * for checkout.session.completed and checkout.session.async_payment_succeeded.
 */
require __DIR__ . '/includes/bootstrap.php';

$secret = (string) config('stripe_webhook_secret');
if ($secret === '') json_out(['error' => 'Webhook secret not configured'], 500);
$body = (string) file_get_contents('php://input');
if (!verify_stripe_signature($body, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null, $secret)) json_out(['error' => 'Invalid signature'], 400);

$event = json_decode($body, true);
if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    apply_paid_session($event['data']['object'] ?? []);
}
json_out(['received' => true]);
