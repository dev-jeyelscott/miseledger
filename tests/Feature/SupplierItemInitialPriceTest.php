<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use App\Models\UnitOfMeasure;
use App\Models\User;

test('supplier item creation can record its initial price', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $baseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $baseUnit->id,
            'active' => true,
        ]);

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $supplier), [
            'inventory_item_id' => $inventoryItem->id,
            'supplier_sku' => 'CASE-INITIAL-PRICE',
            'description' => 'Initial priced case',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'base_quantity' => '10.000000',
            'price' => '120.5000',
            'active' => true,
        ])
        ->assertRedirect(
            route('suppliers.edit', $supplier),
        );

    $supplierItem = SupplierItem::query()
        ->where('organization_id', $organization->id)
        ->where('supplier_id', $supplier->id)
        ->where('supplier_sku', 'CASE-INITIAL-PRICE')
        ->sole();

    expect($supplierItem->current_price)
        ->toBe('120.5000')
        ->and($supplierItem->currency)
        ->toBe('PHP');

    $this->assertDatabaseHas('supplier_item_prices', [
        'organization_id' => $organization->id,
        'supplier_item_id' => $supplierItem->id,
        'price' => '120.5000',
        'currency' => 'PHP',
    ]);

    expect(
        SupplierItemPrice::query()
            ->where('supplier_item_id', $supplierItem->id)
            ->count(),
    )->toBe(1);
});

test('supplier item creation still permits no initial price', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $baseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $baseUnit->id,
            'active' => true,
        ]);

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create([
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $supplier), [
            'inventory_item_id' => $inventoryItem->id,
            'supplier_sku' => 'CASE-NO-PRICE',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'base_quantity' => '10.000000',
            'price' => '',
            'active' => true,
        ])
        ->assertRedirect(
            route('suppliers.edit', $supplier),
        );

    $supplierItem = SupplierItem::query()
        ->where('supplier_sku', 'CASE-NO-PRICE')
        ->sole();

    expect($supplierItem->current_price)->toBeNull();

    expect(
        SupplierItemPrice::query()
            ->where('supplier_item_id', $supplierItem->id)
            ->count(),
    )->toBe(0);
});
