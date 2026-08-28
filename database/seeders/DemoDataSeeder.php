<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryProduct;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\SupplierItem;
use App\Models\WasteRecord;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private const DEMO_ORGANIZATION_NAME = 'Sinta Kitchen & Café';

    /**
     * Seed only missing demo scenarios without replaying completed business
     * history or destructively rebuilding existing demo data.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $this->seedDemoScenarioOnce(
            DemoOrganizationSeeder::class,
            static fn (): bool => Organization::query()
                ->where('name', self::DEMO_ORGANIZATION_NAME)
                ->exists(),
        );

        $organization = Organization::query()
            ->where('name', self::DEMO_ORGANIZATION_NAME)
            ->sole();

        $this->seedDemoScenarioOnce(
            DemoInventorySeeder::class,
            static fn (): bool => InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', 'MANGO-PUREE')
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoInventoryCatalogSeeder::class,
            static fn (): bool => InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', 'CUP-16OZ')
                ->exists()
                && InventoryProduct::query()
                    ->where('organization_id', $organization->id)
                    ->where('name', 'Hot Cups')
                    ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoBarcodeSeeder::class,
            static fn (): bool => InventoryItemBarcode::query()
                ->where('organization_id', $organization->id)
                ->where('barcode', 'ECOSERVE-HC16-CASE1000')
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoSupplierSeeder::class,
            static fn (): bool => SupplierItem::query()
                ->where('organization_id', $organization->id)
                ->where('supplier_sku', 'PFP-GLOVE-100')
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoStockLedgerSeeder::class,
            static fn (): bool => StockMovement::query()
                ->where('organization_id', $organization->id)
                ->where(
                    'idempotency_key',
                    'inventory_adjustment:demo:bgc-cola-opening-correction',
                )
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoPurchasingSeeder::class,
            static fn (): bool => PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->where('number', 'PO-2026-0066')
                ->exists()
                && GoodsReceipt::query()
                    ->where('organization_id', $organization->id)
                    ->where('number', 'GR-2026-0051')
                    ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoWasteSeeder::class,
            static fn (): bool => WasteRecord::query()
                ->where('organization_id', $organization->id)
                ->where(
                    'operation_id',
                    '00000000-0000-4000-8000-000000000106',
                )
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoStockTransferSeeder::class,
            static fn (): bool => StockTransfer::query()
                ->where('organization_id', $organization->id)
                ->where('number', 'ST-2026-0022')
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoStockCountSeeder::class,
            static fn (): bool => StockCount::query()
                ->where('organization_id', $organization->id)
                ->where('number', 'SC-BGC-CANCELLED-20260818')
                ->exists(),
        );

        $this->seedDemoScenarioOnce(
            DemoRecipeSeeder::class,
            static fn (): bool => Recipe::query()
                ->where('organization_id', $organization->id)
                ->where('code', 'MANGO-SHAKE-SEASONAL')
                ->exists(),
        );
    }

    /**
     * Execute one demo scenario only when its completion marker is absent,
     * rolling back partial writes if the scenario fails.
     *
     * @param  class-string<Seeder>  $seederClass
     * @param  Closure(): bool  $isComplete
     */
    private function seedDemoScenarioOnce(
        string $seederClass,
        Closure $isComplete,
    ): void {
        if ($isComplete()) {
            return;
        }

        DB::transaction(function () use ($seederClass): void {
            $this->call([$seederClass]);
        });
    }
}
