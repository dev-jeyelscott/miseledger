<?php

namespace Database\Factories;

use App\Enums\InventoryItemType;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define an inventory item with a base UOM from the same organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'base_unit_of_measure_id' => function (
                array $attributes,
            ): int {
                return UnitOfMeasure::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'type' => InventoryItemType::Ingredient,
            'yield_percentage' => '100.00',
            'active' => true,
        ];
    }
}
