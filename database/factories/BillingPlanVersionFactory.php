<?php

namespace Database\Factories;

use App\Models\BillingPlanVersion;
use App\Support\Billing\FeatureCode;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPlanVersion>
 */
class BillingPlanVersionFactory extends Factory
{
    /**
     * Define a valid draft commercial version.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_code' => 'starter',
            'version' => 1,
            'name' => 'Starter Plan',
            'tier' => 1,
            'feature_codes' => [
                FeatureCode::Assistant,
            ],
            'limits' => [
                UsageLimitKey::Seats => 3,
                UsageLimitKey::Locations => 1,
                UsageLimitKey::InventoryItems => 500,
            ],
            'published_at' => null,
            'superseded_at' => null,
            'created_by_user_id' => null,
            'published_by_user_id' => null,
        ];
    }
}
