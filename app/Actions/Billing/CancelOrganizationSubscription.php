<?php

namespace App\Actions\Billing;

use App\Enums\BillingProvider;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelOrganizationSubscription
{
    public function __construct(private readonly BillingProviderManager $providerManager) {}

    public function handle(Organization $organization): void
    {
        $subscriptions = BillingSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->where('type', (string) config('billing.subscription_type'))
            ->get();

        if ($subscriptions->count() !== 1) {
            throw new RuntimeException('Subscription management state could not be resolved safely.');
        }

        $subscription = $subscriptions->first();

        if (! $subscription instanceof BillingSubscription) {
            throw new RuntimeException('Subscription management state could not be resolved safely.');
        }

        if ($subscription->provider !== BillingProvider::PayMongo || $subscription->cancelled_at !== null || ! $this->paidAccessEndsAt($subscription)?->isFuture()) {
            throw new RuntimeException('This subscription cannot be cancelled safely.');
        }

        $this->providerManager->provider($subscription->provider)->cancelSubscription($subscription);

        DB::transaction(function () use ($subscription): void {
            $projection = BillingSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($projection->cancelled_at !== null) {
                return;
            }

            $projection->update([
                'provider_status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => $this->paidAccessEndsAt($projection),
                'next_billing_at' => null,
            ]);
        });
    }

    private function paidAccessEndsAt(BillingSubscription $subscription): ?Carbon
    {
        return $subscription->ends_at ?? $subscription->current_period_ends_at ?? $subscription->next_billing_at;
    }
}
