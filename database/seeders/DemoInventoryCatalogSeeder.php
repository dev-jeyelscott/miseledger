<?php

namespace Database\Seeders;

use App\Actions\Inventory\CreateInventoryItemUnit;
use App\Actions\Inventory\SaveInventoryBrand;
use App\Actions\Inventory\SaveInventoryItem;
use App\Actions\Inventory\SaveInventoryProduct;
use App\Actions\Inventory\SaveInventoryProductOption;
use App\Actions\Inventory\SaveInventoryProductOptionValue;
use App\Actions\Inventory\SyncInventoryItemOptionValues;
use App\Enums\InventoryItemType;
use App\Models\InventoryBrand;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class DemoInventoryCatalogSeeder extends Seeder
{
    /**
     * Seed connected product metadata, brands, families, and variant dimensions.
     */
    public function run(
        SaveInventoryBrand $saveInventoryBrand,
        SaveInventoryProduct $saveInventoryProduct,
        SaveInventoryProductOption $saveInventoryProductOption,
        SaveInventoryProductOptionValue $saveInventoryProductOptionValue,
        SaveInventoryItem $saveInventoryItem,
        CreateInventoryItemUnit $createInventoryItemUnit,
        SyncInventoryItemOptionValues $syncInventoryItemOptionValues,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        $brands = $this->seedBrands(
            $organization,
            $saveInventoryBrand,
        );

        $products = $this->seedProducts(
            $organization,
            $saveInventoryProduct,
        );

        $optionValues = $this->seedProductOptions(
            $organization,
            $products,
            $saveInventoryProductOption,
            $saveInventoryProductOptionValue,
        );

        $this->enrichOperationalItems(
            $organization,
            $brands,
            $products,
            $optionValues,
            $saveInventoryItem,
            $syncInventoryItemOptionValues,
        );

        $this->seedHotCupVariants(
            $organization,
            $brands,
            $products,
            $optionValues,
            $saveInventoryItem,
            $createInventoryItemUnit,
            $syncInventoryItemOptionValues,
        );
    }

    /**
     * Seed realistic organization-owned brands used by the demo catalog.
     *
     * @return array<string, InventoryBrand>
     */
    private function seedBrands(
        Organization $organization,
        SaveInventoryBrand $saveInventoryBrand,
    ): array {
        /** @var list<array{name: string, active: bool}> $definitions */
        $definitions = [
            ['name' => 'Sinta House', 'active' => true],
            ['name' => 'Amihan Coffee Roasters', 'active' => true],
            ['name' => 'EcoServe Foodservice', 'active' => true],
            ['name' => 'PureSpring Beverages', 'active' => true],
            ['name' => 'Metro Cola Co.', 'active' => true],
            ['name' => 'Island Harvest Foods - Legacy', 'active' => false],
        ];

        /** @var array<string, InventoryBrand> $brands */
        $brands = [];

        foreach ($definitions as $definition) {
            $brand = $saveInventoryBrand->handle(
                $organization,
                $definition,
            );

            $brands[$brand->name] = $brand;
        }

        return $brands;
    }

    /**
     * Seed product families shared by connected inventory items.
     *
     * @return array<string, InventoryProduct>
     */
    private function seedProducts(
        Organization $organization,
        SaveInventoryProduct $saveInventoryProduct,
    ): array {
        /** @var list<array{name: string, active: bool}> $definitions */
        $definitions = [
            ['name' => 'Hot Cups', 'active' => true],
            ['name' => 'Arabica Coffee Beans', 'active' => true],
            ['name' => 'Bottled Water', 'active' => true],
            ['name' => 'Canned Cola', 'active' => true],
            ['name' => 'House Cold Brew', 'active' => true],
        ];

        /** @var array<string, InventoryProduct> $products */
        $products = [];

        foreach ($definitions as $definition) {
            $product = $saveInventoryProduct->handle(
                $organization,
                $definition,
            );

            $products[$product->name] = $product;
        }

        return $products;
    }

    /**
     * Seed legitimate controlled dimensions and values for demo product families.
     *
     * @param  array<string, InventoryProduct>  $products
     * @return array<string, InventoryProductOptionValue>
     */
    private function seedProductOptions(
        Organization $organization,
        array $products,
        SaveInventoryProductOption $saveInventoryProductOption,
        SaveInventoryProductOptionValue $saveInventoryProductOptionValue,
    ): array {
        /** @var list<array{product: string, name: string, values: list<array{value: string, active: bool}>}> $definitions */
        $definitions = [
            [
                'product' => 'Hot Cups',
                'name' => 'Size',
                'values' => [
                    ['value' => '8 oz', 'active' => true],
                    ['value' => '12 oz', 'active' => true],
                    ['value' => '16 oz', 'active' => true],
                    ['value' => '20 oz', 'active' => false],
                ],
            ],
            [
                'product' => 'Arabica Coffee Beans',
                'name' => 'Roast',
                'values' => [
                    ['value' => 'Medium', 'active' => true],
                    ['value' => 'Dark', 'active' => true],
                ],
            ],
            [
                'product' => 'Arabica Coffee Beans',
                'name' => 'Pack Size',
                'values' => [
                    ['value' => '250 g', 'active' => true],
                    ['value' => '1 kg', 'active' => true],
                ],
            ],
            [
                'product' => 'Bottled Water',
                'name' => 'Bottle Size',
                'values' => [
                    ['value' => '500 ml', 'active' => true],
                    ['value' => '1 L', 'active' => true],
                ],
            ],
            [
                'product' => 'Canned Cola',
                'name' => 'Can Size',
                'values' => [
                    ['value' => '330 ml', 'active' => true],
                ],
            ],
            [
                'product' => 'House Cold Brew',
                'name' => 'Format',
                'values' => [
                    ['value' => 'Concentrate', 'active' => true],
                    ['value' => 'Ready-to-drink', 'active' => true],
                ],
            ],
        ];

        /** @var array<string, InventoryProductOptionValue> $values */
        $values = [];

        foreach ($definitions as $definition) {
            $option = $saveInventoryProductOption->handle(
                $organization,
                $products[$definition['product']],
                [
                    'name' => $definition['name'],
                    'active' => true,
                ],
            );

            foreach ($definition['values'] as $valueDefinition) {
                $value = $saveInventoryProductOptionValue->handle(
                    $organization,
                    $option,
                    $valueDefinition,
                );

                $values[
                    $this->optionValueKey(
                        $definition['product'],
                        $definition['name'],
                        $value->value,
                    )
                ] = $value;
            }
        }

        return $values;
    }

    /**
     * Connect newer master-data features to inventory items already used by
     * suppliers, purchasing, stock, transfers, counts, and recipes.
     *
     * @param  array<string, InventoryBrand>  $brands
     * @param  array<string, InventoryProduct>  $products
     * @param  array<string, InventoryProductOptionValue>  $optionValues
     */
    private function enrichOperationalItems(
        Organization $organization,
        array $brands,
        array $products,
        array $optionValues,
        SaveInventoryItem $saveInventoryItem,
        SyncInventoryItemOptionValues $syncInventoryItemOptionValues,
    ): void {
        /** @var list<array{
         *     sku: string,
         *     brand: string,
         *     product: string,
         *     model_number: string|null,
         *     manufacturer_part_number: string|null,
         *     description: string,
         *     option_values: list<string>
         * }> $definitions
         */
        $definitions = [
            [
                'sku' => 'COFFEE-BEAN',
                'brand' => 'Amihan Coffee Roasters',
                'product' => 'Arabica Coffee Beans',
                'model_number' => null,
                'manufacturer_part_number' => 'ACR-ARABICA-MED-1KG',
                'description' => 'Single-origin Arabica coffee beans roasted for Sinta espresso, iced latte, and cold brew service.',
                'option_values' => [
                    $this->optionValueKey('Arabica Coffee Beans', 'Roast', 'Medium'),
                    $this->optionValueKey('Arabica Coffee Beans', 'Pack Size', '1 kg'),
                ],
            ],
            [
                'sku' => 'CUP-12OZ',
                'brand' => 'EcoServe Foodservice',
                'product' => 'Hot Cups',
                'model_number' => 'HC-12W',
                'manufacturer_part_number' => 'ES-HC12-1000',
                'description' => 'White 12 oz paper hot cup used for dine-in takeaway coffee and milk-based drinks.',
                'option_values' => [
                    $this->optionValueKey('Hot Cups', 'Size', '12 oz'),
                ],
            ],
            [
                'sku' => 'WATER-500',
                'brand' => 'PureSpring Beverages',
                'product' => 'Bottled Water',
                'model_number' => null,
                'manufacturer_part_number' => 'PS-PW500',
                'description' => '500 ml purified bottled water sold chilled at the Makati and BGC branches.',
                'option_values' => [
                    $this->optionValueKey('Bottled Water', 'Bottle Size', '500 ml'),
                ],
            ],
            [
                'sku' => 'COLA-CAN',
                'brand' => 'Metro Cola Co.',
                'product' => 'Canned Cola',
                'model_number' => null,
                'manufacturer_part_number' => 'MC-COLA-330',
                'description' => '330 ml canned cola stocked for dine-in service and takeaway beverage orders.',
                'option_values' => [
                    $this->optionValueKey('Canned Cola', 'Can Size', '330 ml'),
                ],
            ],
            [
                'sku' => 'COLD-BREW-CONC',
                'brand' => 'Sinta House',
                'product' => 'House Cold Brew',
                'model_number' => null,
                'manufacturer_part_number' => null,
                'description' => 'House-made cold brew concentrate prepared by the commissary for branch beverage service.',
                'option_values' => [
                    $this->optionValueKey('House Cold Brew', 'Format', 'Concentrate'),
                ],
            ],
            [
                'sku' => 'COLD-BREW-BTL',
                'brand' => 'Sinta House',
                'product' => 'House Cold Brew',
                'model_number' => null,
                'manufacturer_part_number' => null,
                'description' => 'Ready-to-drink bottled house cold brew prepared from Sinta cold brew concentrate.',
                'option_values' => [
                    $this->optionValueKey('House Cold Brew', 'Format', 'Ready-to-drink'),
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $item = InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', $definition['sku'])
                ->sole();

            $item = $saveInventoryItem->handle(
                $organization,
                [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'base_unit_of_measure_id' => $item->base_unit_of_measure_id,
                    'inventory_category_id' => $item->inventory_category_id,
                    'inventory_brand_id' => $brands[$definition['brand']]->id,
                    'inventory_product_id' => $products[$definition['product']]->id,
                    'model_number' => $definition['model_number'],
                    'manufacturer_part_number' => $definition['manufacturer_part_number'],
                    'description' => $definition['description'],
                    'type' => $item->type,
                    'yield_percentage' => $item->yield_percentage,
                    'active' => $item->active,
                ],
                $item,
            );

            $syncInventoryItemOptionValues->handle(
                $organization,
                $item,
                array_map(
                    static fn (string $key): int => $optionValues[$key]->id,
                    $definition['option_values'],
                ),
            );
        }
    }

    /**
     * Seed two additional hot-cup variants so the product-family UI has a
     * realistic multi-SKU family while the existing 12 oz variant remains
     * connected to purchasing and stock history.
     *
     * @param  array<string, InventoryBrand>  $brands
     * @param  array<string, InventoryProduct>  $products
     * @param  array<string, InventoryProductOptionValue>  $optionValues
     */
    private function seedHotCupVariants(
        Organization $organization,
        array $brands,
        array $products,
        array $optionValues,
        SaveInventoryItem $saveInventoryItem,
        CreateInventoryItemUnit $createInventoryItemUnit,
        SyncInventoryItemOptionValues $syncInventoryItemOptionValues,
    ): void {
        $category = InventoryCategory::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'Packaging & Supplies')
            ->where('active', true)
            ->sole();

        $piece = $this->unit($organization, 'piece');
        $case = $this->unit($organization, 'case');

        /** @var list<array{
         *     name: string,
         *     sku: string,
         *     model_number: string,
         *     manufacturer_part_number: string,
         *     description: string,
         *     size: string
         * }> $definitions
         */
        $definitions = [
            [
                'name' => '8oz Hot Cup',
                'sku' => 'CUP-8OZ',
                'model_number' => 'HC-08W',
                'manufacturer_part_number' => 'ES-HC08-1000',
                'description' => 'White 8 oz paper hot cup for espresso-based drinks and smaller hot beverage servings.',
                'size' => '8 oz',
            ],
            [
                'name' => '16oz Hot Cup',
                'sku' => 'CUP-16OZ',
                'model_number' => 'HC-16W',
                'manufacturer_part_number' => 'ES-HC16-1000',
                'description' => 'White 16 oz paper hot cup for large takeaway coffee and hot beverage servings.',
                'size' => '16 oz',
            ],
        ];

        foreach ($definitions as $definition) {
            $item = $saveInventoryItem->handle(
                $organization,
                [
                    'name' => $definition['name'],
                    'sku' => $definition['sku'],
                    'base_unit_of_measure_id' => $piece->id,
                    'inventory_category_id' => $category->id,
                    'inventory_brand_id' => $brands['EcoServe Foodservice']->id,
                    'inventory_product_id' => $products['Hot Cups']->id,
                    'model_number' => $definition['model_number'],
                    'manufacturer_part_number' => $definition['manufacturer_part_number'],
                    'description' => $definition['description'],
                    'type' => InventoryItemType::Packaging,
                    'yield_percentage' => '100.0000',
                    'active' => true,
                ],
            );

            $createInventoryItemUnit->handle(
                $organization,
                $item,
                $case->id,
                '1000.000000',
                true,
            );

            $syncInventoryItemOptionValues->handle(
                $organization,
                $item,
                [
                    $optionValues[
                        $this->optionValueKey(
                            'Hot Cups',
                            'Size',
                            $definition['size'],
                        )
                    ]->id,
                ],
            );
        }
    }

    /**
     * Resolve one active organization-owned UOM by symbol.
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

    /**
     * Build a stable in-memory key for a product option value.
     */
    private function optionValueKey(
        string $product,
        string $option,
        string $value,
    ): string {
        return $product.'|'.$option.'|'.$value;
    }
}
