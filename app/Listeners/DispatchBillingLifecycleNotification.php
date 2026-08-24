<?php

namespace App\Listeners;

use App\Actions\Billing\NotifyOrganizationBillingLifecycle;
use App\Enums\BillingLifecycleEvent;
use Laravel\Cashier\Events\WebhookHandled;

class DispatchBillingLifecycleNotification
{
    public function __construct(private NotifyOrganizationBillingLifecycle $notify) {}

    /**
     * Dispatch notifications for lifecycle events Cashier has synchronized.
     */
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $object = $payload['data']['object'] ?? null;

        if (! is_array($object) || ! is_string($customerId = $object['customer'] ?? null)) {
            return;
        }

        $lifecycleEvent = match ($payload['type'] ?? null) {
            'customer.subscription.deleted' => BillingLifecycleEvent::SubscriptionEnded,
            'customer.subscription.updated' => $this->updatedSubscriptionEvent(
                $object,
                $payload['data']['previous_attributes'] ?? [],
            ),
            default => null,
        };

        if ($lifecycleEvent !== null) {
            $this->notify->handle($customerId, $lifecycleEvent);
        }
    }

    /**
     * Determine whether a synchronized subscription update represents a
     * lifecycle state transition that billing administrators should receive.
     *
     * @param  array<string, mixed>  $subscription
     * @param  array<string, mixed>  $previousAttributes
     */
    private function updatedSubscriptionEvent(
        array $subscription,
        array $previousAttributes,
    ): ?BillingLifecycleEvent {
        if (($subscription['status'] ?? null) === 'past_due') {
            return BillingLifecycleEvent::PaymentFailed;
        }

        if (($subscription['cancel_at_period_end'] ?? false) === true) {
            return BillingLifecycleEvent::ScheduledCancellation;
        }

        $previousStatus = $previousAttributes['status'] ?? null;

        if (
            ($subscription['status'] ?? null) === 'active'
            && in_array($previousStatus, ['past_due', 'unpaid', 'canceled'], true)
        ) {
            return BillingLifecycleEvent::Recovered;
        }

        return null;
    }
}
