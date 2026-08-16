<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create one active storage destination for receiving tests.
 */
function createReceivingStorageLocationForTest(
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
 * Save one single-line goods-receipt draft for common receiving assertions.
 */
function saveReceivingReceiptForTest(
    Organization $organization,
    User $actor,
    PurchaseOrder $purchaseOrder,
    PurchaseOrderLine $purchaseOrderLine,
    StorageLocation $storageLocation,
    UnitOfMeasure $receivedUnit,
    string $number,
    string $quantity,
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
                    'storage_location_id' => $storageLocation->id,
                    'received_quantity' => $quantity,
                    'received_unit_of_measure_id' => $receivedUnit->id,
                    'notes' => null,
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

    $this->storageLocation = createReceivingStorageLocationForTest(
        $this->organization,
        $this->location,
        'MAIN',
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
        'name' => 'Receiving Test Item',
        'sku' => 'RECEIVE-TEST',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Receiving Test Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-RECEIVE-TEST',
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
        'number' => 'PO-RECEIVE-TEST',
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

test('an exact receipt fulfills the purchase order', function () {
    $receipt = saveReceivingReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        'GR-EXACT',
        '10',
    );

    $finalized = app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $receipt,
    );

    expect($finalized->status)
        ->toBe(GoodsReceiptStatus::Finalized)
        ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('10.000000')
        ->and($this->purchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Received)
        ->and(
            StockMovement::query()
                ->where(
                    'type',
                    StockMovementType::PurchaseReceipt->value,
                )
                ->count(),
        )
        ->toBe(1)
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe('10.000000');
});

test(
    'a partial receipt leaves the purchase order partially received',
    function () {
        $receipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-PARTIAL',
            '4',
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        expect(
            $this->purchaseOrderLine
                ->refresh()
                ->received_base_quantity,
        )
            ->toBe('4.000000')
            ->and($this->purchaseOrder->refresh()->status)
            ->toBe(PurchaseOrderStatus::PartiallyReceived)
            ->and(StockBalance::query()->sole()->quantity_on_hand)
            ->toBe('4.000000');
    },
);

test('an over receipt is accepted and fulfills the purchase order', function () {
    $receipt = saveReceivingReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        'GR-OVER',
        '12',
    );

    app(FinalizeGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $receipt,
    );

    expect($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('12.000000')
        ->and($this->purchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Received)
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe('12.000000');
});

test(
    'an existing draft can finish after another receipt already fulfilled the purchase order',
    function () {
        $firstReceipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-FIRST',
            '10',
        );

        $secondReceipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-SECOND',
            '1',
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $firstReceipt,
        );

        expect($this->purchaseOrder->refresh()->status)
            ->toBe(PurchaseOrderStatus::Received);

        $secondReceipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-SECOND',
            '2',
            $secondReceipt,
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $secondReceipt,
        );

        expect(
            $this->purchaseOrderLine
                ->refresh()
                ->received_base_quantity,
        )
            ->toBe('12.000000')
            ->and($this->purchaseOrder->refresh()->status)
            ->toBe(PurchaseOrderStatus::Received)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::PurchaseReceipt->value,
                    )
                    ->count(),
            )
            ->toBe(2);
    },
);

test(
    'a fully received purchase order cannot start a new receipt workflow',
    function () {
        $receipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-CLOSE-PO',
            '10',
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        expect(fn () => saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-TOO-LATE',
            '1',
        ))->toThrow(ValidationException::class);

        expect($this->purchaseOrder->refresh()->status)
            ->toBe(PurchaseOrderStatus::Received);
    },
);

test(
    'an inactive location cannot be used to create a goods receipt',
    function () {
        $this->location->update(['active' => false]);

        expect(fn () => saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-INACTIVE-LOCATION',
            '1',
        ))->toThrow(ValidationException::class);

        expect(GoodsReceipt::query()->count())->toBe(0);
    },
);

test('a saved draft creates no stock movement or balance', function () {
    $receipt = saveReceivingReceiptForTest(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        $this->purchaseOrderLine,
        $this->storageLocation,
        $this->baseUnit,
        'GR-DRAFT-NO-LEDGER',
        '5',
    );

    expect($receipt->status)
        ->toBe(GoodsReceiptStatus::Draft)
        ->and(StockMovement::query()->count())
        ->toBe(0)
        ->and(StockBalance::query()->count())
        ->toBe(0)
        ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('0.000000')
        ->and($this->purchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Approved);
});

test(
    'an incompatible-dimension received unit fails conversion and creates no draft lines',
    function () {
        $incompatibleUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
            'active' => true,
        ]);

        expect(fn () => saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $incompatibleUnit,
            'GR-INVALID-CONVERSION',
            '5',
        ))->toThrow(ValidationException::class);

        expect(GoodsReceipt::query()->count())
            ->toBe(0)
            ->and(StockMovement::query()->count())
            ->toBe(0);
    },
);

