<?php

use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptNonStockLine;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Create one active storage destination for rejected/damaged receiving tests.
 */
function createNonStockReceivingStorageForTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

/**
 * Save one receiving row with independent accepted/rejected/damaged quantities.
 */
function saveDispositionReceiptForTest(
    Organization $organization,
    User $actor,
    PurchaseOrder $purchaseOrder,
    PurchaseOrderLine $purchaseOrderLine,
    ?StorageLocation $storageLocation,
    ?UnitOfMeasure $acceptedUnit,
    ?UnitOfMeasure $rejectedUnit,
    ?UnitOfMeasure $damagedUnit,
    string $number,
    string $acceptedQuantity,
    string $rejectedQuantity,
    string $damagedQuantity,
    ?GoodsReceipt $goodsReceipt = null,
): GoodsReceipt {
    return app(SaveGoodsReceipt::class)->handle(
        $organization,
        $actor,
        $purchaseOrder,
        [
            'number' => $number,
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $purchaseOrderLine->id,
                    'storage_location_id' => $storageLocation?->id,
                    'received_quantity' => $acceptedQuantity,
                    'received_unit_of_measure_id' => $acceptedUnit?->id,
                    'rejected_quantity' => $rejectedQuantity,
                    'rejected_unit_of_measure_id' => $rejectedUnit?->id,
                    'damaged_quantity' => $damagedQuantity,
                    'damaged_unit_of_measure_id' => $damagedUnit?->id,
                    'notes' => 'Supplier delivery inspection.',
                ],
            ],
        ],
        $goodsReceipt,
    );
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation = createNonStockReceivingStorageForTest(
        $this->organization,
        $this->location,
        'NON-STOCK-MAIN',
    );

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Each',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Inspection Test Item',
        'sku' => 'INSPECTION-TEST',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Inspection Test Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-INSPECTION-TEST',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
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
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-INSPECTION-TEST',
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '100.00',
        'notes' => null,
        'created_by' => $this->actor->id,
        'approved_by' => $this->actor->id,
        'approved_at' => now(),
    ]);

    $this->purchaseOrderLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'supplier_item_id' => $this->supplierItem->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'item_name_snapshot' => $this->inventoryItem->name,
        'supplier_sku_snapshot' => $this->supplierItem->supplier_sku,
        'ordered_quantity' => '10.000000',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '10.000000',
        'unit_price' => '10.0000',
        'line_total' => '100.00',
        'received_base_quantity' => '0.000000',
    ]);
});

test('accepted plus rejected retains non-stock evidence and moves accepted stock only', function () {
    $receipt = saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        $this->baseUnit,
        null,
        'GR-ACCEPTED-REJECTED',
        '6',
        '2',
        '0',
    );

    expect($receipt->lines()->count())
        ->toBe(1)
        ->and($receipt->nonStockLines()->count())
        ->toBe(1);

    $evidence = GoodsReceiptNonStockLine::query()->sole();

    expect($evidence->rejected_quantity)
        ->toBe('2.000000')
        ->and($evidence->rejected_base_quantity)
        ->toBe('2.000000')
        ->and($evidence->goods_receipt_line_id)
        ->not->toBeNull();

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $receipt,
    );

    $movement = StockMovement::query()->sole();

    expect($movement->type)
        ->toBe(StockMovementType::PurchaseReceipt)
        ->and($movement->quantity)
        ->toBe('6.000000')
        ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('6.000000')
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe('6.000000')
        ->and(
            StockMovement::query()
                ->where('type', StockMovementType::Waste->value)
                ->count(),
        )
        ->toBe(0);
});

test('accepted plus damaged retains non-stock evidence and moves accepted stock only', function () {
    $receipt = saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        null,
        $this->baseUnit,
        'GR-ACCEPTED-DAMAGED',
        '7',
        '0',
        '1.5',
    );

    $evidence = GoodsReceiptNonStockLine::query()->sole();

    expect($evidence->damaged_quantity)
        ->toBe('1.500000')
        ->and($evidence->damaged_base_quantity)
        ->toBe('1.500000');

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $receipt,
    );

    expect(StockMovement::query()->sole()->quantity)
        ->toBe('7.000000')
        ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('7.000000')
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe('7.000000');
});

