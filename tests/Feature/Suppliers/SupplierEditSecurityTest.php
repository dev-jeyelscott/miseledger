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

test('supplier edit does not serialize prices without costs view permission', function () {
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

    SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'current_price' => '125.5000',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('suppliers.edit', $supplier))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/edit')
                ->where('canViewCosts', false)
                ->where('supplier.items.0.currentPrice', null),
        );
});

test('supplier edit serializes current prices with costs view permission', function () {
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

    SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'current_price' => '125.5000',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('suppliers.edit', $supplier))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/edit')
                ->where('canViewCosts', true)
                ->where('supplier.items.0.currentPrice', '125.5000'),
        );
});
