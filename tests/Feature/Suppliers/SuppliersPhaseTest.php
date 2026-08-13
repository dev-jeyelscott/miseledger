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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create an organization scoped supplier', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.store'), [
            'name' => '  Metro   Food Supply ',
            'code' => ' metro ',
            'contact_name' => '  Maria   Santos ',
            'email' => 'SALES@EXAMPLE.COM',
            'phone' => '09170000000',
            'payment_terms' => ' Net 30 ',
            'lead_time_days' => '3',
            'active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', [
        'organization_id' => $organization->id,
        'name' => 'Metro Food Supply',
        'code' => 'METRO',
        'contact_name' => 'Maria Santos',
        'email' => 'sales@example.com',
        'payment_terms' => 'Net 30',
        'lead_time_days' => 3,
        'active' => true,
    ]);
});

test('supplier code is unique inside an organization', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    Supplier::factory()
        ->for($organization)
        ->create([
            'code' => 'METRO',
        ]);

    Supplier::factory()
        ->for($otherOrganization)
        ->create([
            'code' => 'METRO',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.store'), [
            'name' => 'Duplicate supplier',
            'code' => 'metro',
            'active' => true,
        ])
        ->assertSessionHasErrors('code');
});

test('an auditor can view suppliers but cannot modify them', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('suppliers.edit', $supplier))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('suppliers.update', $supplier), [
            'name' => 'Changed',
            'code' => $supplier->code,
            'active' => true,
        ])
        ->assertForbidden();

    expect($supplier->refresh()->name)
        ->not->toBe('Changed');
});

test('supplier index does not expose another organization supplier', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $ownSupplier = Supplier::factory()
        ->for($organization)
        ->create();

    Supplier::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('suppliers/index')
                ->has('suppliers', 1)
                ->where('suppliers.0.id', $ownSupplier->id),
        );
});

test('a supplier can be deactivated without deleting it', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
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
        ->put(route('suppliers.update', $supplier), [
            'name' => $supplier->name,
            'code' => $supplier->code,
            'active' => false,
        ])
        ->assertRedirect(
            route('suppliers.edit', $supplier),
        );

    expect($supplier->refresh()->active)->toBeFalse();

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
    ]);
});

test('a referenced supplier cannot be hard deleted', function () {
    $organization = Organization::factory()->create();

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
        ]);

    expect(
        fn () => DB::transaction(
            fn () => $supplier->delete(),
        ),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
    ]);
});

test('supplier item requires an internal item purchase unit and base quantity', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $supplier), [
            'supplier_sku' => 'CASE-001',
            'active' => true,
        ])
        ->assertSessionHasErrors([
            'inventory_item_id',
            'purchase_unit_of_measure_id',
            'base_quantity',
        ]);

    $this->assertDatabaseCount('supplier_items', 0);
});

test('supplier item rejects a cross tenant inventory item', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $supplier), [
            'inventory_item_id' => $otherItem->id,
            'supplier_sku' => 'CASE-001',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'base_quantity' => '10.000000',
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_item_id');

    $this->assertDatabaseCount('supplier_items', 0);
});

test('supplier sku is unique per supplier but reusable by another supplier', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $otherSupplier = Supplier::factory()
        ->for($organization)
        ->create();

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'supplier_sku' => 'CASE-001',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $supplier), [
            'inventory_item_id' => $inventoryItem->id,
            'supplier_sku' => 'case-001',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'base_quantity' => '10.000000',
            'active' => true,
        ])
        ->assertSessionHasErrors('supplier_sku');

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('suppliers.items.store', $otherSupplier), [
            'inventory_item_id' => $inventoryItem->id,
            'supplier_sku' => 'case-001',
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'base_quantity' => '10.000000',
            'active' => true,
        ])
        ->assertRedirect(
            route('suppliers.edit', $otherSupplier),
        );

    expect(
        SupplierItem::query()
            ->where('supplier_sku', 'CASE-001')
            ->count(),
    )->toBe(2);
});

test('supplier prices append history and update current price', function () {
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

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $purchaseUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $supplierItem = SupplierItem::factory()
        ->for($organization)
        ->for($supplier)
        ->for($inventoryItem)
        ->create([
            'purchase_unit_of_measure_id' => $purchaseUnit->id,
            'current_price' => null,
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
            [
                'price' => '120.5000',
            ],
        )
        ->assertRedirect();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(
            route(
                'suppliers.items.prices.store',
                [$supplier, $supplierItem],
            ),
            [
                'price' => '135.7500',
            ],
        )
        ->assertRedirect();

    expect(SupplierItemPrice::query()->count())
        ->toBe(2)
        ->and(
            SupplierItemPrice::query()
                ->orderBy('id')
                ->pluck('price')
                ->all(),
        )
        ->toBe([
            '120.5000',
            '135.7500',
        ])
        ->and($supplierItem->refresh()->current_price)
        ->toBe('135.7500')
        ->and($supplierItem->currency)
        ->toBe('PHP');
});

test('a new price cannot be recorded for an inactive supplier item', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $supplier = Supplier::factory()
        ->for($organization)
        ->create();

    $inventoryItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $purchaseUnit = UnitOfMeasure::factory()
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
        ->post(
            route(
                'suppliers.items.prices.store',
                [$supplier, $supplierItem],
            ),
            [
                'price' => '100.0000',
            ],
        )
        ->assertSessionHasErrors('price');

    $this->assertDatabaseCount('supplier_item_prices', 0);
});

test('cross tenant supplier item editing is not exposed', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $otherSupplier = Supplier::factory()
        ->for($otherOrganization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    $otherSupplierItem = SupplierItem::factory()
        ->for($otherOrganization)
        ->for($otherSupplier)
        ->for($otherItem)
        ->create([
            'purchase_unit_of_measure_id' => $otherUnit->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(
            route(
                'suppliers.items.edit',
                [$otherSupplier, $otherSupplierItem],
            ),
        )
        ->assertNotFound();
});
