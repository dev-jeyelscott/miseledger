<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->unitOfMeasure = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);
});

function createPurchaseOrderSupplierItemForTest(
    Organization $organization,
    Supplier $supplier,
    UnitOfMeasure $unitOfMeasure,
    string $name,
    string $supplierSku,
): SupplierItem {
    $inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unitOfMeasure->id,
        'name' => $name,
        'active' => true,
    ]);

    return SupplierItem::factory()->create([
        'organization_id' => $organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $inventoryItem->id,
        'purchase_unit_of_measure_id' => $unitOfMeasure->id,
        'supplier_sku' => $supplierSku,
        'current_price' => '12.5000',
        'active' => true,
    ]);
}

test('purchase order form omits the supplier item catalog', function () {
    foreach (range(1, 30) as $number) {
        createPurchaseOrderSupplierItemForTest(
            $this->organization,
            $this->supplier,
            $this->unitOfMeasure,
            'Form item '.$number,
            'FORM-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        );
    }

    $this->actingAs($this->user)
        ->get(route('purchase-orders.create'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('purchase-orders/form')
                ->has('supplierOptions', 1)
                ->missing('supplierOptions.0.items')
                ->has('selectedSupplierItems', 0),
        );
});

test('supplier item endpoint returns a tenant-scoped searchable page', function () {
    $target = createPurchaseOrderSupplierItemForTest(
        $this->organization,
        $this->supplier,
        $this->unitOfMeasure,
        'Target flour',
        'TARGET-001',
    );

    foreach (range(1, 25) as $number) {
        createPurchaseOrderSupplierItemForTest(
            $this->organization,
            $this->supplier,
            $this->unitOfMeasure,
            'Catalog item '.$number,
            'CAT-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        );
    }

    $inactiveItem = createPurchaseOrderSupplierItemForTest(
        $this->organization,
        $this->supplier,
        $this->unitOfMeasure,
        'Inactive item',
        'INACTIVE-001',
    );
    $inactiveItem->update(['active' => false]);

    $otherOrganization = Organization::factory()->create();
    $otherSupplier = Supplier::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);
    $otherUnitOfMeasure = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    createPurchaseOrderSupplierItemForTest(
        $otherOrganization,
        $otherSupplier,
        $otherUnitOfMeasure,
        'Target flour from another organization',
        'TARGET-FOREIGN',
    );

    $this->actingAs($this->user)
        ->getJson(route('purchase-orders.supplier-items', [
            'supplier' => $this->supplier->id,
            'search' => 'Target',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.itemName', 'Target flour')
        ->assertJsonPath('data.0.currentPrice', '12.5000')
        ->assertJsonPath('meta.nextPage', null)
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->user)
        ->getJson(route('purchase-orders.supplier-items', [
            'supplier' => $this->supplier->id,
        ]))
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.nextPage', 2);
});
