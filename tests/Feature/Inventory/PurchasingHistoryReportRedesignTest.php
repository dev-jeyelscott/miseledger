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
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one deterministic purchasing-history PO and historical line snapshot.
 */
function createPurchasingHistoryRedesignPurchaseOrder(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    SupplierItem $supplierItem,
    InventoryItem $inventoryItem,
    UnitOfMeasure $unit,
    User $actor,
    string $number,
    PurchaseOrderStatus $status,
    string $orderDate,
    string $itemName,
    string $supplierSku,
    string $baseQuantity,
    string $receivedBaseQuantity,
    string $unitPrice,
    string $lineTotal,
): PurchaseOrder {
    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => $status,
        'order_date' => $orderDate,
        'expected_delivery_date' => null,
        'subtotal' => $lineTotal,
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => $lineTotal,
        'notes' => null,
        'created_by' => $actor->id,
        'approved_by' => $actor->id,
        'approved_at' => now(),
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_item_id' => $supplierItem->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name_snapshot' => $itemName,
        'supplier_sku_snapshot' => $supplierSku,
        'ordered_quantity' => $baseQuantity,
        'purchase_unit_of_measure_id' => $unit->id,
        'base_quantity' => $baseQuantity,
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'received_base_quantity' => $receivedBaseQuantity,
    ]);

    return $purchaseOrder;
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Current Inventory Item Name',
        'sku' => 'CURRENT-SKU',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Redesign Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-REDESIGN',
        'purchase_unit_of_measure_id' => $this->unit->id,
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

    createPurchasingHistoryRedesignPurchaseOrder(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->supplierItem,
        $this->inventoryItem,
        $this->unit,
        $this->manager,
        'PO-RECEIVED',
        PurchaseOrderStatus::Received,
        '2026-08-01',
        'Chicken Breast',
        'SKU-RECEIVED',
        '10.000000',
        '10.000000',
        '10.0000',
        '100.00',
    );

    createPurchasingHistoryRedesignPurchaseOrder(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->supplierItem,
        $this->inventoryItem,
        $this->unit,
        $this->manager,
        'PO-PARTIAL',
        PurchaseOrderStatus::PartiallyReceived,
        '2026-08-02',
        'Fresh Milk',
        'SKU-PARTIAL',
        '5.000000',
        '2.000000',
        '10.0000',
        '50.00',
    );

    createPurchasingHistoryRedesignPurchaseOrder(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->supplierItem,
        $this->inventoryItem,
        $this->unit,
        $this->manager,
        'PO-PENDING',
        PurchaseOrderStatus::Approved,
        '2026-08-03',
        'Red Onion',
        'SKU-PENDING',
        '3.000000',
        '0.000000',
        '10.0000',
        '30.00',
    );

    createPurchasingHistoryRedesignPurchaseOrder(
        $this->organization,
        $this->location,
        $this->supplier,
        $this->supplierItem,
        $this->inventoryItem,
        $this->unit,
        $this->manager,
        'PO-OVER',
        PurchaseOrderStatus::Received,
        '2026-08-04',
        'Jasmine Rice',
        'SKU-OVER',
        '2.000000',
        '3.000000',
        '20.0000',
        '40.00',
    );
});

test('report exposes filter-aware purchasing summary metrics', function () {
    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.purchasing-history.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 4)
                ->where('summary.totalPurchaseOrders', 4)
                ->where('summary.fullyReceivedCount', 2)
                ->where('summary.partialReceiptCount', 1)
                ->where('summary.totalSpend', '220.00')
                ->where('canViewCosts', true)
                ->where('canViewPurchaseOrders', true),
        );
});

test(
    'search matches purchase order number item supplier sku and supplier',
    function (
        string $search,
        int $expectedRows,
        ?string $expectedPurchaseOrder,
    ) {
        $response = $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('inventory.purchasing-history.index', [
                'search' => $search,
            ]))
            ->assertOk();

        $response->assertInertia(
            function (Assert $page) use (
                $expectedRows,
                $expectedPurchaseOrder,
            ): Assert {
                $page
                    ->component('inventory/purchasing-history')
                    ->has('rows', $expectedRows)
                    ->where(
                        'summary.totalPurchaseOrders',
                        $expectedRows,
                    );

                if ($expectedPurchaseOrder !== null) {
                    $page->where(
                        'rows.0.purchaseOrderNumber',
                        $expectedPurchaseOrder,
                    );
                }

                return $page;
            },
        );
    },
)->with([
    'purchase order number' => [
        'PO-PARTIAL',
        1,
        'PO-PARTIAL',
    ],
    'historical item name' => [
        'Fresh Milk',
        1,
        'PO-PARTIAL',
    ],
    'supplier sku' => [
        'SKU-PARTIAL',
        1,
        'PO-PARTIAL',
    ],
    'supplier name' => [
        'Redesign Supplier',
        4,
        null,
    ],
]);

test(
    'receipt state filter preserves authoritative receiving semantics',
    function (string $receiptState, string $purchaseOrderNumber) {
        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('inventory.purchasing-history.index', [
                'receipt_state' => $receiptState,
            ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('inventory/purchasing-history')
                    ->has('rows', 1)
                    ->where(
                        'rows.0.purchaseOrderNumber',
                        $purchaseOrderNumber,
                    )
                    ->where(
                        'rows.0.receiptState',
                        $receiptState,
                    )
                    ->where('summary.totalPurchaseOrders', 1),
            );
    },
)->with([
    'received' => [
        'received',
        'PO-RECEIVED',
    ],
    'partial' => [
        'partial',
        'PO-PARTIAL',
    ],
    'not received' => [
        'not_received',
        'PO-PENDING',
    ],
    'over received' => [
        'over_received',
        'PO-OVER',
    ],
]);

test('cost fields and spend are hidden without costs view permission', function () {
    $inventoryStaff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $inventoryStaff->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this
        ->actingAs($inventoryStaff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.purchasing-history.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->where('canViewCosts', false)
                ->where('summary.totalSpend', null)
                ->where('rows.0.unitPrice', null)
                ->where('rows.0.lineTotal', null),
        );

    $content = $this
        ->actingAs($inventoryStaff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.purchasing-history.export'))
        ->assertOk()
        ->streamedContent();

    $headerLine = explode("\n", trim($content))[0] ?? '';

    expect($headerLine)
        ->not->toContain('Unit Price')
        ->not->toContain('Line Total');
});

test('csv export preserves search and receipt state filters', function () {
    $content = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.purchasing-history.export', [
            'search' => 'Fresh Milk',
            'receipt_state' => 'partial',
        ]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('PO-PARTIAL')
        ->toContain('Fresh Milk')
        ->toContain('partial')
        ->not->toContain('PO-RECEIVED')
        ->not->toContain('PO-PENDING')
        ->not->toContain('PO-OVER');
});
