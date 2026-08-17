<?php

use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
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
use Inertia\Testing\AssertableInertia as Assert;

function createPurchasingHistoryStorageLocation(
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

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation = createPurchasingHistoryStorageLocation(
        $this->organization,
        $this->location,
        'MAIN',
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Purchasing History Item',
        'sku' => 'PURCH-HIST',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Purchasing History Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-PURCH-HIST',
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-PURCH-HIST',
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => '2026-08-01',
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
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '10.000000',
        'unit_price' => '10.0000',
        'line_total' => '100.00',
        'received_base_quantity' => '0.000000',
    ]);
});

test(
    'report displays ordered versus received quantities and historical price for an unreceived line',
    function () {
        $url = route('inventory.purchasing-history.index');

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('inventory/purchasing-history')
                    ->has('rows', 1)
                    ->where('rows.0.purchaseOrderNumber', 'PO-PURCH-HIST')
                    ->where('rows.0.orderedQuantity', '10.000000')
                    ->where('rows.0.receivedBaseQuantity', '0.000000')
                    ->where('rows.0.remainingBaseQuantity', '10.000000')
                    ->where('rows.0.receiptState', 'not_received')
                    ->where('rows.0.unitPrice', '10.0000')
                    ->where('rows.0.lineTotal', '100.00'),
            );
    },
);

test('report reflects partial receipt state once part of the line is received', function () {
    $receipt = app(SaveGoodsReceipt::class)->handle(
        $this->organization,
        $this->actor,
        $this->purchaseOrder,
        [
            'number' => 'GR-PARTIAL',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $this->purchaseOrderLine->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'received_quantity' => '4',
                    'received_unit_of_measure_id' => $this->unit->id,
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

    $url = route('inventory.purchasing-history.index');

    $this
        ->actingAs($this->actor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 1)
                ->where('rows.0.receivedBaseQuantity', '4.000000')
                ->where('rows.0.remainingBaseQuantity', '6.000000')
                ->where('rows.0.receiptState', 'partial')
                ->where('rows.0.purchaseOrderStatus', 'partially_received'),
        );
});

test('report supplier filter excludes lines from other suppliers', function () {
    $otherSupplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherPurchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $otherSupplier->id,
        'number' => 'PO-OTHER-SUPPLIER',
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-05',
        'expected_delivery_date' => null,
        'subtotal' => '50.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '50.00',
        'notes' => null,
        'created_by' => $this->actor->id,
        'approved_by' => null,
        'approved_at' => null,
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $otherPurchaseOrder->id,
        'supplier_item_id' => $this->supplierItem->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'item_name_snapshot' => $this->inventoryItem->name,
        'supplier_sku_snapshot' => $this->supplierItem->supplier_sku,
        'ordered_quantity' => '5.000000',
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '5.000000',
        'unit_price' => '10.0000',
        'line_total' => '50.00',
        'received_base_quantity' => '0.000000',
    ]);

    $url = route('inventory.purchasing-history.index', [
        'supplier_id' => $this->supplier->id,
    ]);

    $this
        ->actingAs($this->actor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 1)
                ->where('rows.0.purchaseOrderNumber', 'PO-PURCH-HIST'),
        );
});

test('report location filter excludes purchase orders from other locations', function () {
    $otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherPurchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $otherLocation->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-OTHER-LOCATION',
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-05',
        'expected_delivery_date' => null,
        'subtotal' => '50.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '50.00',
        'notes' => null,
        'created_by' => $this->actor->id,
        'approved_by' => null,
        'approved_at' => null,
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $otherPurchaseOrder->id,
        'supplier_item_id' => $this->supplierItem->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'item_name_snapshot' => $this->inventoryItem->name,
        'supplier_sku_snapshot' => $this->supplierItem->supplier_sku,
        'ordered_quantity' => '5.000000',
        'purchase_unit_of_measure_id' => $this->unit->id,
        'base_quantity' => '5.000000',
        'unit_price' => '10.0000',
        'line_total' => '50.00',
        'received_base_quantity' => '0.000000',
    ]);

    $url = route('inventory.purchasing-history.index', [
        'location_id' => $this->location->id,
    ]);

    $this
        ->actingAs($this->actor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 1)
                ->where('rows.0.purchaseOrderNumber', 'PO-PURCH-HIST'),
        );
});

test('report date filter excludes purchase orders outside the range', function () {
    $url = route('inventory.purchasing-history.index', [
        'from' => '2026-08-02',
        'to' => '2026-08-31',
    ]);

    $this
        ->actingAs($this->actor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 0),
        );
});

test('report is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherSupplier = Supplier::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'dimension' => 'count',
        'active' => true,
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherUnit->id,
        'active' => true,
    ]);

    $otherSupplierItem = SupplierItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'supplier_id' => $otherSupplier->id,
        'inventory_item_id' => $otherItem->id,
        'purchase_unit_of_measure_id' => $otherUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '5.0000',
        'currency' => 'USD',
        'active' => true,
    ]);

    $otherPurchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $otherOrganization->id,
        'location_id' => $otherLocation->id,
        'supplier_id' => $otherSupplier->id,
        'number' => 'PO-OTHER-TENANT',
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-01',
        'expected_delivery_date' => null,
        'subtotal' => '50.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '50.00',
        'notes' => null,
        'created_by' => null,
        'approved_by' => null,
        'approved_at' => null,
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $otherPurchaseOrder->id,
        'supplier_item_id' => $otherSupplierItem->id,
        'inventory_item_id' => $otherItem->id,
        'item_name_snapshot' => $otherItem->name,
        'supplier_sku_snapshot' => $otherSupplierItem->supplier_sku,
        'ordered_quantity' => '5.000000',
        'purchase_unit_of_measure_id' => $otherUnit->id,
        'base_quantity' => '5.000000',
        'unit_price' => '5.0000',
        'line_total' => '25.00',
        'received_base_quantity' => '0.000000',
    ]);

    $url = route('inventory.purchasing-history.index');

    $this
        ->actingAs($this->actor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/purchasing-history')
                ->has('rows', 1)
                ->where('rows.0.purchaseOrderNumber', 'PO-PURCH-HIST'),
        );
});

test('report requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $url = route('inventory.purchasing-history.index');

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertForbidden();
});
