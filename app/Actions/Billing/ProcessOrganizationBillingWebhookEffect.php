<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\BillingCustomer;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessOrganizationBillingWebhookEffect
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
        private readonly BillingObservability $observability,
    ) {}

    /**
     * Persist one provider event idempotently and execute its controlled projection.
     *
     * @param  (callable(Organization): void)|null  $project
     */
    public function handle(
        BillingProvider $provider,
        string $externalEventId,
        string $externalCustomerId,
        BillingLifecycleEvent $lifecycleEvent,
        string $auditAction,
        ?callable $project = null,
    ): void {
        $organization = $this->resolveOrganization(
            $provider,
            $externalCustomerId,
        );

        if ($organization === null) {
            return;
        }

        $effect = DB::transaction(function () use (
            $organization,
            $provider,
            $externalEventId,
            $lifecycleEvent,
            $auditAction,
            $project,
        ): ?BillingWebhookEffect {
            $wasCreated = BillingWebhookEffect::query()->insertOrIgnore([
                'organization_id' => $organization->id,
                'provider' => $provider->value,
                'external_event_id' => $externalEventId,
                'stripe_event_id' => $provider === BillingProvider::Stripe
                    ? $externalEventId
                    : null,
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
                if ($project !== null) {
                    $project($organization);
                }

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
            } else {
                $this->observability->duplicateWebhookEvent(
                    $organization,
                    $provider,
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
                $organization->id,
                $provider,
                $externalEventId,
                $externalCustomerId,
            );
        } catch (Throwable $exception) {
            $this->observability->queueFailure(
                $organization->id,
                $provider,
                $externalEventId,
                $exception,
            );
        }
    }

    /** Resolve the local organization strictly through the provider ownership mapping. */
    private function resolveOrganization(
        BillingProvider $provider,
        string $externalCustomerId,
    ): ?Organization {
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
