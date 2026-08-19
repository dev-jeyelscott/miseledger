<?php

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
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one storage location for Receiving index fixtures.
 */
function createGoodsReceiptIndexStorageForTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Receiving Index {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

/**
 * Create one approved PO with deterministic lines for Receiving index fixtures.
 */
function createGoodsReceiptIndexPurchaseOrderForTest(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    SupplierItem $supplierItem,
    User $creator,
    string $number,
    int $lineCount = 1,
): PurchaseOrder {
    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '100.00',
        'notes' => null,
        'created_by' => $creator->id,
        'approved_by' => $creator->id,
        'approved_at' => now(),
    ]);

    foreach (range(1, $lineCount) as $lineNumber) {
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_item_id' => $supplierItem->id,
            'inventory_item_id' => $supplierItem->inventory_item_id,
            'item_name_snapshot' => "Receiving Index Item {$lineNumber}",
            'supplier_sku_snapshot' => $supplierItem->supplier_sku,
            'ordered_quantity' => '1.000000',
            'purchase_unit_of_measure_id' => $supplierItem
                ->purchase_unit_of_measure_id,
            'base_quantity' => '1.000000',
            'unit_price' => '10.0000',
            'line_total' => '10.00',
            'received_base_quantity' => '0.000000',
        ]);
    }

    return $purchaseOrder;
}

/**
 * Create one receipt header and optional accepted lines for list-only coverage.
 */
function createGoodsReceiptIndexReceiptForTest(
    Organization $organization,
    PurchaseOrder $purchaseOrder,
    StorageLocation $storageLocation,
    User $receiver,
    string $number,
    GoodsReceiptStatus $status,
    ?CarbonImmutable $receivedAt = null,
    int $acceptedLineCount = 0,
): GoodsReceipt {
    $receipt = GoodsReceipt::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $purchaseOrder->location_id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $purchaseOrder->supplier_id,
        'number' => $number,
        'status' => $status,
        'received_at' => $receivedAt,
        'supplier_reference' => null,
        'received_by' => $receivedAt === null
            ? null
            : $receiver->id,
        'notes' => null,
    ]);

    if ($acceptedLineCount === 0) {
        return $receipt;
    }

    $purchaseOrderLines = $purchaseOrder
        ->lines()
        ->orderBy('id')
        ->limit($acceptedLineCount)
        ->get();

    foreach ($purchaseOrderLines as $purchaseOrderLine) {
        GoodsReceiptLine::query()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $purchaseOrderLine->id,
            'inventory_item_id' => $purchaseOrderLine->inventory_item_id,
            'storage_location_id' => $storageLocation->id,
            'received_quantity' => '1.000000',
            'received_unit_of_measure_id' => $purchaseOrderLine
                ->purchase_unit_of_measure_id,
            'base_quantity' => '1.000000',
            'unit_cost' => '10.0000',
            'total_cost' => '10.0000',
            'notes' => null,
        ]);
    }

    return $receipt;
}

