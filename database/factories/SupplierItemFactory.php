<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierItem>
 */
class SupplierItemFactory extends Factory
{
    /**
     * Define a supplier purchase pack within one organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),

            'supplier_id' => function (array $attributes): int {
                return Supplier::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },

            'inventory_item_id' => function (array $attributes): int {
                return InventoryItem::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },

            'supplier_sku' => strtoupper(
                fake()->unique()->bothify('VENDOR-#####'),
            ),

            'description' => fake()->words(3, true),

            'purchase_unit_of_measure_id' => function (
                array $attributes,
            ): int {
                return UnitOfMeasure::factory()->create([
                    'organization_id' => (int) $attributes['organization_id'],
                ])->id;
            },

            'base_quantity' => '1.000000',
            'current_price' => null,
            'currency' => 'PHP',
            'active' => true,
        ];
    }
}
