<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemUnit>
 */
class InventoryItemUnitFactory extends Factory
{
    /**
     * Define a conversion whose UOM belongs to the item's organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'unit_of_measure_id' => function (
                array $attributes,
            ): int {
                $item = InventoryItem::query()->findOrFail(
                    (int) $attributes['inventory_item_id'],
                );

                return UnitOfMeasure::factory()->create([
                    'organization_id' => $item->organization_id,
                ])->id;
            },
            'quantity_in_base_unit' => '1.000000',
            'active' => true,
        ];
    }
}
