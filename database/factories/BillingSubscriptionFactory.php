<?php

namespace Database\Factories;

use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingSubscription>
 */
class BillingSubscriptionFactory extends Factory
{
    /**
     * Derives `organization_id` and `provider` from the billing customer by
     * default so factory-built rows satisfy the composite foreign key
     * (billing_customer_id, organization_id, provider) without extra setup.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_customer_id' => BillingCustomer::factory(),
            'organization_id' => fn (array $attributes) => BillingCustomer::query()
                ->whereKey($attributes['billing_customer_id'])
                ->value('organization_id'),
            'provider' => fn (array $attributes) => BillingCustomer::query()
                ->whereKey($attributes['billing_customer_id'])
                ->value('provider'),
            'external_subscription_id' => 'sub_'.Str::lower(Str::random(14)),
            'external_plan_id' => null,
            'plan_code' => null,
            'interval' => null,
            'provider_status' => 'active',
            'livemode' => false,
            'trial_ends_at' => null,
            'current_period_ends_at' => null,
            'next_billing_at' => null,
            'ends_at' => null,
            'cancelled_at' => null,
        ];
    }
}