beforeEach(function () {
    CarbonImmutable::setTestNow(
        CarbonImmutable::create(
            2026,
            8,
            19,
            12,
            0,
            0,
            'Asia/Manila',
        ),
    );

    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Makati Branch',
        'active' => true,
    ]);

    $this->otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'BGC Branch',
        'active' => true,
    ]);

    $this->storageLocation = createGoodsReceiptIndexStorageForTest(
        $this->organization,
        $this->location,
        'MAKATI',
    );

    $this->otherStorageLocation = createGoodsReceiptIndexStorageForTest(
        $this->organization,
        $this->otherLocation,
        'BGC',
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
        'name' => 'Receiving Index Item',
        'sku' => 'RECEIVING-INDEX',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Global Foods Inc.',
        'active' => true,
    ]);

    $this->otherSupplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Fresh Harvest Produce',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-RECEIVING-A',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $this->otherSupplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->otherSupplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-RECEIVING-B',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->primaryPurchaseOrder =
        createGoodsReceiptIndexPurchaseOrderForTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-RECEIVING-PRIMARY',
            lineCount: 2,
        );

    $this->secondaryPurchaseOrder =
        createGoodsReceiptIndexPurchaseOrderForTest(
            $this->organization,
            $this->otherLocation,
            $this->otherSupplier,
            $this->otherSupplierItem,
            $this->manager,
            'PO-RECEIVING-SECONDARY',
        );

    $this->businessNow = CarbonImmutable::now(
        $this->organization->timezone,
    );
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test(
    'receiving index exposes organization summaries line counts and sorting',
    function () {
        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->primaryPurchaseOrder,
            $this->storageLocation,
            $this->manager,
            'GR-A-DRAFT',
            GoodsReceiptStatus::Draft,
            acceptedLineCount: 2,
        );

        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->primaryPurchaseOrder,
            $this->storageLocation,
            $this->manager,
            'GR-B-FINALIZED',
            GoodsReceiptStatus::Finalized,
            $this->businessNow
                ->subDay()
                ->utc(),
            acceptedLineCount: 1,
        );

        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->secondaryPurchaseOrder,
            $this->otherStorageLocation,
            $this->manager,
            'GR-C-CANCELLED',
            GoodsReceiptStatus::Cancelled,
        );

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'sort' => 'receipt_asc',
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('goods-receipts/index')
                    ->where(
                        'summary.totalCount',
                        3,
                    )
                    ->where(
                        'summary.draftCount',
                        1,
                    )
                    ->where(
                        'summary.finalizedCount',
                        1,
                    )
                    ->where(
                        'summary.receivedThisWeekCount',
                        1,
                    )
                    ->where(
                        'receipts.total',
                        3,
                    )
                    ->where(
                        'receipts.data.0.number',
                        'GR-A-DRAFT',
                    )
                    ->where(
                        'receipts.data.0.acceptedLineCount',
                        2,
                    )
                    ->where(
                        'receipts.data.0.purchaseOrderId',
                        $this->primaryPurchaseOrder->id,
                    )
                    ->where(
                        'filters.sort',
                        'receipt_asc',
                    )
                    ->where(
                        'timezone',
                        'Asia/Manila',
                    )
                    ->where(
                        'canFinalize',
                        true,
                    ),
            );
    },
);

test(
    'receiving index supports combined tenant scoped filters',
    function () {
        $receivedAt = $this->businessNow
            ->subDay();

        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->primaryPurchaseOrder,
            $this->storageLocation,
            $this->manager,
            'GR-MATCH-001',
            GoodsReceiptStatus::Finalized,
            $receivedAt->utc(),
            acceptedLineCount: 1,
        );

        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->secondaryPurchaseOrder,
            $this->otherStorageLocation,
            $this->manager,
            'GR-WRONG-SUPPLIER',
            GoodsReceiptStatus::Finalized,
            $receivedAt->utc(),
        );

        createGoodsReceiptIndexReceiptForTest(
            $this->organization,
            $this->primaryPurchaseOrder,
            $this->storageLocation,
            $this->manager,
            'GR-WRONG-STATUS',
            GoodsReceiptStatus::Draft,
        );

        $businessDate = $receivedAt->toDateString();

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'search' => 'PO-RECEIVING-PRIMARY',
                        'status' => GoodsReceiptStatus::Finalized->value,
                        'supplier_id' => $this->supplier->id,
                        'location_id' => $this->location->id,
                        'from' => $businessDate,
                        'to' => $businessDate,
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'receipts.total',
                        1,
                    )
                    ->where(
                        'receipts.data.0.number',
                        'GR-MATCH-001',
                    )
                    ->where(
                        'filters.search',
                        'PO-RECEIVING-PRIMARY',
                    )
                    ->where(
                        'filters.status',
                        GoodsReceiptStatus::Finalized->value,
                    )
                    ->where(
                        'filters.supplierId',
                        $this->supplier->id,
                    )
                    ->where(
                        'filters.locationId',
                        $this->location->id,
                    )
                    ->where(
                        'filters.from',
                        $businessDate,
                    )
                    ->where(
                        'filters.to',
                        $businessDate,
                    )
                    ->has(
                        'supplierOptions',
                        2,
                    )
                    ->has(
                        'locationOptions',
                        2,
                    ),
            );
    },
);

