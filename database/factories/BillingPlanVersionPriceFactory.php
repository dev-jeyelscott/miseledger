<?php

namespace Database\Factories;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Models\BillingPlanVersion;
use App\Models\BillingPlanVersionPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPlanVersionPrice>
 */
class BillingPlanVersionPriceFactory extends Factory
{
    /**
     * Define an exact minor-unit commercial price.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_plan_version_id' => BillingPlanVersion::factory(),
            'provider' => BillingProvider::PayMongo,
            'collection_method' => BillingCollectionMethod::Manual,
            'interval' => 'monthly',
            'currency' => 'PHP',
            'amount_minor' => 49_900,
        ];
    }
}