test(
    'all rejected or all damaged finalizes as stock-neutral receiving evidence',
    function (string $rejectedQuantity, string $damagedQuantity) {
        $receipt = saveDispositionReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            null,
            null,
            $rejectedQuantity === '0' ? null : $this->baseUnit,
            $damagedQuantity === '0' ? null : $this->baseUnit,
            "GR-NON-STOCK-{$rejectedQuantity}-{$damagedQuantity}",
            '0',
            $rejectedQuantity,
            $damagedQuantity,
        );

        expect($receipt->lines()->count())
            ->toBe(0)
            ->and($receipt->nonStockLines()->count())
            ->toBe(1);

        $finalized = app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        expect($finalized->status)
            ->toBe(GoodsReceiptStatus::Finalized)
            ->and(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0)
            ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
            ->toBe('0.000000')
            ->and($this->purchaseOrder->refresh()->status)
            ->toBe(PurchaseOrderStatus::Approved);
    },
)->with([
    'all rejected' => ['10', '0'],
    'all damaged' => ['0', '10'],
]);

test(
    'unknown rejected or damaged conversion fails before a draft can be finalized',
    function (string $rejectedQuantity, string $damagedQuantity) {
        $unsupportedUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Case',
            'symbol' => 'case',
            'dimension' => 'count',
            'active' => true,
        ]);

        expect(fn () => saveDispositionReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            null,
            null,
            $rejectedQuantity === '0' ? null : $unsupportedUnit,
            $damagedQuantity === '0' ? null : $unsupportedUnit,
            "GR-INVALID-CONVERSION-{$rejectedQuantity}-{$damagedQuantity}",
            '0',
            $rejectedQuantity,
            $damagedQuantity,
        ))->toThrow(ValidationException::class);

        expect(GoodsReceipt::query()->count())
            ->toBe(0)
            ->and(GoodsReceiptNonStockLine::query()->count())
            ->toBe(0)
            ->and(StockMovement::query()->count())
            ->toBe(0);
    },
)->with([
    'rejected conversion' => ['1', '0'],
    'damaged conversion' => ['0', '1'],
]);

test('finalized rejected and damaged evidence cannot be replaced through the draft save action', function () {
    $receipt = saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        $this->baseUnit,
        $this->baseUnit,
        'GR-IMMUTABLE-EVIDENCE',
        '4',
        '1',
        '1',
    );

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $receipt,
    );

    expect(fn () => saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        $this->baseUnit,
        $this->baseUnit,
        'GR-IMMUTABLE-EVIDENCE',
        '4',
        '2',
        '3',
        $receipt,
    ))->toThrow(ValidationException::class);

    $evidence = GoodsReceiptNonStockLine::query()->sole();

    expect($evidence->rejected_quantity)
        ->toBe('1.000000')
        ->and($evidence->damaged_quantity)
        ->toBe('1.000000')
        ->and(StockMovement::query()->count())
        ->toBe(1);
});

test('non-stock receiving evidence rejects cross-tenant units and locations', function () {
    $otherOrganization = Organization::factory()->create();
    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);
    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Other Each',
        'symbol' => 'other-ea',
        'dimension' => 'count',
        'active' => true,
    ]);

    expect(fn () => saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        null,
        null,
        $otherUnit,
        null,
        'GR-CROSS-TENANT-UOM',
        '0',
        '1',
        '0',
    ))->toThrow(ValidationException::class);

    $this->purchaseOrder->forceFill([
        'location_id' => $otherLocation->id,
    ])->save();

    expect(fn () => saveDispositionReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        null,
        null,
        $this->baseUnit,
        null,
        'GR-CROSS-TENANT-LOCATION',
        '0',
        '1',
        '0',
    ))->toThrow(ValidationException::class);

    expect(GoodsReceipt::query()->count())
        ->toBe(0)
        ->and(StockMovement::query()->count())
        ->toBe(0);
});
