<?php

use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one purchase order and optional snapshotted lines for index coverage.
 */
function createPurchaseOrderForIndexTest(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    SupplierItem $supplierItem,
    User $creator,
    string $number,
    PurchaseOrderStatus $status,
    string $orderDate,
    string $total,
    int $lineCount = 1,
    ?string $expectedDeliveryDate = null,
): PurchaseOrder {
    $approved = in_array(
        $status,
        [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
        ],
        true,
    );

    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => $status,
        'order_date' => $orderDate,
        'expected_delivery_date' => $expectedDeliveryDate,
        'subtotal' => $total,
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => $total,
        'notes' => null,
        'created_by' => $creator->id,
        'approved_by' => $approved
            ? $creator->id
            : null,
        'approved_at' => $approved
            ? now()
            : null,
    ]);

    foreach (range(1, $lineCount) as $lineNumber) {
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_item_id' => $supplierItem->id,
            'inventory_item_id' => $supplierItem
                ->inventory_item_id,
            'item_name_snapshot' => "Index Test Item {$lineNumber}",
            'supplier_sku_snapshot' => $supplierItem
                ->supplier_sku,
            'ordered_quantity' => '1.000000',
            'purchase_unit_of_measure_id' => $supplierItem
                ->purchase_unit_of_measure_id,
            'base_quantity' => '1.000000',
            'unit_price' => '10.0000',
            'line_total' => '10.00',
            'received_base_quantity' => $status
                === PurchaseOrderStatus::Received
                    ? '1.000000'
                    : '0.000000',
        ]);
    }

    return $purchaseOrder;
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
        'name' => 'Index Test Item',
        'sku' => 'INDEX-TEST',
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
        'supplier_sku' => 'SUP-INDEX-A',
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
        'supplier_sku' => 'SUP-INDEX-B',
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

    $this->businessNow = CarbonImmutable::now(
        $this->organization->timezone,
    );
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test(
    'purchase order index exposes operational summaries and line counts',
    function () {
        $currentMonth = $this->businessNow
            ->startOfMonth();

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-DRAFT',
            PurchaseOrderStatus::Draft,
            $currentMonth
                ->addDay()
                ->toDateString(),
            '10.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-APPROVED',
            PurchaseOrderStatus::Approved,
            $currentMonth
                ->addDays(2)
                ->toDateString(),
            '20.00',
            lineCount: 2,
            expectedDeliveryDate: $currentMonth
                ->addDays(8)
                ->toDateString(),
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->otherLocation,
            $this->otherSupplier,
            $this->otherSupplierItem,
            $this->manager,
            'PO-PARTIAL',
            PurchaseOrderStatus::PartiallyReceived,
            $currentMonth
                ->addDays(3)
                ->toDateString(),
            '30.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->otherLocation,
            $this->otherSupplier,
            $this->otherSupplierItem,
            $this->manager,
            'PO-RECEIVED',
            PurchaseOrderStatus::Received,
            $currentMonth
                ->addDays(4)
                ->toDateString(),
            '40.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-CANCELLED',
            PurchaseOrderStatus::Cancelled,
            $currentMonth
                ->addDays(5)
                ->toDateString(),
            '50.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-OLDER',
            PurchaseOrderStatus::Approved,
            $currentMonth
                ->subMonthNoOverflow()
                ->startOfMonth()
                ->toDateString(),
            '100.00',
        );

        $this->actingAs($this->manager)
            ->get(
                route(
                    'purchase-orders.index',
                    [
                        'search' => 'PO-APPROVED',
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('purchase-orders/index')
                    ->where(
                        'summary.openCount',
                        4,
                    )
                    ->where(
                        'summary.awaitingDeliveryCount',
                        2,
                    )
                    ->where(
                        'summary.partiallyReceivedCount',
                        1,
                    )
                    ->where(
                        'summary.thisMonthSpend',
                        '90.00',
                    )
                    ->where(
                        'purchaseOrders.total',
                        1,
                    )
                    ->where(
                        'purchaseOrders.data.0.number',
                        'PO-APPROVED',
                    )
                    ->where(
                        'purchaseOrders.data.0.lineCount',
                        2,
                    )
                    ->where(
                        'purchaseOrders.data.0.expectedDeliveryDate',
                        $currentMonth
                            ->addDays(8)
                            ->toDateString(),
                    )
                    ->where(
                        'canManage',
                        true,
                    )
                    ->where(
                        'canViewCosts',
                        true,
                    ),
            );
    },
);

test(
    'purchase order index supports combined tenant scoped filters',
    function () {
        $orderDate = $this->businessNow
            ->startOfMonth()
            ->addDays(3);

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-MATCH-001',
            PurchaseOrderStatus::Approved,
            $orderDate->toDateString(),
            '25.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->otherLocation,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-WRONG-LOCATION',
            PurchaseOrderStatus::Approved,
            $orderDate->toDateString(),
            '25.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->otherSupplier,
            $this->otherSupplierItem,
            $this->manager,
            'PO-WRONG-SUPPLIER',
            PurchaseOrderStatus::Approved,
            $orderDate->toDateString(),
            '25.00',
        );

        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-WRONG-STATUS',
            PurchaseOrderStatus::Draft,
            $orderDate->toDateString(),
            '25.00',
        );

        $this->actingAs($this->manager)
            ->get(
                route(
                    'purchase-orders.index',
                    [
                        'search' => 'Global Foods',
                        'status' => PurchaseOrderStatus::Approved
                            ->value,
                        'supplier_id' => $this->supplier->id,
                        'location_id' => $this->location->id,
                        'from' => $orderDate
                            ->subDay()
                            ->toDateString(),
                        'to' => $orderDate
                            ->addDay()
                            ->toDateString(),
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'purchaseOrders.total',
                        1,
                    )
                    ->where(
                        'purchaseOrders.data.0.number',
                        'PO-MATCH-001',
                    )
                    ->where(
                        'filters.search',
                        'Global Foods',
                    )
                    ->where(
                        'filters.status',
                        'approved',
                    )
                    ->where(
                        'filters.supplierId',
                        $this->supplier->id,
                    )
                    ->where(
                        'filters.locationId',
                        $this->location->id,
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
    'purchase order index blocks cross tenant filters and data leakage',
    function () {
        $otherOrganization = Organization::factory()->create();

        $foreignLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign Branch',
            'active' => true,
        ]);

        $foreignSupplier = Supplier::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign Supplier',
            'active' => true,
        ]);

        PurchaseOrder::query()->create([
            'organization_id' => $otherOrganization->id,
            'location_id' => $foreignLocation->id,
            'supplier_id' => $foreignSupplier->id,
            'number' => 'PO-FOREIGN-INDEX',
            'status' => PurchaseOrderStatus::Approved,
            'order_date' => $this->businessNow->toDateString(),
            'expected_delivery_date' => null,
            'subtotal' => '500.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'total' => '500.00',
            'notes' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($this->manager)
            ->get(
                route(
                    'purchase-orders.index',
                    [
                        'search' => 'PO-FOREIGN-INDEX',
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'purchaseOrders.total',
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
                    'purchase-orders.index',
                    [
                        'supplier_id' => $foreignSupplier->id,
                    ],
                ),
            )
            ->assertRedirect()
            ->assertSessionHasErrors(
                'supplier_id',
            );

        $this->actingAs($this->manager)
            ->get(
                route(
                    'purchase-orders.index',
                    [
                        'location_id' => $foreignLocation->id,
                    ],
                ),
            )
            ->assertRedirect()
            ->assertSessionHasErrors(
                'location_id',
            );
    },
);

test(
    'purchase order index respects purchasing and cost permissions',
    function () {
        createPurchaseOrderForIndexTest(
            $this->organization,
            $this->location,
            $this->supplier,
            $this->supplierItem,
            $this->manager,
            'PO-PERMISSION',
            PurchaseOrderStatus::Approved,
            $this->businessNow->toDateString(),
            '75.00',
        );

        $inventoryStaff = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $inventoryStaff->id,
            'role' => OrganizationRole::InventoryStaff,
        ]);

        $this->actingAs($inventoryStaff)
            ->get(
                route('purchase-orders.index'),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'canManage',
                        false,
                    )
                    ->where(
                        'canViewCosts',
                        false,
                    )
                    ->where(
                        'summary.thisMonthSpend',
                        null,
                    )
                    ->where(
                        'purchaseOrders.data.0.total',
                        '75.00',
                    ),
            );

        $kitchenStaff = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $kitchenStaff->id,
            'role' => OrganizationRole::KitchenStaff,
        ]);

        $this->actingAs($kitchenStaff)
            ->get(
                route('purchase-orders.index'),
            )
            ->assertForbidden();
    },
);

test(
    'purchase order index paginates while retaining active query filters',
    function () {
        foreach (range(1, 26) as $index) {
            createPurchaseOrderForIndexTest(
                $this->organization,
                $this->location,
                $this->supplier,
                $this->supplierItem,
                $this->manager,
                sprintf(
                    'PO-PAGE-%03d',
                    $index,
                ),
                PurchaseOrderStatus::Approved,
                $this->businessNow->toDateString(),
                '10.00',
            );
        }

        $this->actingAs($this->manager)
            ->get(
                route(
                    'purchase-orders.index',
                    [
                        'status' => PurchaseOrderStatus::Approved
                            ->value,
                        'page' => 2,
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->where(
                        'purchaseOrders.current_page',
                        2,
                    )
                    ->where(
                        'purchaseOrders.per_page',
                        25,
                    )
                    ->where(
                        'purchaseOrders.total',
                        26,
                    )
                    ->has(
                        'purchaseOrders.data',
                        1,
                    )
                    ->where(
                        'purchaseOrders.prev_page_url',
                        fn (
                            string $url,
                        ): bool => str_contains(
                            $url,
                            'status=approved',
                        ),
                    ),
            );
    },
);
