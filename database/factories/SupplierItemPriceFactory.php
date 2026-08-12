<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierItemPrice>
 */
class SupplierItemPriceFactory extends Factory
{
    /**
     * Define an immutable historical supplier price.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),

            'supplier_item_id' => function (array $attributes): int {
                return SupplierItem::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },

            'price' => '100.0000',
            'currency' => 'PHP',
            'effective_at' => now(),
        ];
    }
}
