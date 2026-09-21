<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveInventoryItem;
use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\InventoryItemType;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Build the full attribute payload SaveInventoryItem requires.
 *
 * @return array<string, mixed>
 */
function purchasingDeactivationItemAttributes(
    InventoryItem $inventoryItem,
    UnitOfMeasure $baseUnit,
    bool $active,
): array {
    return [
        'name' => $inventoryItem->name,
        'sku' => $inventoryItem->sku,
        'base_unit_of_measure_id' => $baseUnit->id,
        'inventory_category_id' => null,
        'inventory_brand_id' => null,
        'inventory_product_id' => null,
        'model_number' => null,
        'manufacturer_part_number' => null,
        'description' => null,
        'type' => InventoryItemType::Ingredient,
        'yield_percentage' => '100.00',
        'active' => $active,
    ];
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation = new StorageLocation;
    $this->storageLocation->organization_id = $this->organization->id;
    $this->storageLocation->location_id = $this->location->id;
    $this->storageLocation->name = 'Storage MAIN';
    $this->storageLocation->code = 'MAIN';
    $this->storageLocation->active = true;
    $this->storageLocation->save();

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
        'name' => 'Purchasing Dependency Item',
        'sku' => 'PURCHASING-DEP-ITEM',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Purchasing Dependency Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-PURCHASING-DEP-ITEM',
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
        'role' => OrganizationRole::Owner,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-PURCHASING-DEP',
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

test('an open approved purchase-order line blocks its inventory item from being deactivated', function () {
    expect(fn () => app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    ))->toThrow(ValidationException::class);

    expect($this->inventoryItem->refresh()->active)->toBeTrue();
});

test('a fully received purchase-order line no longer blocks deactivation', function () {
    $this->purchaseOrderLine->update([
        'received_base_quantity' => '10.000000',
    ]);

    $this->purchaseOrder->update([
        'status' => PurchaseOrderStatus::Received,
    ]);

    $item = app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    );

    expect($item->active)->toBeFalse();
});

test('a cancelled purchase order does not block deactivation', function () {
    $this->purchaseOrder->update([
        'status' => PurchaseOrderStatus::Cancelled,
    ]);

    $item = app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    );

    expect($item->active)->toBeFalse();
});

test('a draft goods receipt with an accepted line blocks its inventory item from being deactivated', function () {
    $receipt = app(SaveGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        [
            'number' => 'GR-PURCHASING-DEP',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $this->purchaseOrderLine->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'received_quantity' => '4',
                    'received_unit_of_measure_id' => $this->baseUnit->id,
                    'notes' => null,
                ],
            ],
        ],
    );

    expect($receipt->status)->toBe(GoodsReceiptStatus::Draft);

    expect(fn () => app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    ))->toThrow(ValidationException::class);

    expect($this->inventoryItem->refresh()->active)->toBeTrue();
});

test('finalizing the receipt clears the draft-receipt dependency so the item can later be deactivated', function () {
    $receipt = app(SaveGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        [
            'number' => 'GR-PURCHASING-DEP-FINAL',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $this->purchaseOrderLine->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'received_quantity' => '10',
                    'received_unit_of_measure_id' => $this->baseUnit->id,
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

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->inventoryItem,
        type: StockMovementType::ManualAdjustment,
        baseQuantity: '-10.000000',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'purchasing_deactivation_test',
        referenceId: $receipt->id,
        occurredAt: now(),
        actor: $this->actor,
        idempotencyKey: "purchasing-deactivation:clear-balance:{$receipt->id}",
    );

    $item = app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    );

    expect($item->active)->toBeFalse();
});

test('a draft purchase order does not block deactivation but approval is rejected once the item is inactive', function () {
    $this->purchaseOrder->update([
        'status' => PurchaseOrderStatus::Cancelled,
    ]);

    $draftPurchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-PURCHASING-DEP-DRAFT',
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '100.00',
        'notes' => null,
        'created_by' => $this->actor->id,
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $draftPurchaseOrder->id,
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

    $item = app(SaveInventoryItem::class)->handle(
        $this->organization,
        purchasingDeactivationItemAttributes(
            $this->inventoryItem,
            $this->baseUnit,
            false,
        ),
        $this->inventoryItem,
    );

    expect($item->active)->toBeFalse();

    expect(fn () => app(ApprovePurchaseOrder::class)->handle(
        $this->organization,
        $this->actor,
        $draftPurchaseOrder,
    ))->toThrow(ValidationException::class);

    expect($draftPurchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Draft);
});

test('a draft goods receipt cannot accept quantity against an already inactive inventory item', function () {
    DB::table('inventory_items')
        ->where('id', $this->inventoryItem->id)
        ->update(['active' => false]);

    expect(fn () => app(SaveGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        [
            'number' => 'GR-INACTIVE-ITEM',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $this->purchaseOrderLine->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'received_quantity' => '4',
                    'received_unit_of_measure_id' => $this->baseUnit->id,
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
});

test('inventory-item deactivation is serialized against a concurrent draft goods-receipt creation', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-purchasing-deactivation-');

    if ($resultPath === false) {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to create the concurrency result file.');
    }

    $childPid = pcntl_fork();

    if ($childPid === -1) {
        unlink($resultPath);
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to fork the concurrency test process.');
    }

    if ($childPid === 0) {
        try {
            app(SaveGoodsReceipt::class)->handle(
                $this->organization,
                $this->actor,
                $this->purchaseOrder,
                [
                    'number' => 'GR-CONCURRENCY-DEACTIVATION',
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
                            'received_quantity' => '4',
                            'received_unit_of_measure_id' => $this
                                ->baseUnit
                                ->id,
                            'notes' => null,
                        ],
                    ],
                ],
            );

            file_put_contents($resultPath, 'success');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultPath, get_class($exception));
            exit(1);
        }
    }

    // Give the child a head start so it locks the inventory-item row and
    // commits the draft receipt before this process attempts to
    // deactivate the item.
    usleep(300000);

    $thrown = null;

    try {
        app(SaveInventoryItem::class)->handle(
            $this->organization,
            purchasingDeactivationItemAttributes(
                $this->inventoryItem,
                $this->baseUnit,
                false,
            ),
            $this->inventoryItem,
        );
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    $childReaped = false;

    try {
        pcntl_waitpid($childPid, $status);
        $childReaped = true;

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0)
            ->and(file_get_contents($resultPath))->toBe('success')
            ->and($thrown)->not->toBeNull()
            ->and($thrown->errors())->toHaveKey('active')
            ->and($this->inventoryItem->refresh()->active)->toBeTrue();
    } finally {
        if (! $childReaped) {
            pcntl_waitpid($childPid, $status);
        }

        if (file_exists($resultPath)) {
            unlink($resultPath);
        }

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
    }
});