test(
    'finalization creates one purchase receipt movement per receipt line and is idempotent',
    function () {
        $receipt = app(SaveGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            [
                'number' => 'GR-MULTI-LINE',
                'supplier_reference' => null,
                'notes' => null,
                'lines' => [
                    [
                        'purchase_order_line_id' => $this
                            ->purchaseOrderLine
                            ->id,
                        'storage_location_id' => $this
                            ->storageLocation
                            ->id,
                        'received_quantity' => '6',
                        'received_unit_of_measure_id' => $this
                            ->baseUnit
                            ->id,
                        'notes' => null,
                    ],
                    [
                        'purchase_order_line_id' => $this
                            ->purchaseOrderLine
                            ->id,
                        'storage_location_id' => $this
                            ->storageLocation
                            ->id,
                        'received_quantity' => '7',
                        'received_unit_of_measure_id' => $this
                            ->baseUnit
                            ->id,
                        'notes' => null,
                    ],
                ],
            ],
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        $receiptLineIds = $receipt->lines()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $movements = StockMovement::query()
            ->where(
                'type',
                StockMovementType::PurchaseReceipt->value,
            )
            ->where('reference_type', 'goods_receipt_line')
            ->orderBy('reference_id')
            ->get();

        expect($movements)
            ->toHaveCount(2)
            ->and($movements->pluck('reference_id')->all())
            ->toBe($receiptLineIds)
            ->and(
                $this->purchaseOrderLine
                    ->refresh()
                    ->received_base_quantity,
            )
            ->toBe('13.000000')
            ->and(StockBalance::query()->sole()->quantity_on_hand)
            ->toBe('13.000000');
    },
);

test(
    'weighted average costing remains correct for an over receipt',
    function () {
        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '8',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now()->subSecond(),
            actor: $this->actor,
            idempotencyKey: 'opening:receiving-test',
            inboundUnitCost: '5',
        );

        $receipt = saveReceivingReceiptForTest(
            $this->organization,
            $this->actor,
            $this->purchaseOrder,
            $this->purchaseOrderLine,
            $this->storageLocation,
            $this->baseUnit,
            'GR-WEIGHTED-AVERAGE',
            '12',
        );

        app(FinalizeGoodsReceipt::class)->handle(
            $this->organization,
            $this->actor,
            $receipt,
        );

        $balance = StockBalance::query()->sole();

        $receiptMovement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::PurchaseReceipt->value,
            )
            ->sole();

        expect($receiptMovement->quantity)
            ->toBe('12.000000')
            ->and($receiptMovement->unit_cost)
            ->toBe('10.0000')
            ->and($balance->quantity_on_hand)
            ->toBe('20.000000')
            ->and($balance->average_unit_cost)
            ->toBe('8.0000')
            ->and($balance->inventory_value)
            ->toBe('160.0000');
    },
);

test(
    'receipt storage remains isolated to the active tenant and purchase order location',
    function () {
        $otherLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'active' => true,
        ]);

        $wrongLocationStorage = createReceivingStorageLocationForTest(
            $this->organization,
            $otherLocation,
            'WRONG-LOCATION',
        );

        $otherOrganization = Organization::factory()->create();

        $otherOrganizationLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherTenantStorage = createReceivingStorageLocationForTest(
            $otherOrganization,
            $otherOrganizationLocation,
            'OTHER-TENANT',
        );

        foreach (
            [$wrongLocationStorage, $otherTenantStorage] as $invalidStorage
        ) {
            expect(fn () => saveReceivingReceiptForTest(
                $this->organization,
                $this->actor,
                $this->purchaseOrder,
                $this->purchaseOrderLine,
                $invalidStorage,
                $this->baseUnit,
                "GR-INVALID-{$invalidStorage->id}",
                '1',
            ))->toThrow(ValidationException::class);
        }

        expect(StockMovement::query()->count())->toBe(0);
    },
);

test(
    'postgresql quantity constraint permits over received quantities',
    function () {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL constraint coverage runs in CI.',
            );
        }

        DB::table('purchase_order_lines')
            ->where('id', $this->purchaseOrderLine->id)
            ->update([
                'received_base_quantity' => '12.000000',
            ]);

        expect(
            $this->purchaseOrderLine
                ->refresh()
                ->received_base_quantity,
        )->toBe('12.000000');
    },
);

test(
    'postgresql quantity constraint retains required lower bounds',
    function (string $column, string $value) {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL constraint coverage runs in CI.',
            );
        }

        expect(
            fn () => DB::table('purchase_order_lines')
                ->where('id', $this->purchaseOrderLine->id)
                ->update([$column => $value]),
        )->toThrow(QueryException::class);
    },
)->with([
    ['ordered_quantity', '0.000000'],
    ['base_quantity', '0.000000'],
    ['received_base_quantity', '-0.000001'],
]);