test(
    'receiving index blocks cross tenant filters and data leakage',
    function () {
        $foreignOrganization = Organization::factory()->create();

        $foreignLocation = Location::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'name' => 'Foreign Branch',
            'active' => true,
        ]);

        $foreignSupplier = Supplier::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'name' => 'Foreign Supplier',
            'active' => true,
        ]);

        $foreignPurchaseOrder = PurchaseOrder::query()->create([
            'organization_id' => $foreignOrganization->id,
            'location_id' => $foreignLocation->id,
            'supplier_id' => $foreignSupplier->id,
            'number' => 'PO-FOREIGN-RECEIVING',
            'status' => PurchaseOrderStatus::Approved,
            'order_date' => $this->businessNow->toDateString(),
            'expected_delivery_date' => null,
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'total' => '100.00',
            'notes' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        GoodsReceipt::query()->create([
            'organization_id' => $foreignOrganization->id,
            'location_id' => $foreignLocation->id,
            'purchase_order_id' => $foreignPurchaseOrder->id,
            'supplier_id' => $foreignSupplier->id,
            'number' => 'GR-FOREIGN-RECEIVING',
            'status' => GoodsReceiptStatus::Draft,
            'received_at' => null,
            'supplier_reference' => null,
            'received_by' => null,
            'notes' => null,
        ]);

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'search' => 'GR-FOREIGN-RECEIVING',
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'receipts.total',
                        0,
                    )
                    ->where(
                        'summary.totalCount',
                        0,
                    )
                    ->has(
                        'supplierOptions',
                        2,
                    )
                    ->has(
                        'locationOptions',
                        2,
                    ),
            );

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'supplier_id' => $foreignSupplier->id,
                    ],
                ),
            )
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_id');

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'location_id' => $foreignLocation->id,
                    ],
                ),
            )
            ->assertRedirect()
            ->assertSessionHasErrors('location_id');
    },
);

test(
    'receiving index respects purchasing and finalization permissions',
    function () {
        $auditor = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $auditor->id,
            'role' => OrganizationRole::Auditor,
        ]);

        $this->actingAs($auditor)
            ->get(route('goods-receipts.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'canFinalize',
                        false,
                    ),
            );

        $kitchenStaff = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $kitchenStaff->id,
            'role' => OrganizationRole::KitchenStaff,
        ]);

        $this->actingAs($kitchenStaff)
            ->get(route('goods-receipts.index'))
            ->assertForbidden();
    },
);

test(
    'receiving index paginates while retaining active query filters',
    function () {
        foreach (range(1, 26) as $index) {
            createGoodsReceiptIndexReceiptForTest(
                $this->organization,
                $this->primaryPurchaseOrder,
                $this->storageLocation,
                $this->manager,
                sprintf(
                    'GR-PAGE-%03d',
                    $index,
                ),
                GoodsReceiptStatus::Draft,
            );
        }

        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'status' => GoodsReceiptStatus::Draft->value,
                        'sort' => 'receipt_asc',
                        'page' => 2,
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'receipts.current_page',
                        2,
                    )
                    ->where(
                        'receipts.per_page',
                        25,
                    )
                    ->where(
                        'receipts.total',
                        26,
                    )
                    ->has(
                        'receipts.data',
                        1,
                    )
                    ->where(
                        'receipts.data.0.number',
                        'GR-PAGE-026',
                    )
                    ->where(
                        'receipts.prev_page_url',
                        fn (
                            string $url,
                        ): bool => str_contains(
                            $url,
                            'status=draft',
                        ) && str_contains(
                            $url,
                            'sort=receipt_asc',
                        ),
                    ),
            );
    },
);

test(
    'receiving index rejects an inverted received date range',
    function () {
        $this->actingAs($this->manager)
            ->get(
                route(
                    'goods-receipts.index',
                    [
                        'from' => '2026-08-20',
                        'to' => '2026-08-19',
                    ],
                ),
            )
            ->assertRedirect()
            ->assertSessionHasErrors('from');
    },
);
