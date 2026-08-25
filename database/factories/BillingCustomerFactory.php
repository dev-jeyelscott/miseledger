<?php

namespace Database\Factories;

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingCustomer>
 */
class BillingCustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => BillingProvider::Stripe,
            'external_customer_id' => 'cus_'.Str::lower(Str::random(14)),
            'livemode' => false,
        ];
    }
}
