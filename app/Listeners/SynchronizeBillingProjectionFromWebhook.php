<?php

namespace App\Listeners;

use App\Actions\Billing\SynchronizeStripeBillingProjection;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Subscription;

/**
 * Keeps the durable, provider-neutral billing projection (`billing_customers`
 * / `billing_subscriptions`) in sync with Cashier's already-synchronized
 * Stripe subscription state. Does not touch `OrganizationSubscriptionAccessResolver`
 * or any entitlement decision — this is read-side identity/history only.
 */
class SynchronizeBillingProjectionFromWebhook
{
    public function __construct(private SynchronizeStripeBillingProjection $synchronize) {}

    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $object = $payload['data']['object'] ?? null;

        if (
            ! is_array($object)
            || ! is_string($customerId = $object['customer'] ?? null)
            || ! in_array($payload['type'] ?? null, [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
            ], true)
        ) {
            return;
        }

        $organization = Organization::query()
            ->where('stripe_id', $customerId)
            ->first();

        if ($organization === null) {
            return;
        }

        // Cashier's own subscriptions.* handler has already saved its local
        // row by the time WebhookHandled fires, so this is the same
        // authoritative row Cashier just wrote — not a re-derivation.
        $subscriptionId = $object['id'] ?? null;

        if (! is_string($subscriptionId)) {
            return;
        }

        $subscription = Subscription::query()
            ->where('organization_id', $organization->getKey())
            ->where('stripe_id', $subscriptionId)
            ->first();

        if ($subscription === null) {
            return;
        }

        $this->synchronize->handle($organization, $subscription, $object);
    }
}
