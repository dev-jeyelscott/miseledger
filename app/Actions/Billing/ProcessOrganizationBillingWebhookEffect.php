<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingLifecycleEvent;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessOrganizationBillingWebhookEffect
{
    public function __construct(private readonly RecordAuditEntry $recordAuditEntry) {}

    /**
     * Persist and dispatch the custom effects for one Stripe event without
     * participating in Cashier's subscription synchronization.
     */
    public function handle(
        string $stripeEventId,
        string $stripeCustomerId,
        BillingLifecycleEvent $lifecycleEvent,
        string $auditAction,
    ): void {
        $organization = Organization::query()
            ->where('stripe_id', $stripeCustomerId)
            ->first();

        if ($organization === null) {
            return;
        }

        $effect = DB::transaction(function () use ($organization, $stripeEventId, $lifecycleEvent, $auditAction): ?BillingWebhookEffect {
            $wasCreated = BillingWebhookEffect::query()->insertOrIgnore([
                'organization_id' => $organization->getKey(),
                'stripe_event_id' => $stripeEventId,
                'lifecycle_event' => $lifecycleEvent->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            $effect = BillingWebhookEffect::query()
                ->lockForUpdate()
                ->where('stripe_event_id', $stripeEventId)
                ->firstOrFail();

            if ($wasCreated) {
                $this->recordAuditEntry->handle(
                    $organization,
                    null,
                    $auditAction,
                    BillingWebhookEffect::class,
                    $effect->getKey(),
                    null,
                    [
                        'origin' => 'stripe_webhook',
                        'lifecycle_event' => $lifecycleEvent->value,
                    ],
                    $stripeEventId,
                );
            }

            if ($effect->notification_dispatched_at !== null) {
                return null;
            }

            return $effect;
        });

        if ($effect === null) {
            return;
        }

        try {
            SendOrganizationBillingLifecycleNotification::dispatch(
                $effect->getKey(),
                $organization->getKey(),
                $stripeEventId,
                $stripeCustomerId,
            );
        } catch (Throwable) {
            Log::channel((string) config('billing.logger'))
                ->warning('Billing lifecycle notification dispatch failed.', [
                    'organization_id' => $organization->getKey(),
                    'event' => $lifecycleEvent->value,
                    'stripe_event_id' => $stripeEventId,
                ]);
        }
    }
}
