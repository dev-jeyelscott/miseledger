<?php

use App\Actions\Inventory\CreateBarcode;
use App\Enums\BarcodeSymbology;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRolloutClassification;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

test('create barcode action rejects an alternate unit outside the locked item', function () {
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

    expect(fn () => (new CreateBarcode)->handle(
        $organization,
        $item,
        '5555555555555',
        BarcodeSymbology::Ean13,
        $otherItemUnit->id,
        false,
        true,
    ))->toThrow(ModelNotFoundException::class);
});

/**
 * Create an authenticated organization member with an inventory item.
 *
 * @param  array<string, mixed>  $organizationAttributes
 * @return array{User, Organization, InventoryItem}
 */
function inventoryItemBarcodeContext(
    OrganizationRole $role,
    array $organizationAttributes = [],
): array {
    $user = User::factory()->create();
    $organization = Organization::factory()->create($organizationAttributes);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => $role,
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    return [$user, $organization, $item];
}

test('a manager can add a barcode and surrounding whitespace is trimmed without losing leading zeroes', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Manager,
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '  0123456789012  ',
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
        ->toBeTrue()
        ->and($barcode->active)
        ->toBeTrue();
});

test('code 128 preserves significant case and punctuation', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => '  Abc:01/xy-9?  ',
            'symbology' => BarcodeSymbology::Code128->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    expect(InventoryItemBarcode::query()->sole()->barcode)
        ->toBe('Abc:01/xy-9?');
});

test('fixed length retail symbologies reject invalid numeric structure', function (
    BarcodeSymbology $symbology,
    string $value,
) {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => $value,
            'symbology' => $symbology->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    $this->assertDatabaseCount('inventory_item_barcodes', 0);
})->with([
    'EAN-13 wrong length' => [BarcodeSymbology::Ean13, '123456789012'],
    'EAN-8 non numeric' => [BarcodeSymbology::Ean8, '1234567A'],
    'UPC-A wrong length' => [BarcodeSymbology::UpcA, '12345678901'],
    'UPC-E wrong length' => [BarcodeSymbology::UpcE, '1234567'],
]);

test('structural validation does not enforce retail barcode checksums', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

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
        ->assertRedirect(route('inventory.items.edit', $item));

    $this->assertDatabaseHas('inventory_item_barcodes', [
        'organization_id' => $organization->id,
        'inventory_item_id' => $item->id,
        'barcode' => '1111111111111',
    ]);
});

test('code 39 accepts its standard data characters and rejects lowercase input', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => 'ABC 123-.$/+%',
            'symbology' => BarcodeSymbology::Code39->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => 'abc-123',
            'symbology' => BarcodeSymbology::Code39->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    expect(InventoryItemBarcode::query()->count())->toBe(1);
});

test('other barcode identifiers remain bounded but otherwise generic', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => 'lot:abc/001?variant=x',
            'symbology' => BarcodeSymbology::Other->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.barcodes.store', $item), [
            'value' => str_repeat('x', 65),
            'symbology' => BarcodeSymbology::Other->value,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertSessionHasErrors('value');

    expect(InventoryItemBarcode::query()->count())->toBe(1);
});

test('an auditor cannot add or edit barcodes', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Auditor,
    );

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777777777777',
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
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

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

test('creating a barcode rejects a unit belonging to a different item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

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

test('an authorized member cannot manage barcodes for another organization item', function () {
    [$user, $organization] = inventoryItemBarcodeContext(
        OrganizationRole::Manager,
    );

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

test('an authorized member can reassociate a barcode to an alternate unit on the same item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Manager,
    );

    $unit = InventoryItemUnit::factory()
        ->for($item)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444444444444',
            'inventory_item_unit_id' => null,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.items.barcodes.update', [$item, $barcode]), [
            'value' => $barcode->barcode,
            'symbology' => BarcodeSymbology::Ean13->value,
            'inventory_item_unit_id' => $unit->id,
            'is_primary' => false,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    expect($barcode->fresh()->inventory_item_unit_id)->toBe($unit->id);
});

test('updating a barcode as the primary demotes the current primary for the item', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

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

test('a primary barcode can be deactivated and unmarked without inventing a replacement primary', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $currentPrimary = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '6666666666666',
            'primary' => true,
            'active' => true,
        ]);

    $secondary = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777777777777',
            'primary' => false,
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route(
                'inventory.items.barcodes.update',
                [$item, $currentPrimary],
            ),
            [
                'value' => $currentPrimary->barcode,
                'symbology' => BarcodeSymbology::Ean13->value,
                'is_primary' => false,
                'active' => false,
            ],
        )
        ->assertRedirect(route('inventory.items.edit', $item));

    expect($currentPrimary->fresh()->active)
        ->toBeFalse()
        ->and($currentPrimary->fresh()->primary)
        ->toBeFalse()
        ->and($secondary->fresh()->primary)
        ->toBeFalse();
});

test('commercial read only organizations can read barcode data but cannot mutate it', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Manager,
        [
            'trial_ends_at' => null,
            'rollout_classification' => OrganizationRolloutClassification::ImmediatelyBillable,
        ],
    );

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '8888888888888',
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $item))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('item.barcodes.0.id', $barcode->id)
                ->where('item.barcodes.0.value', $barcode->barcode),
        );

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
            'value' => '9999999999999',
            'symbology' => BarcodeSymbology::Ean13->value,
            'is_primary' => false,
            'active' => false,
        ])
        ->assertForbidden();

    expect($barcode->fresh()->barcode)
        ->toBe('8888888888888')
        ->and($barcode->fresh()->active)
        ->toBeTrue();
});

test('inventory item edit exposes configured barcodes to Inertia', function () {
    [$user, $organization, $item] = inventoryItemBarcodeContext(
        OrganizationRole::Owner,
    );

    $unit = InventoryItemUnit::factory()
        ->for($item)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $unit->id,
            'barcode' => '9999999999999',
            'symbology' => BarcodeSymbology::Code128,
            'primary' => true,
            'active' => false,
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
                ->where(
                    'item.barcodes.0.symbology',
                    BarcodeSymbology::Code128->value,
                )
                ->where('item.barcodes.0.isPrimary', true)
                ->where('item.barcodes.0.active', false)
                ->where(
                    'item.barcodes.0.inventoryItemUnit.id',
                    $unit->id,
                ),
        );
});
