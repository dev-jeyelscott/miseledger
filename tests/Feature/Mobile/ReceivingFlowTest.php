<?php

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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $this->organization->id;
    $storageLocation->location_id = $this->location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();
    $this->storageLocation = $storageLocation;

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Each',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Mobile Receiving Item',
        'sku' => 'MOBILE-RECEIVE',
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
        'supplier_sku' => 'SUP-MOBILE-RECEIVE',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '15.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $this->actor = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-MOBILE-FLOW',
        'status' => PurchaseOrderStatus::Approved,
        'origin' => 'desktop',
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '150.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '150.00',
        'notes' => null,
        'created_by' => $this->actor->id,
        'approved_by' => $this->actor->id,
        'approved_at' => now(),
    ]);

    $this->purchaseOrderLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'supplier_item_id' => $this->supplierItem->id,
        'inventory_item_id' => $this->item->id,
        'item_name_snapshot' => $this->item->name,
        'supplier_sku_snapshot' => $this->supplierItem->supplier_sku,
        'ordered_quantity' => '10.000000',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '10.000000',
        'unit_price' => '15.0000',
        'line_total' => '150.00',
        'received_base_quantity' => '0.000000',
    ]);

    test()->actingAs($this->actor)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->location->id],
    ]);
});

test('a full PO-based scan, review, and finalize matches a desktop-entered receipt', function () {
    $this->post('/mobile/receiving/lines', [
        'purchase_order_id' => $this->purchaseOrder->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '10',
    ])->assertRedirect();

    $receipt = GoodsReceipt::query()
        ->where('organization_id', $this->organization->id)
        ->where('purchase_order_id', $this->purchaseOrder->id)
        ->sole();

    expect($receipt->status)->toBe(GoodsReceiptStatus::Draft)
        ->and($receipt->lines)->toHaveCount(1);

    $this->get("/mobile/receiving/{$receipt->id}/review")
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/receiving/review')
                ->where('goodsReceipt.lines.0.itemName', $this->item->name)
                ->where('goodsReceipt.hasZeroCostLine', false),
        );

    $this->post("/mobile/receiving/{$receipt->id}/finalize")
        ->assertRedirect();

    expect($receipt->refresh()->status)->toBe(GoodsReceiptStatus::Finalized)
        ->and($this->purchaseOrderLine->refresh()->received_base_quantity)
        ->toBe('10.000000')
        ->and($this->purchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Received)
        ->and(
            StockMovement::query()
                ->where('type', StockMovementType::PurchaseReceipt->value)
                ->count(),
        )->toBe(1)
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe('10.000000');

    // Double-submitting finalize is a no-op, not a duplicate movement.
    $this->post("/mobile/receiving/{$receipt->id}/finalize")
        ->assertRedirect();

    expect(
        StockMovement::query()
            ->where('type', StockMovementType::PurchaseReceipt->value)
            ->count(),
    )->toBe(1);
});

test('an ad-hoc receipt creates an approved PO and a supplier item, flagged origin mobile_ad_hoc', function () {
    $newItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Unplanned Delivery Item',
        'sku' => 'UNPLANNED-ITEM',
        'active' => true,
    ]);

    $this->post('/mobile/receiving/lines', [
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $newItem->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '3',
    ])->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('organization_id', $this->organization->id)
        ->where('origin', 'mobile_ad_hoc')
        ->sole();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Approved);

    $receipt = GoodsReceipt::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->sole();

    $this->get("/mobile/receiving/{$receipt->id}/review")
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/receiving/review')
                ->where('goodsReceipt.isAdHoc', true)
                ->where('goodsReceipt.hasZeroCostLine', true),
        );

    $this->post("/mobile/receiving/{$receipt->id}/finalize")
        ->assertRedirect();

    expect($receipt->refresh()->status)->toBe(GoodsReceiptStatus::Finalized)
        ->and(
            SupplierItem::query()
                ->where('organization_id', $this->organization->id)
                ->where('inventory_item_id', $newItem->id)
                ->exists(),
        )->toBeTrue();
});

test('a user without ReceivingFinalize permission is forbidden from adding a line', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $viewer->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this->actingAs($viewer)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->location->id],
    ])->post('/mobile/receiving/lines', [
        'purchase_order_id' => $this->purchaseOrder->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '1',
    ])->assertForbidden();
});
