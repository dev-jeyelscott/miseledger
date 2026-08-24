<?php

namespace App\Listeners;

use App\Actions\Billing\ProcessOrganizationBillingWebhookEffect;
use App\Enums\BillingLifecycleEvent;
use Laravel\Cashier\Events\WebhookReceived;

class DispatchBillingLifecycleNotificationFromReceivedWebhook
{
    public function __construct(private ProcessOrganizationBillingWebhookEffect $process) {}

    /**
     * Dispatch notifications for lifecycle events Cashier does not itself
     * synchronize, after signature verification has completed.
     */
    public function handle(WebhookReceived $event): void
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
            'customer.subscription.trial_will_end' => BillingLifecycleEvent::TrialEnding,
            'invoice.payment_failed' => BillingLifecycleEvent::PaymentFailed,
            default => null,
        };

        if ($lifecycleEvent !== null) {
            $this->process->handle($stripeEventId, $customerId, $lifecycleEvent);
        }
    }
}
