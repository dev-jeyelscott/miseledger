<?php

namespace Database\Seeders;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed a single deterministic organization owner plus minimal purchasing
 * and inventory master data for the Playwright E2E harness. This seeder is
 * never called by DatabaseSeeder::run() and must only run against an
 * isolated, disposable test database.
 */
class E2ETestSeeder extends Seeder
{
    public const string EMAIL = 'e2e-owner@miseledger.test';

    public const string PASSWORD = 'password';

    public function run(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'E2E Test Kitchen',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'E2E Owner',
            'email' => self::EMAIL,
        ]);

        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Owner,
        ]);

        $location = Location::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Main Kitchen',
            'code' => 'MAIN',
        ]);

        $storageLocation = new StorageLocation;
        $storageLocation->organization_id = $organization->id;
        $storageLocation->location_id = $location->id;
        $storageLocation->name = 'Main Storage';
        $storageLocation->code = 'MAIN';
        $storageLocation->active = true;
        $storageLocation->save();

        $unit = UnitOfMeasure::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
        ]);

        $item = InventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'E2E Test Ingredient',
            'sku' => 'E2E-0001',
        ]);

        app(RecordStockMovement::class)->handle(
            organization: $organization,
            location: $location,
            storageLocation: $storageLocation,
            inventoryItem: $item,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '25',
            baseUnitOfMeasure: $unit,
            referenceType: 'opening_balance',
            referenceId: $item->id,
            occurredAt: now()->subDay(),
            idempotencyKey: 'e2e-seed:opening-balance',
            inboundUnitCost: '2.5000',
        );

        $lowStockItem = InventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'E2E Low Stock Ingredient',
            'sku' => 'E2E-0002',
        ]);

        app(RecordStockMovement::class)->handle(
            organization: $organization,
            location: $location,
            storageLocation: $storageLocation,
            inventoryItem: $lowStockItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '5',
            baseUnitOfMeasure: $unit,
            referenceType: 'opening_balance',
            referenceId: $lowStockItem->id,
            occurredAt: now()->subDay(),
            idempotencyKey: 'e2e-seed:low-stock-opening-balance',
            inboundUnitCost: '2.5000',
        );

        app(RecordStockMovement::class)->handle(
            organization: $organization,
            location: $location,
            storageLocation: $storageLocation,
            inventoryItem: $lowStockItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '-5',
            baseUnitOfMeasure: $unit,
            referenceType: 'manual_adjustment',
            referenceId: $lowStockItem->id,
            occurredAt: now(),
            idempotencyKey: 'e2e-seed:low-stock-adjustment',
        );

        $supplier = Supplier::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'E2E Test Supplier',
            'code' => 'E2ESUP',
        ]);

        $supplierItem = SupplierItem::factory()->create([
            'organization_id' => $organization->id,
            'supplier_id' => $supplier->id,
            'inventory_item_id' => $item->id,
            'supplier_sku' => 'E2E-SUP-SKU',
            'purchase_unit_of_measure_id' => $unit->id,
            'base_quantity' => '1.000000',
            'current_price' => '2.5000',
            'currency' => 'PHP',
            'active' => true,
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-E2E-0001',
            'status' => PurchaseOrderStatus::Approved,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => null,
            'subtotal' => '25.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'total' => '25.00',
            'notes' => null,
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_item_id' => $supplierItem->id,
            'inventory_item_id' => $item->id,
            'item_name_snapshot' => $item->name,
            'supplier_sku_snapshot' => $supplierItem->supplier_sku,
            'ordered_quantity' => '10.000000',
            'purchase_unit_of_measure_id' => $unit->id,
            'base_quantity' => '10.000000',
            'unit_price' => '2.5000',
            'line_total' => '25.00',
            'received_base_quantity' => '0.000000',
        ]);
    }
}
