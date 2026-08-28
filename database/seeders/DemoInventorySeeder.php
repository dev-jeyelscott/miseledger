<?php

namespace Database\Seeders;

use App\Actions\Inventory\CreateInventoryItemUnit;
use App\Actions\Inventory\SaveInventoryCategory;
use App\Actions\Inventory\SaveInventoryItem;
use App\Enums\InventoryItemType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    /**
     * Seed realistic restaurant inventory master data and operational UOMs.
     */
    public function run(
        SaveInventoryCategory $saveInventoryCategory,
        SaveInventoryItem $saveInventoryItem,
        CreateInventoryItemUnit $createInventoryItemUnit,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        /** @var array<string, InventoryCategory> $categories */
        $categories = [];

        foreach ([
            ['name' => 'Proteins', 'active' => true],
            ['name' => 'Produce', 'active' => true],
            ['name' => 'Dry Goods', 'active' => true],
            ['name' => 'Dairy & Eggs', 'active' => true],
            ['name' => 'Sauces & Seasonings', 'active' => true],
            ['name' => 'Beverages', 'active' => true],
            ['name' => 'Packaging & Supplies', 'active' => true],
            ['name' => 'Cleaning & Consumables', 'active' => true],
            ['name' => 'Seasonal Specials', 'active' => false],
        ] as $definition) {
            $category = $saveInventoryCategory->handle(
                $organization,
                $definition,
            );

            $categories[$category->name] = $category;
        }

        /** @var list<array{name: string, sku: string, unit: string, category: string, type: InventoryItemType, yield: string, active: bool}> $items */
        $items = [
            ['name' => 'Chicken Thigh Fillet', 'sku' => 'CHK-THIGH', 'unit' => 'kg', 'category' => 'Proteins', 'type' => InventoryItemType::Ingredient, 'yield' => '92.0000', 'active' => true],
            ['name' => 'Pork Belly', 'sku' => 'PORK-BELLY', 'unit' => 'kg', 'category' => 'Proteins', 'type' => InventoryItemType::Ingredient, 'yield' => '95.0000', 'active' => true],
            ['name' => 'Beef Short Plate', 'sku' => 'BEEF-SHORT', 'unit' => 'kg', 'category' => 'Proteins', 'type' => InventoryItemType::Ingredient, 'yield' => '94.0000', 'active' => true],

            ['name' => 'Fresh Garlic', 'sku' => 'GARLIC', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '88.0000', 'active' => true],
            ['name' => 'Red Onion', 'sku' => 'RED-ONION', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '90.0000', 'active' => true],
            ['name' => 'Roma Tomato', 'sku' => 'TOMATO-ROMA', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '96.0000', 'active' => true],
            ['name' => 'Calamansi', 'sku' => 'CALAMANSI', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '55.0000', 'active' => true],
            ['name' => 'Green Chili', 'sku' => 'GREEN-CHILI', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '95.0000', 'active' => true],
            ['name' => 'Fresh Cilantro', 'sku' => 'CILANTRO', 'unit' => 'kg', 'category' => 'Produce', 'type' => InventoryItemType::Ingredient, 'yield' => '85.0000', 'active' => true],

            ['name' => 'Jasmine Rice', 'sku' => 'JASMINE-RICE', 'unit' => 'kg', 'category' => 'Dry Goods', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'All-Purpose Flour', 'sku' => 'AP-FLOUR', 'unit' => 'kg', 'category' => 'Dry Goods', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Brown Sugar', 'sku' => 'BROWN-SUGAR', 'unit' => 'kg', 'category' => 'Dry Goods', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Cornstarch', 'sku' => 'CORNSTARCH', 'unit' => 'kg', 'category' => 'Dry Goods', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],

            ['name' => 'Large Eggs', 'sku' => 'EGG-LARGE', 'unit' => 'piece', 'category' => 'Dairy & Eggs', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Unsalted Butter', 'sku' => 'BUTTER', 'unit' => 'kg', 'category' => 'Dairy & Eggs', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Fresh Milk', 'sku' => 'FRESH-MILK', 'unit' => 'l', 'category' => 'Dairy & Eggs', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'All-Purpose Cream', 'sku' => 'AP-CREAM', 'unit' => 'l', 'category' => 'Dairy & Eggs', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],

            ['name' => 'Premium Soy Sauce', 'sku' => 'SOY-SAUCE', 'unit' => 'l', 'category' => 'Sauces & Seasonings', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Cane Vinegar', 'sku' => 'CANE-VINEGAR', 'unit' => 'l', 'category' => 'Sauces & Seasonings', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Canola Cooking Oil', 'sku' => 'COOKING-OIL', 'unit' => 'l', 'category' => 'Sauces & Seasonings', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Fish Sauce', 'sku' => 'FISH-SAUCE', 'unit' => 'l', 'category' => 'Sauces & Seasonings', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],

            ['name' => 'Arabica Coffee Beans', 'sku' => 'COFFEE-BEAN', 'unit' => 'kg', 'category' => 'Beverages', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Cola 330ml Can', 'sku' => 'COLA-CAN', 'unit' => 'can', 'category' => 'Beverages', 'type' => InventoryItemType::FinishedItem, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Bottled Water 500ml', 'sku' => 'WATER-500', 'unit' => 'bottle', 'category' => 'Beverages', 'type' => InventoryItemType::FinishedItem, 'yield' => '100.0000', 'active' => true],

            ['name' => '750ml Takeout Bowl', 'sku' => 'BOWL-750', 'unit' => 'piece', 'category' => 'Packaging & Supplies', 'type' => InventoryItemType::Packaging, 'yield' => '100.0000', 'active' => true],
            ['name' => '12oz Hot Cup', 'sku' => 'CUP-12OZ', 'unit' => 'piece', 'category' => 'Packaging & Supplies', 'type' => InventoryItemType::Packaging, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Medium Kraft Paper Bag', 'sku' => 'PAPER-BAG-M', 'unit' => 'piece', 'category' => 'Packaging & Supplies', 'type' => InventoryItemType::Packaging, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Dinner Napkin', 'sku' => 'NAPKIN', 'unit' => 'piece', 'category' => 'Packaging & Supplies', 'type' => InventoryItemType::Packaging, 'yield' => '100.0000', 'active' => true],

            ['name' => 'Dishwashing Liquid', 'sku' => 'DISHWASH-LIQ', 'unit' => 'l', 'category' => 'Cleaning & Consumables', 'type' => InventoryItemType::Consumable, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Nitrile Gloves - Medium', 'sku' => 'GLOVES-M', 'unit' => 'piece', 'category' => 'Cleaning & Consumables', 'type' => InventoryItemType::Consumable, 'yield' => '100.0000', 'active' => true],

            ['name' => 'Cold Brew Concentrate', 'sku' => 'COLD-BREW-CONC', 'unit' => 'l', 'category' => 'Beverages', 'type' => InventoryItemType::PreparedItem, 'yield' => '100.0000', 'active' => true],
            ['name' => 'House Cold Brew Bottle', 'sku' => 'COLD-BREW-BTL', 'unit' => 'bottle', 'category' => 'Beverages', 'type' => InventoryItemType::FinishedItem, 'yield' => '100.0000', 'active' => true],
            ['name' => 'Mango Purée - Seasonal', 'sku' => 'MANGO-PUREE', 'unit' => 'l', 'category' => 'Beverages', 'type' => InventoryItemType::Ingredient, 'yield' => '100.0000', 'active' => false],
        ];

        foreach ($items as $definition) {
            $saveInventoryItem->handle(
                $organization,
                [
                    'name' => $definition['name'],
                    'sku' => $definition['sku'],
                    'base_unit_of_measure_id' => $this
                        ->unit($organization, $definition['unit'])
                        ->id,
                    'inventory_category_id' => $categories[
                        $definition['category']
                    ]->id,
                    'inventory_brand_id' => null,
                    'inventory_product_id' => null,
                    'model_number' => null,
                    'manufacturer_part_number' => null,
                    'description' => null,
                    'type' => $definition['type'],
                    'yield_percentage' => $definition['yield'],
                    'active' => $definition['active'],
                ],
            );
        }

        /** @var list<array{sku: string, unit: string, quantity: string}> $conversions */
        $conversions = [
            ['sku' => 'CHK-THIGH', 'unit' => 'case', 'quantity' => '10.000000'],
            ['sku' => 'PORK-BELLY', 'unit' => 'case', 'quantity' => '10.000000'],
            ['sku' => 'JASMINE-RICE', 'unit' => 'sack', 'quantity' => '25.000000'],
            ['sku' => 'AP-FLOUR', 'unit' => 'sack', 'quantity' => '25.000000'],
            ['sku' => 'BROWN-SUGAR', 'unit' => 'sack', 'quantity' => '25.000000'],
            ['sku' => 'EGG-LARGE', 'unit' => 'tray', 'quantity' => '30.000000'],
            ['sku' => 'COOKING-OIL', 'unit' => 'case', 'quantity' => '16.000000'],
            ['sku' => 'COFFEE-BEAN', 'unit' => 'bag', 'quantity' => '1.000000'],
            ['sku' => 'COLA-CAN', 'unit' => 'case', 'quantity' => '24.000000'],
            ['sku' => 'WATER-500', 'unit' => 'case', 'quantity' => '24.000000'],
            ['sku' => 'BOWL-750', 'unit' => 'case', 'quantity' => '500.000000'],
            ['sku' => 'CUP-12OZ', 'unit' => 'case', 'quantity' => '1000.000000'],
            ['sku' => 'PAPER-BAG-M', 'unit' => 'pack', 'quantity' => '100.000000'],
            ['sku' => 'NAPKIN', 'unit' => 'pack', 'quantity' => '500.000000'],
            ['sku' => 'GLOVES-M', 'unit' => 'box', 'quantity' => '100.000000'],
        ];

        foreach ($conversions as $conversion) {
            $inventoryItem = InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', $conversion['sku'])
                ->sole();

            $createInventoryItemUnit->handle(
                $organization,
                $inventoryItem,
                $this->unit($organization, $conversion['unit'])->id,
                $conversion['quantity'],
                true,
            );
        }
    }

    /**
     * Resolve one tenant-owned standard UOM by its stable symbol.
     */
    private function unit(
        Organization $organization,
        string $symbol,
    ): UnitOfMeasure {
        return UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('symbol', $symbol)
            ->where('active', true)
            ->sole();
    }
}
