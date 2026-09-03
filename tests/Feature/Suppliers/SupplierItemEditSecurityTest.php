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
use Inertia\Testing\AssertableInertia as Assert;

test('supplier item edit does not serialize price or price history without costs view permission', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::InventoryStaff,
        ]);

    $supplier = Supplier::factory()->for($organization)->create();
    $inventoryItem = InventoryItem::factory()->for($organization)->create();
    $purchaseUnit = UnitOfMeasure::factory()->for($organization)->create();

    $supplierItem = SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'current_price' => '125.5000',
        ]);

    SupplierItemPrice::factory()
        ->for($organization)
        ->for($supplierItem)
        ->create([
            'price' => '125.5000',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(
            route(
                'suppliers.items.edit',
                [$supplier, $supplierItem],
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/items/edit')
                ->where('canViewCosts', false)
                ->where('supplierItem.currentPrice', null)
                ->where('supplierItem.prices', []),
        );
});

test('supplier item edit serializes price and price history with costs view permission', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $supplier = Supplier::factory()->for($organization)->create();
    $inventoryItem = InventoryItem::factory()->for($organization)->create();
    $purchaseUnit = UnitOfMeasure::factory()->for($organization)->create();

    $supplierItem = SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'current_price' => '125.5000',
        ]);

    SupplierItemPrice::factory()
        ->for($organization)
        ->for($supplierItem)
        ->create([
            'price' => '125.5000',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(
            route(
                'suppliers.items.edit',
                [$supplier, $supplierItem],
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/items/edit')
                ->where('canViewCosts', true)
                ->where('supplierItem.currentPrice', '125.5000')
                ->has('supplierItem.prices', 1),
        );
});
