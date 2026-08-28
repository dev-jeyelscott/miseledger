<?php

use App\Enums\BarcodeSymbology;
use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('an inventory item can have multiple barcodes across supported symbologies', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    foreach (BarcodeSymbology::cases() as $symbology) {
        InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'symbology' => $symbology,
                'barcode' => $symbology->value.'-value',
            ]);
    }

    expect($item->barcodes()->count())
        ->toBe(count(BarcodeSymbology::cases()));
});

test('a barcode value is unique within its organization but reusable by another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '0123456789012',
        ]);

    InventoryItemBarcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'barcode' => '0123456789012',
        ]);

    expect(InventoryItemBarcode::query()->count())->toBe(2);

    expect(function () use ($organization, $item): void {
        InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => '0123456789012',
            ]);
    })->toThrow(QueryException::class);
});

test('at most one barcode per item can be marked primary', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '1111111111111',
            'primary' => true,
        ]);

    expect(function () use ($organization, $item): void {
        InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => '2222222222222',
                'primary' => true,
            ]);
    })->toThrow(QueryException::class);
});

test('a barcode can be linked to an alternate unit belonging to the same item', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $unit = InventoryItemUnit::factory()
        ->for($item)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $unit->id,
            'barcode' => '3333333333333',
        ]);

    expect($barcode->inventoryItemUnit->id)->toBe($unit->id);
});

test('a barcode cannot be linked to a unit belonging to a different item', function () {
    $organization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $otherItemUnit = InventoryItemUnit::factory()
        ->for($otherItem)
        ->create();

    expect(function () use ($organization, $item, $otherItemUnit): void {
        InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'inventory_item_unit_id' => $otherItemUnit->id,
                'barcode' => '4444444444444',
            ]);
    })->toThrow(QueryException::class);
});

/**
 * Create an authenticated inventory owner with an item to manage barcodes for.
 *
 * @return array{User, Organization, InventoryItem}
 */
function inventoryItemBarcodeOwnerContext(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    return [$user, $organization, $item];
}

test('a manager can add a barcode to an inventory item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '0123456789012',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => true,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    $barcode = InventoryItemBarcode::query()->sole();

    expect($barcode->inventory_item_id)
        ->toBe($item->id)
        ->and($barcode->barcode)
        ->toBe('0123456789012')
        ->and($barcode->primary)
        ->toBeTrue();
});

test('an auditor cannot add or edit barcodes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '9999999999999',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertForbidden();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.items.barcodes.update', [$item, $barcode]), [
            'value' => '8888888888888',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertForbidden();

    expect(InventoryItemBarcode::query()->count())->toBe(1);
});

test('creating a barcode rejects a duplicate value within the active organization', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '1111111111111',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '1111111111111',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    expect(InventoryItemBarcode::query()->count())->toBe(1);
});

test('creating a barcode rejects a malformed value', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => 'not valid!',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    $this->assertDatabaseCount('inventory_item_barcodes', 0);
});

test('creating a barcode rejects an excessively long value', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => str_repeat('1', 65),
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    $this->assertDatabaseCount('inventory_item_barcodes', 0);
});

test('creating a barcode rejects a unit belonging to a different item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $otherItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $otherItemUnit = InventoryItemUnit::factory()
        ->for($otherItem)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '2222222222222',
            'symbology' => BarcodeSymbology::Ean13->value,
            'inventory_item_unit_id' => $otherItemUnit->id,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_item_unit_id');

    $this->assertDatabaseCount('inventory_item_barcodes', 0);
});

test('a manager cannot manage barcodes for another organization\'s item', function () {
    [$user, $organization] = inventoryItemBarcodeOwnerContext();

    $otherOrganization = Organization::factory()->create();
    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $otherItem), [
            'value' => '3333333333333',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_item_barcodes', 0);
});

test('updating a barcode as the primary demotes the current primary for the item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $currentPrimary = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444444444444',
            'primary' => true,
        ]);

    $challenger = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '5555555555555',
            'primary' => false,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route(
                'inventory.items.barcodes.update',
                [$item, $challenger],
            ),
            [
                'value' => $challenger->barcode,
                'symbology' => BarcodeSymbology::Ean13->value,
                'is_primary' => true,
                'active' => true,
            ],
        )
        ->assertRedirect(route('inventory.items.edit', $item));

    expect($challenger->fresh()->primary)
        ->toBeTrue()
        ->and($currentPrimary->fresh()->primary)
        ->toBeFalse();
});

test('inventory item edit exposes configured barcodes to Inertia', function () {
    [$user, $organization, $item] = inventoryItemBarcodeOwnerContext();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '6666666666666',
            'primary' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $item))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/edit')
                ->where('item.barcodes.0.id', $barcode->id)
                ->where('item.barcodes.0.value', $barcode->barcode)
                ->where('item.barcodes.0.isPrimary', true),
        );
});
