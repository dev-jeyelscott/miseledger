<?php

namespace Database\Factories;

use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryProductOptionValue>
 */
class InventoryProductOptionValueFactory extends Factory
{
    /**
     * Define a value with an option dimension from the same organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inventory_product_option_id' => function (
                array $attributes,
            ): int {
                return InventoryProductOption::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },
            'value' => fake()->unique()->word(),
            'active' => true,
        ];
    }
}
