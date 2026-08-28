<?php

namespace Database\Factories;

use App\Enums\BarcodeSymbology;
use App\Models\Barcode;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barcode>
 */
class BarcodeFactory extends Factory
{
    /**
     * Define a barcode owned by the same organization as its item.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'organization_id' => function (
                array $attributes,
            ): int {
                return InventoryItem::query()
                    ->findOrFail((int) $attributes['inventory_item_id'])
                    ->organization_id;
            },
            'inventory_item_unit_id' => null,
            'value' => fake()->unique()->numerify('###############'),
            'symbology' => BarcodeSymbology::Ean13,
            'is_primary' => false,
            'active' => true,
        ];
    }
}
