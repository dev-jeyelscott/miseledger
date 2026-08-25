<?php

namespace App\Jobs;

use App\Actions\Billing\NotifyOrganizationBillingLifecycle;
use App\Enums\BillingProvider;
use App\Exceptions\AmbiguousBillingNotificationDeliveryException;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SendOrganizationBillingLifecycleNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $billingWebhookEffectId,
        public readonly int $organizationId,
        public readonly BillingProvider $provider,
        public readonly string $externalEventId,
        public readonly string $externalCustomerId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotifyOrganizationBillingLifecycle $notify): void
    {
        $effect = DB::transaction(function (): ?BillingWebhookEffect {
            $effect = BillingWebhookEffect::query()
                ->lockForUpdate()
                ->whereKey($this->billingWebhookEffectId)
                ->where('organization_id', $this->organizationId)
                ->where('provider', $this->provider->value)
                ->where('external_event_id', $this->externalEventId)
                ->first();

            if ($effect === null || $effect->notification_dispatched_at !== null) {
                return null;
            }

            // A claim already recorded by a prior attempt, with no dispatch marker, is
            // ambiguous: that attempt may have terminated before or after delivery
            // actually occurred, and there is no local or provider-side signal capable
            // of resolving that. Resending here risks a duplicate externally visible
            // notification, so the claim is left untouched and delivery is refused;
            // billing:reconcile surfaces stale claims like this for manual recovery.
            if ($effect->notification_claimed_at !== null) {
                throw new AmbiguousBillingNotificationDeliveryException($this->externalEventId);
            }

            $effect->update(['notification_claimed_at' => now()]);

            return $effect;
        });

        if ($effect === null) {
            return;
        }

        // The claim recorded above is left in place if this throws: a transport can
        // accept a message and then throw on a lost acknowledgement, and a
        // multi-recipient send can throw after earlier recipients already received
        // it, so a thrown send is not provable non-delivery. Any subsequent retry
        // attempt will find the claim already set and refuse to redeliver via the
        // ambiguous-claim guard above, instead surfacing for manual reconciliation.
        $notify->handle(Organization::findOrFail($this->organizationId), $effect->lifecycle_event, $this->externalEventId);

        $effect->update(['notification_dispatched_at' => now()]);
    }

    public function uniqueId(): string
    {
        return $this->provider->value.':'.$this->externalEventId;
    }

    public function failed(Throwable $exception): void
    {
        app(BillingObservability::class)->queueFailure(
            $this->organizationId,
            $this->provider,
            $this->externalEventId,
            $exception,
        );
    }
}
