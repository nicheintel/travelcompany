import { applyPaidSession, verifyStripeSignature } from "@/lib/server/payments";

/**
 * Stripe → us. Point a Stripe webhook at /api/stripe/webhook for the events
 * checkout.session.completed and checkout.session.async_payment_succeeded.
 */
export async function POST(request: Request) {
  const secret = process.env.STRIPE_WEBHOOK_SECRET;
  if (!secret) return Response.json({ error: "Webhook secret not configured" }, { status: 500 });

  const body = await request.text();
  if (!verifyStripeSignature(body, request.headers.get("stripe-signature"), secret)) {
    return Response.json({ error: "Invalid signature" }, { status: 400 });
  }

  const event = JSON.parse(body) as { type: string; data: { object: Parameters<typeof applyPaidSession>[0] } };
  if (event.type === "checkout.session.completed" || event.type === "checkout.session.async_payment_succeeded") {
    await applyPaidSession(event.data.object);
  }
  return Response.json({ received: true });
}
