<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\BillingCustomer;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessOrganizationBillingWebhookEffect
{
    public function __construct(private readonly RecordAuditEntry $recordAuditEntry) {}

    /**
     * Persist and dispatch the custom effects for one provider event without
     * participating in Cashier's subscription synchronization. Idempotency
     * is enforced by the database's unique (provider, external_event_id)
     * constraint plus the row lock below, not by a check-then-act race.
     */
    public function handle(
        BillingProvider $provider,
        string $externalEventId,
        string $externalCustomerId,
        BillingLifecycleEvent $lifecycleEvent,
        string $auditAction,
        ?callable $project = null,
    ): void {
        $organization = $this->resolveOrganization($provider, $externalCustomerId);

        if ($organization === null) {
            return;
        }

        $effect = DB::transaction(function () use ($organization, $provider, $externalEventId, $lifecycleEvent, $auditAction, $project): ?BillingWebhookEffect {
            $wasCreated = BillingWebhookEffect::query()->insertOrIgnore([
                'organization_id' => $organization->getKey(),
                'provider' => $provider->value,
                'external_event_id' => $externalEventId,
                'stripe_event_id' => $provider === BillingProvider::Stripe ? $externalEventId : null,
                'lifecycle_event' => $lifecycleEvent->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            $effect = BillingWebhookEffect::query()
                ->lockForUpdate()
                ->where('provider', $provider->value)
                ->where('external_event_id', $externalEventId)
                ->firstOrFail();

            if ($wasCreated) {
                $project?->__invoke($organization);

                $this->recordAuditEntry->handle(
                    $organization,
                    null,
                    $auditAction,
                    BillingWebhookEffect::class,
                    $effect->getKey(),
                    null,
                    [
                        'origin' => $provider->value.'_webhook',
                        'lifecycle_event' => $lifecycleEvent->value,
                    ],
                    $externalEventId,
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
                $provider,
                $externalEventId,
                $externalCustomerId,
            );
        } catch (Throwable) {
            Log::channel((string) config('billing.logger'))
                ->warning('Billing lifecycle notification dispatch failed.', [
                    'organization_id' => $organization->getKey(),
                    'event' => $lifecycleEvent->value,
                    'provider' => $provider->value,
                    'external_event_id' => $externalEventId,
                ]);
        }
    }

    private function resolveOrganization(BillingProvider $provider, string $externalCustomerId): ?Organization
    {
        if ($provider === BillingProvider::Stripe) {
            return Organization::query()
                ->where('stripe_id', $externalCustomerId)
                ->first();
        }

        return BillingCustomer::query()
            ->where('provider', $provider->value)
            ->where('external_customer_id', $externalCustomerId)
            ->first()
            ?->organization;
    }
}
