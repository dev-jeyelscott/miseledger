<?php

namespace App\Jobs;

use App\Actions\Billing\NotifyOrganizationBillingLifecycle;
use App\Models\BillingWebhookEffect;
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
        public readonly string $stripeEventId,
        public readonly string $stripeCustomerId,
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
                ->where('stripe_event_id', $this->stripeEventId)
                ->first();

            if ($effect === null || $effect->notification_dispatched_at !== null) {
                return null;
            }

            // Records the most recent delivery attempt for observability. This does not
            // gate redelivery: whether a prior attempt terminated before or after the
            // send call cannot be determined locally, but BillingLifecycleNotification
            // carries a deterministic Message-Id derived from the Stripe event, so the
            // mail transport deduplicates redelivery and a defunct claim is always
            // safe, and necessary, to retry to completion.
            $effect->update(['notification_claimed_at' => now()]);

            return $effect;
        });

        if ($effect === null) {
            return;
        }

        $notify->handle($this->stripeCustomerId, $effect->lifecycle_event, $this->stripeEventId);

        $effect->update(['notification_dispatched_at' => now()]);
    }

    public function uniqueId(): string
    {
        return $this->stripeEventId;
    }

    public function failed(Throwable $exception): void
    {
        app(BillingObservability::class)->queueFailure(
            $this->organizationId,
            self::class,
            $exception,
        );
    }
}
