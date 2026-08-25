<?php

namespace App\Console\Commands;

use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription as CashierSubscription;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription as StripeSubscription;
use Throwable;

final class BillingReconcile extends Command
{
    public function __construct(private readonly BillingObservability $observability)
    {
        parent::__construct();
    }

    protected $signature = 'billing:reconcile
        {--chunk=100 : Number of organizations to process per database batch}';

    protected $description =
        'Detect local Cashier and Stripe billing discrepancies without changing data.';

    public function handle(): int
    {
        $organizations = 0;
        $discrepancies = 0;
        $providerFailures = 0;

        $discrepancies += $this->reconcileStaleNotificationClaims();

        Organization::query()->with('subscriptions.items')->chunkById(
            $this->chunkSize(),
            function (Collection $batch) use (&$organizations, &$discrepancies, &$providerFailures): void {
                foreach ($batch as $organization) {
                    $organizations++;
                    [$organizationDiscrepancies, $organizationProviderFailures] = $this->reconcileOrganization($organization);
                    $discrepancies += $organizationDiscrepancies;
                    $providerFailures += $organizationProviderFailures;
                }
            },
        );

        $this->observability->subscriptionStatusCounts(
            Organization::query()->whereHas('subscriptions', fn ($query) => $query->where('stripe_status', 'past_due'))->count(),
            Organization::query()->whereHas('subscriptions', fn ($query) => $query->where('stripe_status', 'unpaid'))->count(),
        );

        $this->line(sprintf(
            'Billing reconciliation completed: %d organization%s inspected, %d discrepanc%s, %d provider failure%s.',
            $organizations, $organizations === 1 ? '' : 's', $discrepancies,
            $discrepancies === 1 ? 'y' : 'ies', $providerFailures,
            $providerFailures === 1 ? '' : 's',
        ));

        return $discrepancies === 0 && $providerFailures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Surface notification claims left behind by a defunct job attempt: a claim with
     * no dispatch marker, older than the job's maximum retry window, can no longer be
     * a retry-in-progress and needs manual investigation to confirm whether delivery
     * actually occurred before the claim can be safely cleared.
     */
    private function reconcileStaleNotificationClaims(): int
    {
        $staleClaims = BillingWebhookEffect::query()
            ->with('organization')
            ->whereNotNull('notification_claimed_at')
            ->whereNull('notification_dispatched_at')
            ->where('notification_claimed_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($staleClaims as $effect) {
            if ($effect->organization === null) {
                continue;
            }

            $this->observability->staleNotificationClaim($effect->organization, $effect->stripe_event_id);
        }

        return $staleClaims->count();
    }

    /** @return array{0: int, 1: int} */
    private function reconcileOrganization(Organization $organization): array
    {
        /** @var Collection<int, Model> $localSubscriptions */
        $localSubscriptions = $organization->subscriptions->where('type', (string) config('billing.subscription_type'));

        if (blank($organization->stripe_id)) {
            if ($localSubscriptions->isEmpty()) {
                return [0, 0];
            }

            $this->logDiscrepancy($organization, 'missing_stripe_customer');

            return [1, 0];
        }

        try {
            Cashier::stripe()->customers->retrieve($organization->stripe_id);
            $remoteSubscriptions = $this->remoteSubscriptions($organization->stripe_id);
        } catch (Throwable $exception) {
            if ($this->isMissingCustomer($exception)) {
                $this->logDiscrepancy($organization, 'missing_stripe_customer');

                return [1, 0];
            }

            $this->logProviderFailure($organization, $exception);

            return [0, 1];
        }

        $discrepancies = 0;

        foreach ($remoteSubscriptions as $stripeId => $remoteSubscription) {
            if ($localSubscriptions->contains('stripe_id', $stripeId)) {
                continue;
            }

            $this->logDiscrepancy($organization, 'missing_local_subscription', ['stripe_subscription_id' => $stripeId]);
            $discrepancies++;
        }

        foreach ($localSubscriptions as $localSubscription) {
            if (! $localSubscription instanceof CashierSubscription) {
                continue;
            }

            $remoteSubscription = $remoteSubscriptions[$localSubscription->stripe_id] ?? null;

            if ($remoteSubscription === null) {
                if (OrganizationSubscriptionAccessResolver::resolve($organization)->isWritable()) {
                    $this->logDiscrepancy($organization, 'unexpected_local_active_state', [
                        'stripe_subscription_id' => $localSubscription->stripe_id,
                        'local_status' => $localSubscription->stripe_status,
                    ]);
                } else {
                    $this->logDiscrepancy($organization, 'subscription_mismatch', [
                        'stripe_subscription_id' => $localSubscription->stripe_id,
                        'local_status' => $localSubscription->stripe_status,
                        'remote_status' => 'missing',
                    ]);
                }

                $discrepancies++;

                continue;
            }

            if ($this->subscriptionsMatch($localSubscription, $remoteSubscription)) {
                continue;
            }

            $this->logDiscrepancy($organization, 'subscription_mismatch', [
                'stripe_subscription_id' => $localSubscription->stripe_id,
                'local_status' => $localSubscription->stripe_status,
                'remote_status' => $remoteSubscription->status,
            ]);
            $discrepancies++;
        }

        return [$discrepancies, 0];
    }

    /** @return array<string, StripeSubscription> */
    private function remoteSubscriptions(string $stripeCustomerId): array
    {
        $remoteSubscriptions = [];

        foreach (Cashier::stripe()->subscriptions->all([
            'customer' => $stripeCustomerId, 'status' => 'all', 'limit' => 100,
        ])->autoPagingIterator() as $subscription) {
            $remoteSubscriptions[$subscription->id] = $subscription;
        }

        return $remoteSubscriptions;
    }

    private function subscriptionsMatch(CashierSubscription $localSubscription, StripeSubscription $remoteSubscription): bool
    {
        return $localSubscription->stripe_status === $remoteSubscription->status
            && $this->localPriceIds($localSubscription) === $this->remotePriceIds($remoteSubscription);
    }

    /** @return list<string> */
    private function localPriceIds(CashierSubscription $subscription): array
    {
        if ($subscription->stripe_price !== null) {
            return [$subscription->stripe_price];
        }

        $priceIds = [];

        foreach ($subscription->items as $item) {
            if ($item instanceof CashierSubscriptionItem && $item->stripe_price !== '') {
                $priceIds[] = $item->stripe_price;
            }
        }

        sort($priceIds);

        return $priceIds;
    }

    /** @return list<string> */
    private function remotePriceIds(StripeSubscription $subscription): array
    {
        $priceIds = [];

        foreach ($subscription->items->data as $item) {
            $priceIds[] = $item->price->id;
        }

        sort($priceIds);

        return $priceIds;
    }

    private function isMissingCustomer(Throwable $exception): bool
    {
        return $exception instanceof InvalidRequestException && $exception->getHttpStatus() === 404;
    }

    /** @param array<string, string> $context */
    private function logDiscrepancy(Organization $organization, string $discrepancy, array $context = []): void
    {
        $this->observability->reconciliationMismatch($organization, $discrepancy, $context);
    }

    private function logProviderFailure(Organization $organization, Throwable $exception): void
    {
        $this->observability->reconciliationProviderFailure($organization, $exception);
    }

    private function chunkSize(): int
    {
        return max(1, (int) $this->option('chunk'));
    }
}
