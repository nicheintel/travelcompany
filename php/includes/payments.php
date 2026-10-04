<?php
declare(strict_types=1);

/*
 * Online payments: PayPal (preferred when configured) or Stripe Checkout, both over their
 * REST APIs (no SDKs). A booking is only marked paid after the provider confirms the
 * payment server-to-server and the amount matches the booking.
 */

/** 'paypal', 'stripe' or null (reserve now, pay later). */
function payment_provider(): ?string
{
    if (paypal_enabled()) return 'paypal';
    if (stripe_enabled()) return 'stripe';
    return null;
}

function payments_enabled(): bool
{
    return payment_provider() !== null;
}

// ---------- PayPal (Orders v2) ----------

function paypal_enabled(): bool
{
    return (string) config('paypal_client_id') !== '' && (string) config('paypal_secret') !== '';
}

function paypal_live(): bool
{
    return config('paypal_mode') === 'live';
}

function paypal_base(): string
{
    $configured = rtrim((string) config('paypal_api_base'), '/');
    if ($configured !== '') return $configured;
    return paypal_live() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

/** OAuth access token (client credentials). Throws with PayPal's message if the keys are wrong. */
function paypal_token(): string
{
    static $token = null;
    if ($token) return $token;
    $res = http_json('POST', paypal_base() . '/v1/oauth2/token', [
        'Authorization: Basic ' . base64_encode(config('paypal_client_id') . ':' . config('paypal_secret')),
    ], ['grant_type' => 'client_credentials'], 20, true);
    if ($res['status'] >= 300 || empty($res['json']['access_token'])) {
        throw new RuntimeException('PayPal ' . $res['status'] . ': ' . ($res['json']['error_description'] ?? $res['json']['error'] ?? 'could not sign in with your Client ID and Secret'));
    }
    return $token = $res['json']['access_token'];
}

/** Calls the PayPal API; returns ['status', 'json'] so callers can handle 4xx answers. */
function paypal(string $method, string $path, ?array $body = null, array $headers = []): array
{
    return http_json($method, paypal_base() . $path, array_merge(['Authorization: Bearer ' . paypal_token()], $headers), $body, 30);
}

function paypal_amount(int $total): string
{
    return number_format($total, 2, '.', '');
}

/** Creates a PayPal order for the booking; returns [order id, URL to send the customer to]. */
function paypal_create_order(array $b): array
{
    $trip = app_url() . '/trip.php?ref=' . rawurlencode($b['reference']);
    $res = paypal('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $b['reference'],
            'custom_id' => $b['reference'],
            'description' => mb_substr("{$b['quote']['title']} ({$b['reference']})", 0, 127),
            'amount' => ['currency_code' => 'USD', 'value' => paypal_amount($b['total'])],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'brand_name' => mb_substr((string) config('site_name'), 0, 127),
            'user_action' => 'PAY_NOW',
            'shipping_preference' => 'NO_SHIPPING',
            'return_url' => $trip . '&paypal=return',
            'cancel_url' => $trip . '&payment=cancelled',
        ]]],
    ], ['PayPal-Request-Id: create-' . $b['reference'] . '-' . $b['total'] . '-' . bin2hex(random_bytes(4))]);
    if ($res['status'] >= 300) {
        throw new RuntimeException('PayPal ' . $res['status'] . ': ' . ($res['json']['message'] ?? 'could not create order'));
    }
    foreach ($res['json']['links'] ?? [] as $link) {
        if (in_array($link['rel'], ['payer-action', 'approve'], true)) return [$res['json']['id'], $link['href']];
    }
    throw new RuntimeException('PayPal order has no approval link');
}

/**
 * Captures (takes) the money for an approved order and marks the booking paid if PayPal
 * confirms the full amount. Safe to call more than once (return page + webhook).
 */
