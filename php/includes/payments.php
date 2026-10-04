<?php
declare(strict_types=1);

/* Stripe Checkout over Stripe's REST API (no SDK needed). */

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
