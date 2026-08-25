<?php

namespace App\Actions\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\Providers\PayMongoBillingProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EnsureManualPayMongoSubscription
{
    public function __construct(
        private readonly PayMongoBillingProvider $payMongo,
        private readonly PlanCatalog $planCatalog,
    ) {}

    public function handle(Organization $organization, User $actor, ?PlanCode $planCode = null, ?string $interval = null): BillingSubscription
    {
        $existing = $organization->billingSubscriptions()
            ->where('type', (string) config('billing.subscription_type'))
            ->first();

        if ($existing !== null) {
            if ($existing->provider !== BillingProvider::PayMongo || $existing->collection_method !== BillingCollectionMethod::Manual) {
                throw new RuntimeException('This organization does not use manual QR Ph billing.');
            }

            return $existing;
        }

        if ($planCode === null || ! in_array($interval, ['monthly', 'yearly'], true)
            || $this->planCatalog->get($planCode)?->manualAmount($interval) === null) {
            throw new RuntimeException('The selected plan is not available for manual QR Ph billing.');
        }

        $customer = $this->payMongo->ensureCustomer($organization, $actor);

        return DB::transaction(function () use ($organization, $customer, $planCode, $interval): BillingSubscription {
            Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            $existing = BillingSubscription::query()
                ->where('organization_id', $organization->getKey())
                ->where('type', (string) config('billing.subscription_type'))
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return BillingSubscription::query()->create([
                'organization_id' => $organization->getKey(),
                'billing_customer_id' => $customer->getKey(),
                'provider' => BillingProvider::PayMongo,
                'type' => config('billing.subscription_type'),
                'external_subscription_id' => null,
                'plan_code' => $planCode->value,
                'interval' => $interval,
                'collection_method' => BillingCollectionMethod::Manual,
                'provider_status' => 'pending',
                'livemode' => $customer->livemode,
            ]);
        }, attempts: 3);
    }
}
