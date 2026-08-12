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

test('supplier item edit retains inactive current item and purchase unit options', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $baseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Base Unit',
            'symbol' => 'bu',
            'active' => true,
        ]);

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $baseUnit->id,
            'name' => 'Legacy Ingredient',
            'sku' => 'LEGACY-001',
            'active' => false,
        ]);

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Legacy Case',
            'symbol' => 'case',
            'active' => false,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $supplierItem = SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'active' => false,
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
                ->has('itemOptions', 1)
                ->where(
                    'itemOptions.0.id',
                    $inventoryItem->id,
                )
                ->where(
                    'itemOptions.0.active',
                    false,
                )
                ->has('unitOptions', 2)
                ->where(
                    'unitOptions.1.id',
                    $purchaseUnit->id,
                )
                ->where(
                    'unitOptions.1.active',
                    false,
                ),
        );
});
