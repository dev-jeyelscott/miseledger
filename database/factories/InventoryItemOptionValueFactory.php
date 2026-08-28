<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryItemOptionValue;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemOptionValue>
 */
class InventoryItemOptionValueFactory extends Factory
{
    /**
     * Define an association between a variant item and an option value
     * from the same organization and product family.
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
            'inventory_product_option_value_id' => function (
                array $attributes,
            ): int {
                $item = InventoryItem::query()->findOrFail(
                    (int) $attributes['inventory_item_id'],
                );

                $product = $item->inventory_product_id === null
                    ? InventoryProduct::factory()->create([
                        'organization_id' => $item->organization_id,
                    ])
                    : InventoryProduct::query()->findOrFail(
                        $item->inventory_product_id,
                    );

                if ($item->inventory_product_id === null) {
                    $item->update([
                        'inventory_product_id' => $product->id,
                    ]);
                }

                $option = InventoryProductOption::factory()->create([
                    'organization_id' => $item->organization_id,
                    'inventory_product_id' => $product->id,
                ]);

                return InventoryProductOptionValue::factory()->create([
                    'organization_id' => $item->organization_id,
                    'inventory_product_option_id' => $option->id,
                ])->id;
            },
        ];
    }
}
