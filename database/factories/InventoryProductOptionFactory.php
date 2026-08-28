<?php

namespace Database\Factories;

use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryProductOption>
 */
class InventoryProductOptionFactory extends Factory
{
    /**
     * Define an option dimension with a product family from the same
     * organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inventory_product_id' => function (
                array $attributes,
            ): int {
                return InventoryProduct::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },
            'name' => fake()->unique()->randomElement([
                'Size',
                'Color',
                'Gauge',
                'Model',
            ]),
            'active' => true,
        ];
    }
}
