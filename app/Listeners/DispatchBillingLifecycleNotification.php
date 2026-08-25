<?php

namespace App\Listeners;

use App\Actions\Billing\ProcessOrganizationBillingWebhookEffect;
use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use Laravel\Cashier\Events\WebhookHandled;

class DispatchBillingLifecycleNotification
{
    public function __construct(private ProcessOrganizationBillingWebhookEffect $process) {}

    /**
     * Dispatch notifications for lifecycle events Cashier has synchronized.
     */
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $object = $payload['data']['object'] ?? null;

        if (
            ! is_array($object)
            || ! is_string($stripeEventId = $payload['id'] ?? null)
            || ! is_string($customerId = $object['customer'] ?? null)
        ) {
            return;
        }

        $lifecycleEvent = match ($payload['type'] ?? null) {
            'customer.subscription.created' => BillingLifecycleEvent::SubscriptionStarted,
            'customer.subscription.deleted' => BillingLifecycleEvent::SubscriptionEnded,
            'customer.subscription.updated' => $this->updatedSubscriptionEvent(
                $object,
                $payload['data']['previous_attributes'] ?? [],
            ),
            default => null,
        };

        if ($lifecycleEvent !== null) {
            $this->process->handle(
                BillingProvider::Stripe,
                $stripeEventId,
                $customerId,
                $lifecycleEvent,
                $this->auditAction($lifecycleEvent),
            );
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

        if (($previousAttributes['cancel_at_period_end'] ?? false) === true) {
            return BillingLifecycleEvent::SubscriptionResumed;
        }

        if (
            ($subscription['status'] ?? null) === 'active'
            && in_array($previousStatus, ['past_due', 'unpaid'], true)
        ) {
            return BillingLifecycleEvent::Recovered;
        }

        if (array_key_exists('items', $previousAttributes)) {
            return BillingLifecycleEvent::PlanChanged;
        }

        return null;
    }

    /**
     * Return the stable audit action for a provider-synchronized event.
     */
    private function auditAction(BillingLifecycleEvent $lifecycleEvent): string
    {
        return match ($lifecycleEvent) {
            BillingLifecycleEvent::SubscriptionStarted => 'billing.subscription.started',
            BillingLifecycleEvent::PlanChanged => 'billing.subscription.plan_changed',
            BillingLifecycleEvent::ScheduledCancellation => 'billing.subscription.cancellation_scheduled',
            BillingLifecycleEvent::SubscriptionResumed => 'billing.subscription.resumed',
            BillingLifecycleEvent::SubscriptionEnded => 'billing.subscription.ended',
            BillingLifecycleEvent::PaymentFailed => 'billing.subscription.past_due',
            BillingLifecycleEvent::Recovered => 'billing.payment.recovered',
            BillingLifecycleEvent::TrialEnding => 'billing.subscription.trial_ending',
        };
    }
}
