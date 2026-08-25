<?php

namespace App\Listeners;

use App\Actions\Billing\SynchronizeStripeBillingProjection;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookHandled;

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

        $this->synchronize->handle($organization, $object);
    }
}
