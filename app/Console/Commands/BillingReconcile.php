<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingProvider;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\Providers\BillingProviderManager;
use App\Support\Billing\Providers\RemoteBillingSubscription;
use App\Support\Billing\Providers\RemoteBillingSubscriptionNotFoundException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;
use Throwable;

final class BillingReconcile extends Command
{
    public function __construct(
        private readonly BillingObservability $observability,
        private readonly BillingProviderManager $providerManager,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {
        parent::__construct();
    }

    protected $signature = 'billing:reconcile {--chunk=100 : Number of billing subscriptions to process per database batch}';

    protected $description = 'Reconcile local billing projections with authoritative provider subscription state.';

    public function handle(): int
    {
        $subscriptions = 0;
        $discrepancies = $this->reconcileStaleNotificationClaims();
        $providerFailures = 0;

        BillingSubscription::query()->with(['organization', 'billingCustomer'])
            ->where('type', (string) config('billing.subscription_type'))
            ->chunkById($this->chunkSize(), function (Collection $batch) use (&$subscriptions, &$discrepancies, &$providerFailures): void {
                foreach ($batch as $subscription) {
                    if (! $subscription instanceof BillingSubscription || $subscription->organization === null || $subscription->billingCustomer === null) {
                        continue;
                    }
                    $subscriptions++;
                    [$found, $failed] = $this->reconcileSubscription($subscription);
                    $discrepancies += $found;
                    $providerFailures += $failed;
                }
            });

        [$found, $failed] = $this->reconcileStripeRemoteOnlySubscriptions();
        $discrepancies += $found;
        $providerFailures += $failed;

        $this->observability->subscriptionStatusCounts(
            BillingSubscription::query()->where('provider_status', 'past_due')->count(),
            BillingSubscription::query()->where('provider_status', 'unpaid')->count(),
        );

        $this->line(sprintf('Billing reconciliation completed: %d subscription%s inspected, %d discrepanc%s, %d provider failure%s.', $subscriptions, $subscriptions === 1 ? '' : 's', $discrepancies, $discrepancies === 1 ? 'y' : 'ies', $providerFailures, $providerFailures === 1 ? '' : 's'));

        return $discrepancies === 0 && $providerFailures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function reconcileStaleNotificationClaims(): int
    {
        $claims = BillingWebhookEffect::query()->with('organization')->whereNotNull('notification_claimed_at')->whereNull('notification_dispatched_at')->where('notification_claimed_at', '<', now()->subMinutes(30))->get();
        foreach ($claims as $effect) {
            if ($effect->organization !== null) {
                $this->observability->staleNotificationClaim($effect->organization, $effect->external_event_id, $effect->provider);
            }
        }

        return $claims->count();
    }

    /** @return array{0: int, 1: int} */
    private function reconcileSubscription(BillingSubscription $subscription): array
    {
        try {
            $remote = $this->providerManager->provider($subscription->provider)->retrieveSubscription($subscription);
        } catch (RemoteBillingSubscriptionNotFoundException) {
            $this->observability->reconciliationMismatch($subscription->organization, $subscription->provider, 'missing_remote_subscription', ['subscription_status' => $subscription->provider_status, 'livemode' => $subscription->livemode]);

            return [1, 0];
        } catch (Throwable $exception) {
            $this->observability->reconciliationProviderFailure($subscription->organization, $subscription->provider, $exception, $subscription->provider_status, $subscription->livemode);

            return [0, 1];
        }

        if ($remote->externalCustomerId !== $subscription->billingCustomer->external_customer_id || $remote->livemode !== $subscription->livemode) {
            $this->observability->reconciliationProviderFailure($subscription->organization, $subscription->provider, new \RuntimeException('Provider subscription ownership validation failed.'), $subscription->provider_status, $subscription->livemode);

            return [0, 1];
        }
        if ($this->projectionMatches($subscription, $remote)) {
            return [0, 0];
        }

        $this->updateProjection($subscription, $remote);
        $this->observability->reconciliationMismatch($subscription->organization, $subscription->provider, 'subscription_mismatch', ['subscription_status' => $remote->status, 'livemode' => $remote->livemode]);

        return [1, 0];
    }

    private function updateProjection(BillingSubscription $subscription, RemoteBillingSubscription $remote): void
    {
        DB::transaction(function () use ($subscription, $remote): void {
            $projection = BillingSubscription::query()->with(['organization', 'billingCustomer'])->lockForUpdate()->whereKey($subscription->getKey())
                ->where('organization_id', $subscription->organization_id)->where('billing_customer_id', $subscription->billing_customer_id)
                ->where('provider', $subscription->provider)->where('external_subscription_id', $subscription->external_subscription_id)
                ->where('livemode', $subscription->livemode)->firstOrFail();
            if ($projection->billingCustomer->external_customer_id !== $remote->externalCustomerId || $projection->livemode !== $remote->livemode || $this->projectionMatches($projection, $remote)) {
                return;
            }
            $before = $this->projectionSnapshot($projection);
            $projection->update($remote->projection());
            $this->recordAuditEntry->handle($projection->organization, null, 'billing.subscription.reconciled', BillingSubscription::class, $projection->getKey(), $before, $this->projectionSnapshot($projection->fresh()), $projection->external_subscription_id);
        });
    }

    /** @return array{0: int, 1: int} */
    private function reconcileStripeRemoteOnlySubscriptions(): array
    {
        $discrepancies = 0;
        $providerFailures = 0;
        Organization::query()->whereNotNull('stripe_id')->chunkById($this->chunkSize(), function (Collection $organizations) use (&$discrepancies, &$providerFailures): void {
            foreach ($organizations as $organization) {
                if (! $organization instanceof Organization || blank($organization->stripe_id)) {
                    continue;
                }
                try {
                    Cashier::stripe()->customers->retrieve($organization->stripe_id);
                    $remoteIds = [];
                    foreach (Cashier::stripe()->subscriptions->all(['customer' => $organization->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $remote) {
                        $remoteIds[] = $remote->id;
                    }
                } catch (InvalidRequestException $exception) {
                    if ($exception->getHttpStatus() === 404) {
                        $this->observability->reconciliationMismatch($organization, BillingProvider::Stripe, 'missing_stripe_customer');
                        $discrepancies++;

                        continue;
                    }

                    $this->observability->reconciliationProviderFailure($organization, BillingProvider::Stripe, $exception);
                    $providerFailures++;

                    continue;
                } catch (Throwable $exception) {
                    $this->observability->reconciliationProviderFailure($organization, BillingProvider::Stripe, $exception);
                    $providerFailures++;

                    continue;
                }
                $localIds = BillingSubscription::query()->where('organization_id', $organization->getKey())->where('provider', BillingProvider::Stripe)->pluck('external_subscription_id');
                foreach ($remoteIds as $remoteId) {
                    if (! $localIds->contains($remoteId)) {
                        $this->observability->reconciliationMismatch($organization, BillingProvider::Stripe, 'missing_local_subscription');
                        $discrepancies++;
                    }
                }
            }
        });

        return [$discrepancies, $providerFailures];
    }

    private function projectionMatches(BillingSubscription $subscription, RemoteBillingSubscription $remote): bool
    {
        return $this->projectionSnapshot($subscription) === $this->normalizeProjection($remote->projection());
    }

    /** @return array<string, bool|string|null> */
    private function projectionSnapshot(BillingSubscription $subscription): array
    {
        return $this->normalizeProjection($subscription->only(['external_plan_id', 'provider_status', 'livemode', 'trial_ends_at', 'current_period_ends_at', 'next_billing_at', 'ends_at', 'cancelled_at']));
    }

    /** @param array<string, Carbon|bool|string|null> $projection @return array<string, bool|string|null> */
    private function normalizeProjection(array $projection): array
    {
        foreach ($projection as $field => $value) {
            if ($value instanceof Carbon) {
                $projection[$field] = $value->utc()->toISOString();
            }
        }

        return $projection;
    }

    private function chunkSize(): int
    {
        return max(1, (int) $this->option('chunk'));
    }
}
