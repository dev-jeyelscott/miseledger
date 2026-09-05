<?php

use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->storageLocation = StorageLocation::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'name' => 'Main storage',
        'code' => 'MAIN',
        'active' => true,
    ]);
    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);
    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'active' => true,
    ]);
    $supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
    ]);
    $this->actor = User::factory()->create();
    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);
    $this->purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $supplier->id,
        'number' => 'PO-PERFORMANCE',
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'subtotal' => '200.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '200.00',
        'created_by' => $this->actor->id,
        'approved_by' => $this->actor->id,
        'approved_at' => now(),
    ]);

    $lines = collect();
    foreach (range(1, 100) as $index) {
        $lines->push(PurchaseOrderLine::query()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_item_id' => $supplierItem->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'item_name_snapshot' => $this->inventoryItem->name,
            'supplier_sku_snapshot' => $supplierItem->supplier_sku,
            'ordered_quantity' => '10.000000',
            'purchase_unit_of_measure_id' => $this->baseUnit->id,
            'base_quantity' => '10.000000',
            'unit_price' => '10.0000',
            'line_total' => '100.00',
            'received_base_quantity' => '0.000000',
        ]));
    }

    $this->receipt = GoodsReceipt::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'purchase_order_id' => $this->purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'number' => 'GR-PERFORMANCE',
        'status' => GoodsReceiptStatus::Draft,
    ]);

    foreach ($lines as $line) {
        GoodsReceiptLine::query()->create([
            'goods_receipt_id' => $this->receipt->id,
            'purchase_order_line_id' => $line->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'storage_location_id' => $this->storageLocation->id,
            'received_quantity' => '1.000000',
            'received_unit_of_measure_id' => $this->baseUnit->id,
            'base_quantity' => '1.000000',
            'unit_cost' => '10.0000',
            'total_cost' => '10.0000',
        ]);
    }
});

test('finalization batch-resolves receipt dependencies', function (): void {
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->receipt,
    );

    $queries = collect($queries);

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "purchase_order_lines"')
        && str_contains($sql, 'for update'))->count())
        ->toBe(1)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "inventory_items"'))->count())
        ->toBe(1)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "units_of_measure"'))->count())
        ->toBe(1)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "storage_locations"'))->count())
        ->toBe(1)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "organization_memberships"'))->count())
        ->toBe(0)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "stock_balances"')
            && str_contains($sql, 'for update'))->count())
        ->toBe(1)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "stock_movements"')
            && str_starts_with($sql, 'select')
            && str_contains($sql, 'idempotency_key'))->count())
        ->toBe(1)
        ->and($this->receipt->refresh()->status)->toBe(GoodsReceiptStatus::Finalized);
});

test('finalization acquires inventory locks in the normal stock mutation order', function (): void {
    $lockQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$lockQueries): void {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'for update')) {
            $lockQueries[] = $sql;
        }
    });

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->receipt,
    );

    $storageLock = collect($lockQueries)->search(
        fn (string $sql): bool => str_contains($sql, 'from "storage_locations"'),
    );
    $itemLock = collect($lockQueries)->search(
        fn (string $sql): bool => str_contains($sql, 'from "inventory_items"'),
    );
    $unitLock = collect($lockQueries)->search(
        fn (string $sql): bool => str_contains($sql, 'from "units_of_measure"'),
    );

    expect($storageLock)->toBeInt()
        ->and($itemLock)->toBeInt()->toBeGreaterThan($storageLock)
        ->and($unitLock)->toBeInt()->toBeGreaterThan($itemLock);
});

test('preload rejects inactive accepted dependencies before writing movements', function (): void {
    $this->inventoryItem->update(['active' => false]);

    expect(fn (): GoodsReceipt => app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->receipt,
    ))->toThrow(ValidationException::class);
});
