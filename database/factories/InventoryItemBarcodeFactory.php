<?php

namespace Database\Factories;

use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemBarcode>
 */
class InventoryItemBarcodeFactory extends Factory
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
            'organization_id' => function (array $attributes): int {
                return InventoryItem::query()
                    ->findOrFail(
                        (int) $attributes['inventory_item_id'],
                    )
                    ->organization_id;
            },
            'inventory_item_unit_id' => null,
            'barcode' => fake()
                ->unique()
                ->numerify('#############'),
            'symbology' => BarcodeSymbology::Ean13,
            'primary' => false,
            'active' => true,
        ];
    }
}
