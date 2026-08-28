<?php

namespace Database\Seeders;

use App\Actions\Inventory\CreateBarcode;
use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DemoBarcodeSeeder extends Seeder
{
    /**
     * Seed realistic item and purchase-unit barcodes for scanner-ready demos.
     */
    public function run(CreateBarcode $createBarcode): void
    {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        /** @var list<array{
         *     sku: string,
         *     barcode: string,
         *     symbology: BarcodeSymbology,
         *     unit: string|null,
         *     primary: bool,
         *     active: bool
         * }> $definitions
         */
        $definitions = [
            ['sku' => 'WATER-500', 'barcode' => '4801234567897', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'WATER-500', 'barcode' => 'PS-WATER500-CASE24', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'case', 'primary' => false, 'active' => true],
            ['sku' => 'WATER-500', 'barcode' => 'LEGACY-WATER-500-2025', 'symbology' => BarcodeSymbology::Other, 'unit' => null, 'primary' => false, 'active' => false],
            ['sku' => 'COLA-CAN', 'barcode' => '4801234567903', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'COLA-CAN', 'barcode' => 'MC-COLA330-CASE24', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'case', 'primary' => false, 'active' => true],
            ['sku' => 'COFFEE-BEAN', 'barcode' => '4801234567941', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'COFFEE-BEAN', 'barcode' => 'AMIHAN-ARABICA-BAG-1KG', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'bag', 'primary' => false, 'active' => true],
            ['sku' => 'CUP-8OZ', 'barcode' => '4801234567910', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'CUP-8OZ', 'barcode' => 'ECOSERVE-HC08-CASE1000', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'case', 'primary' => false, 'active' => true],
            ['sku' => 'CUP-12OZ', 'barcode' => '4801234567927', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'CUP-12OZ', 'barcode' => 'ECOSERVE-HC12-CASE1000', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'case', 'primary' => false, 'active' => true],
            ['sku' => 'CUP-16OZ', 'barcode' => '4801234567934', 'symbology' => BarcodeSymbology::Ean13, 'unit' => null, 'primary' => true, 'active' => true],
            ['sku' => 'CUP-16OZ', 'barcode' => 'ECOSERVE-HC16-CASE1000', 'symbology' => BarcodeSymbology::Code128, 'unit' => 'case', 'primary' => false, 'active' => true],
        ];

        foreach ($definitions as $definition) {
            $item = InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', $definition['sku'])
                ->sole();

            $itemUnitId = $definition['unit'] === null
                ? null
                : $this->itemUnit(
                    $item,
                    $definition['unit'],
                )->id;

            $createBarcode->handle(
                $organization,
                $item,
                $definition['barcode'],
                $definition['symbology'],
                $itemUnitId,
                $definition['primary'],
                $definition['active'],
            );
        }
    }

    /**
     * Resolve one active alternate item unit by its UOM symbol.
     */
    private function itemUnit(
        InventoryItem $inventoryItem,
        string $symbol,
    ): InventoryItemUnit {
        return InventoryItemUnit::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('active', true)
            ->whereHas(
                'unitOfMeasure',
                static fn ($query) => $query
                    ->where('symbol', $symbol)
                    ->where('active', true),
            )
            ->sole();
    }
}
