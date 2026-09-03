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

test('recording a new supplier price appends history instead of replacing it', function () {
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

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create(['active' => true]);

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $purchaseUnit->id,
            'active' => true,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create(['active' => true]);

    $supplierItem = SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'active' => true,
            'current_price' => '100.0000',
        ]);

    $originalPrice = SupplierItemPrice::factory()
        ->for($organization)
        ->for($supplierItem)
        ->create([
            'price' => '100.0000',
            'currency' => 'PHP',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(
            route(
                'suppliers.items.prices.store',
                [$supplier, $supplierItem],
            ),
            ['price' => '110.2500'],
        )
        ->assertRedirect(
            route('suppliers.items.edit', [$supplier, $supplierItem]),
        );

    $this->assertDatabaseHas('supplier_item_prices', [
        'id' => $originalPrice->id,
        'price' => '100.0000',
    ]);

    expect(
        SupplierItemPrice::query()
            ->where('supplier_item_id', $supplierItem->id)
            ->count(),
    )->toBe(2);

    $supplierItem->refresh();

    expect($supplierItem->current_price)->toBe('110.2500');

    $this->get(
        route('suppliers.items.edit', [$supplier, $supplierItem]),
    )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/items/edit')
                ->where('supplierItem.currentPrice', '110.2500')
                ->has('supplierItem.prices', 2)
                ->where('supplierItem.prices.0.price', '110.2500')
                ->where('supplierItem.prices.1.price', '100.0000'),
        );
});

test('supplier item edit renders read-only mapping details without manage permission', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::InventoryStaff,
        ]);

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create(['active' => true]);

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $purchaseUnit->id,
            'active' => true,
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
                ->where('canManage', false),
        );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route(
                'suppliers.items.update',
                [$supplier, $supplierItem],
            ),
            [
                'inventory_item_id' => $inventoryItem->id,
                'supplier_sku' => 'BLOCKED-UPDATE',
                'purchase_unit_of_measure_id' => $purchaseUnit->id,
                'base_quantity' => '1.000000',
                'active' => true,
            ],
        )
        ->assertForbidden();
});
