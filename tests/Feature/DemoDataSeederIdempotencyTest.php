<?php

use App\Models\GoodsReceipt;
use App\Models\InventoryBrand;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemOptionValue;
use App\Models\InventoryProduct;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Models\WasteRecord;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoOrganizationSeeder;
use Database\Seeders\DemoPurchasingSeeder;
use Database\Seeders\DemoRecipeSeeder;
use Database\Seeders\DemoStockCountSeeder;
use Database\Seeders\DemoStockLedgerSeeder;
use Database\Seeders\DemoStockTransferSeeder;
use Database\Seeders\DemoSupplierSeeder;
use Database\Seeders\DemoWasteSeeder;

test('demo data seeder upgrades legacy demo data without replaying completed history', function () {
    $this->seed(DemoOrganizationSeeder::class);
    $this->seed(DemoInventorySeeder::class);
    $this->seed(DemoSupplierSeeder::class);
    $this->seed(DemoStockLedgerSeeder::class);
    $this->seed(DemoPurchasingSeeder::class);
    $this->seed(DemoWasteSeeder::class);
    $this->seed(DemoStockTransferSeeder::class);
    $this->seed(DemoStockCountSeeder::class);
    $this->seed(DemoRecipeSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Sinta Kitchen & Café')
        ->sole();

    /**
     * Capture the authoritative projected stock state for comparison across
     * repeated demo-seeder executions.
     *
     * @return list<array<string, int|string|null>>
     */
    $stockSnapshot = static function (
        Organization $organization,
    ): array {
        return StockBalance::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StockBalance $balance): array => [
                    'id' => $balance->id,
                    'quantity_on_hand' => $balance->quantity_on_hand,
                    'average_unit_cost' => $balance->average_unit_cost,
                    'inventory_value' => $balance->inventory_value,
                    'last_movement_at' => $balance
                        ->last_movement_at
                        ?->toISOString(),
                ],
            )
            ->all();
    };

    $legacyCounts = [
        'users' => User::query()->count(),
        'organizations' => Organization::query()->count(),
        'suppliers' => Supplier::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'supplier_items' => SupplierItem::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'purchase_orders' => PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'goods_receipts' => GoodsReceipt::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_movements' => StockMovement::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'waste_records' => WasteRecord::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_transfers' => StockTransfer::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_counts' => StockCount::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'recipes' => Recipe::query()
            ->where('organization_id', $organization->id)
            ->count(),
    ];

    $legacyStock = $stockSnapshot($organization);

    $this->seed(DemoDataSeeder::class);

    expect(User::query()->count())
        ->toBe($legacyCounts['users'])
        ->and(Organization::query()->count())
        ->toBe($legacyCounts['organizations'])
        ->and(
            Supplier::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['suppliers'])
        ->and(
            SupplierItem::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['supplier_items'])
        ->and(
            PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['purchase_orders'])
        ->and(
            GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['goods_receipts'])
        ->and(
            StockMovement::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['stock_movements'])
        ->and(
            WasteRecord::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['waste_records'])
        ->and(
            StockTransfer::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['stock_transfers'])
        ->and(
            StockCount::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['stock_counts'])
        ->and(
            Recipe::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe($legacyCounts['recipes'])
        ->and($stockSnapshot($organization))
        ->toBe($legacyStock);

    expect(
        InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('sku', 'CUP-16OZ')
            ->exists(),
    )
        ->toBeTrue()
        ->and(
            InventoryItemBarcode::query()
                ->where('organization_id', $organization->id)
                ->where('barcode', 'ECOSERVE-HC16-CASE1000')
                ->exists(),
        )
        ->toBeTrue();

    $extendedCounts = [
        'users' => User::query()->count(),
        'organizations' => Organization::query()->count(),
        'items' => InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'brands' => InventoryBrand::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'products' => InventoryProduct::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'option_values' => InventoryItemOptionValue::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'barcodes' => InventoryItemBarcode::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'suppliers' => Supplier::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'purchase_orders' => PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_movements' => StockMovement::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'waste_records' => WasteRecord::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_transfers' => StockTransfer::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_counts' => StockCount::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'recipes' => Recipe::query()
            ->where('organization_id', $organization->id)
            ->count(),
    ];

    $extendedStock = $stockSnapshot($organization);

    $this->seed(DemoDataSeeder::class);

    expect([
        'users' => User::query()->count(),
        'organizations' => Organization::query()->count(),
        'items' => InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'brands' => InventoryBrand::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'products' => InventoryProduct::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'option_values' => InventoryItemOptionValue::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'barcodes' => InventoryItemBarcode::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'suppliers' => Supplier::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'purchase_orders' => PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_movements' => StockMovement::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'waste_records' => WasteRecord::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_transfers' => StockTransfer::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'stock_counts' => StockCount::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'recipes' => Recipe::query()
            ->where('organization_id', $organization->id)
            ->count(),
    ])
        ->toBe($extendedCounts)
        ->and($stockSnapshot($organization))
        ->toBe($extendedStock);
});