function paypal_capture(string $orderId, string $reference): bool
{
    if (!preg_match('/^[A-Z0-9-]{5,40}$/i', $orderId)) return false;
    $order = paypal('GET', '/v2/checkout/orders/' . $orderId)['json'];
    if (($order['purchase_units'][0]['custom_id'] ?? null) !== $reference) {
        error_log("[paypal] order $orderId does not belong to $reference");
        return false;
    }
    if (($order['status'] ?? '') === 'APPROVED') {
        $res = paypal('POST', "/v2/checkout/orders/$orderId/capture", [], ['Prefer: return=representation', "PayPal-Request-Id: capture-$orderId"]);
        $order = $res['status'] < 300 ? $res['json'] : paypal('GET', '/v2/checkout/orders/' . $orderId)['json'];
    }
    if (($order['status'] ?? '') !== 'COMPLETED') return false;

    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    $b = db_one('SELECT total FROM bookings WHERE reference = ?', [$reference]);
    if (!$capture || !$b || ($capture['status'] ?? '') !== 'COMPLETED'
        || ($capture['amount']['currency_code'] ?? '') !== 'USD'
        || ($capture['amount']['value'] ?? '') !== paypal_amount((int) $b['total'])) {
        error_log("[paypal] capture for $reference doesn't match the booking amount");
        return false;
    }
    if (mark_paid($reference, 'PayPal', null, "Paid via PayPal (order $orderId, capture {$capture['id']}).", $orderId)) {
        notify_booking('paid', $reference);
    }
    return true;
}

/** Asks PayPal whether a webhook call really came from PayPal. */
function paypal_verify_webhook(array $headers, array $event): bool
{
    $webhookId = (string) config('paypal_webhook_id');
    if ($webhookId === '') return false;
    $res = paypal('POST', '/v1/notifications/verify-webhook-signature', [
        'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
        'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
        'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'webhook_id' => $webhookId,
        'webhook_event' => $event,
    ]);
    return ($res['json']['verification_status'] ?? '') === 'SUCCESS';
}

// ---------- Stripe Checkout ----------

function stripe_enabled(): bool
{
    return (string) config('stripe_secret_key') !== '';
}

function stripe(string $method, string $path, ?array $form = null): array
{
    $res = http_json($method, rtrim((string) config('stripe_api_base'), '/') . '/v1/' . $path, ['Authorization: Bearer ' . config('stripe_secret_key')], $form, 30, true);
    if ($res['status'] >= 300) {
        throw new RuntimeException('Stripe ' . $res['status'] . ': ' . ($res['json']['error']['message'] ?? 'request failed'));
    }
    return $res['json'];
}

function create_checkout_session(array $b): array
{
    $trip = app_url() . '/trip.php?ref=' . rawurlencode($b['reference']);
    return stripe('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => $trip . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $trip,
        'customer_email' => $b['contact_email'],
        'client_reference_id' => $b['reference'],
        'metadata' => ['reference' => $b['reference']],
        'payment_intent_data' => ['metadata' => ['reference' => $b['reference']]],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $b['total'] * 100,
                'product_data' => ['name' => "{$b['quote']['title']} ({$b['reference']})", 'description' => $b['quote']['subtitle']],
            ],
        ]],
    ]);
}

function retrieve_checkout_session(string $id): array
{
    return stripe('GET', 'checkout/sessions/' . rawurlencode($id));
}

/** Marks the booking paid if the session paid the right amount. Safe to call twice; emails once. */
function apply_paid_session(array $session): bool
{
    $ref = $session['metadata']['reference'] ?? $session['client_reference_id'] ?? null;
    if (!$ref || ($session['payment_status'] ?? '') !== 'paid') return false;
    $b = db_one('SELECT total FROM bookings WHERE reference = ?', [$ref]);
    if (!$b) return false;
    if (($session['currency'] ?? '') !== 'usd' || (int) ($session['amount_total'] ?? -1) !== (int) $b['total'] * 100) {
        error_log("[payments] Amount mismatch for $ref");
        return false;
    }
    if (mark_paid($ref, 'Card (Stripe)', null, "Paid by card via Stripe (session {$session['id']}).", $session['id'])) {
        notify_booking('paid', $ref);
    }
    return true;
}

/** Verifies the Stripe-Signature header (HMAC-SHA256 over "timestamp.body", 5-minute tolerance). */
function verify_stripe_signature(string $body, ?string $header, string $secret): bool
{
    if (!$header) return false;
    $t = 0;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        if ($k === 't') $t = (int) $v;
        if ($k === 'v1') $sigs[] = $v;
    }
    if (!$t || !$sigs || abs(time() - $t) > 300) return false;
    $expected = hash_hmac('sha256', "$t.$body", $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}
