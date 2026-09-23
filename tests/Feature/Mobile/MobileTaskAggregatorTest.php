<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Mobile\MobileTaskAggregator;

function makeStorageLocationForTaskTest(Organization $organization, Location $location, string $code): StorageLocation
{
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

function makeMemberForTaskTest(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);
    $this->otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);
    $this->storageLocation = makeStorageLocationForTaskTest($this->organization, $this->location, 'A');

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->item->id,
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $this->manager = makeMemberForTaskTest($this->organization, OrganizationRole::Manager);
    $this->kitchenStaff = makeMemberForTaskTest($this->organization, OrganizationRole::KitchenStaff);

    $this->aggregator = app(MobileTaskAggregator::class);
});

function makePurchaseOrderForTaskTest(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    User $actor,
    PurchaseOrderStatus $status,
    ?string $expectedDeliveryDate,
    string $number,
): PurchaseOrder {
    return PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => $status,
        'origin' => 'desktop',
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => $expectedDeliveryDate,
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '100.00',
        'created_by' => $actor->id,
        'approved_by' => $actor->id,
        'approved_at' => now(),
    ]);
}

test('a receivable PO appears as a receive task and disappears once fully received', function () {
    $purchaseOrder = makePurchaseOrderForTaskTest(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->manager,
        PurchaseOrderStatus::Approved,
        now()->addDay()->toDateString(),
        'PO-TASK-1',
    );

    $tasks = $this->aggregator->forLocation($this->organization, $this->location, $this->manager);

    expect($tasks->where('type', 'receive')->pluck('urgency')->all())->toBe(['ready']);

    $purchaseOrder->update(['status' => PurchaseOrderStatus::Received]);

    $tasks = $this->aggregator->forLocation($this->organization, $this->location, $this->manager);

    expect($tasks->where('type', 'receive'))->toHaveCount(0);
});

test('an overdue PO ranks above a fresh draft count, which ranks above a healthy item', function () {
    makePurchaseOrderForTaskTest(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->manager,
        PurchaseOrderStatus::Approved,
        now()->subDays(3)->toDateString(),
        'PO-OVERDUE',
    );

    StockCount::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'storage_location_id' => $this->storageLocation->id,
        'number' => 'SC-DRAFT',
        'status' => StockCountStatus::Draft,
        'created_by' => $this->manager->id,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "task-test:healthy:{$this->item->id}",
        inboundUnitCost: '4.0000',
    );

    $tasks = $this->aggregator->forLocation($this->organization, $this->location, $this->manager);

    expect($tasks->pluck('urgency')->all())->toBe(['overdue', 'ready']);
    expect($tasks->first()->type)->toBe('receive');
});

test('a kitchen-staff account sees only restock tasks even when receive, count, and transfer records exist', function () {
    makePurchaseOrderForTaskTest(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->manager,
        PurchaseOrderStatus::Approved,
        null,
        'PO-KITCHEN',
    );

    StockCount::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'storage_location_id' => $this->storageLocation->id,
        'number' => 'SC-KITCHEN',
        'status' => StockCountStatus::Draft,
        'created_by' => $this->manager->id,
    ]);

    StockTransfer::query()->create([
        'organization_id' => $this->organization->id,
        'from_location_id' => $this->location->id,
        'from_storage_location_id' => $this->storageLocation->id,
        'to_location_id' => $this->otherLocation->id,
        'to_storage_location_id' => makeStorageLocationForTaskTest($this->organization, $this->otherLocation, 'B')->id,
        'number' => 'ST-KITCHEN',
        'status' => StockTransferStatus::Draft,
        'created_by' => $this->manager->id,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "task-test:kitchen:opening:{$this->item->id}",
        inboundUnitCost: '4.0000',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "task-test:kitchen:adjustment:{$this->item->id}",
    );

    $managerTasks = $this->aggregator->forLocation($this->organization, $this->location, $this->manager);
    expect($managerTasks->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['count', 'receive', 'restock', 'ship']);

    $kitchenTasks = $this->aggregator->forLocation($this->organization, $this->location, $this->kitchenStaff);
    expect($kitchenTasks->pluck('type')->unique()->values()->all())->toBe(['restock']);
});

test('a task at a different location or organization never leaks in', function () {
    makePurchaseOrderForTaskTest(
        $this->organization,
        $this->otherLocation,
        $this->supplier,
        $this->manager,
        PurchaseOrderStatus::Approved,
        null,
        'PO-OTHER-LOCATION',
    );

    $otherOrganization = Organization::factory()->create();
    $otherOrgLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);
    $otherOrgSupplier = Supplier::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);
    $otherOrgManager = makeMemberForTaskTest($otherOrganization, OrganizationRole::Manager);

    makePurchaseOrderForTaskTest(
        $otherOrganization,
        $otherOrgLocation,
        $otherOrgSupplier,
        $otherOrgManager,
        PurchaseOrderStatus::Approved,
        null,
        'PO-OTHER-ORG',
    );

    $tasks = $this->aggregator->forLocation($this->organization, $this->location, $this->manager);

    expect($tasks)->toHaveCount(0);
});

test('receiving progress narrows the receive task set as goods receipts move a PO through its lifecycle', function () {
    $purchaseOrder = makePurchaseOrderForTaskTest(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->manager,
        PurchaseOrderStatus::Approved,
        null,
        'PO-LIFECYCLE',
    );

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_item_id' => $this->supplierItem->id,
        'inventory_item_id' => $this->item->id,
        'item_name_snapshot' => $this->item->name,
        'supplier_sku_snapshot' => $this->supplierItem->supplier_sku,
        'ordered_quantity' => '10.000000',
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '10.000000',
        'unit_price' => '10.0000',
        'line_total' => '100.00',
        'received_base_quantity' => '0.000000',
    ]);

    expect(
        $this->aggregator
            ->forLocation($this->organization, $this->location, $this->manager)
            ->where('type', 'receive')
            ->first()
            ->urgency,
    )->toBe('ready');

    $purchaseOrder->update(['status' => PurchaseOrderStatus::PartiallyReceived]);

    expect(
        $this->aggregator
            ->forLocation($this->organization, $this->location, $this->manager)
            ->where('type', 'receive')
            ->first()
            ->urgency,
    )->toBe('in_progress');

    $purchaseOrder->update(['status' => PurchaseOrderStatus::Received]);

    expect(
        $this->aggregator
            ->forLocation($this->organization, $this->location, $this->manager)
            ->where('type', 'receive'),
    )->toHaveCount(0);
});
