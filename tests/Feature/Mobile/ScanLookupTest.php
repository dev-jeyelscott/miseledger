<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;

/**
 * Create one active storage destination for scan-lookup tests.
 */
function createScanStorageLocationForTest(
    Organization $organization,
    Location $location,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

/**
 * Sign in a Manager as an organization member with an active mobile session.
 */
function actingAsScanManager(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Manager,
    ]);

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    test()->actingAs($user)->withSession([
        'active_organization_id' => $organization->id,
        'mobile_location_by_org' => [$organization->id => $location->id],
    ]);

    return [$organization, $user, $location];
}

test('an exact active-barcode match returns a single match with current stock', function () {
    [$organization, $user, $location] = actingAsScanManager();

    $storageLocation = createScanStorageLocationForTest($organization, $location);

    $item = InventoryItem::factory()->for($organization)->create();
    $baseUnit = UnitOfMeasure::find($item->base_unit_of_measure_id);

    InventoryItemBarcode::factory()->for($item)->create([
        'organization_id' => $organization->id,
        'barcode' => '0123456789012',
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '12',
        baseUnitOfMeasure: $baseUnit,
        referenceType: 'opening_balance',
        referenceId: 1,
        occurredAt: now()->subSecond(),
        actor: $user,
        idempotencyKey: 'opening:scan-lookup-test',
        inboundUnitCost: '2.50',
    );

    $this->postJson('/mobile/scan/lookup', ['value' => '0123456789012'])
        ->assertOk()
        ->assertJson([
            'match' => [
                'inventoryItemId' => $item->id,
                'name' => $item->name,
                'stockAtActiveLocation' => [
                    'quantityOnHand' => '12.000000',
                ],
                'availableActions' => ['receive', 'count', 'waste', 'transfer', 'details'],
            ],
        ]);
});

test('an inactive barcode falls through to the loose search fallback', function () {
    [$organization] = actingAsScanManager();

    $item = InventoryItem::factory()->for($organization)->create(['name' => 'Roma Tomato']);

    InventoryItemBarcode::factory()->for($item)->create([
        'organization_id' => $organization->id,
        'barcode' => 'ROMA-TOMATO-1',
        'active' => false,
    ]);

    $this->postJson('/mobile/scan/lookup', ['value' => 'Roma Tomato'])
        ->assertOk()
        ->assertJson([
            'match' => ['inventoryItemId' => $item->id],
        ]);
});

test('a loose-search value matching several items returns matches for the selection screen', function () {
    [$organization] = actingAsScanManager();

    $first = InventoryItem::factory()->for($organization)->create(['sku' => 'SKU-4471']);
    $second = InventoryItem::factory()->for($organization)->create(['sku' => 'SKU-4472']);

    $response = $this->postJson('/mobile/scan/lookup', ['value' => 'SKU-447'])
        ->assertOk()
        ->assertJsonCount(2, 'matches');

    expect(
        collect($response->json('matches'))->pluck('inventoryItemId')->sort()->values()->all(),
    )->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

test('an unknown value returns a 404 not-found payload', function () {
    actingAsScanManager();

    $this->postJson('/mobile/scan/lookup', ['value' => 'no-such-thing-9999'])
        ->assertNotFound()
        ->assertJson([
            'notFound' => true,
            'query' => 'no-such-thing-9999',
        ]);
});

test('a barcode belonging to a different organization never matches', function () {
    [$organization] = actingAsScanManager();

    $otherOrganization = Organization::factory()->create();
    $otherItem = InventoryItem::factory()->for($otherOrganization)->create();

    InventoryItemBarcode::factory()->for($otherItem)->create([
        'organization_id' => $otherOrganization->id,
        'barcode' => '9988776655443',
    ]);

    $this->postJson('/mobile/scan/lookup', ['value' => '9988776655443'])
        ->assertNotFound()
        ->assertJson(['notFound' => true]);
});

test('a Kitchen Staff account only sees waste and details actions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    $item = InventoryItem::factory()->for($organization)->create();

    InventoryItemBarcode::factory()->for($item)->create([
        'organization_id' => $organization->id,
        'barcode' => '1112223334445',
    ]);

    $this->actingAs($user)
        ->withSession([
            'active_organization_id' => $organization->id,
            'mobile_location_by_org' => [$organization->id => $location->id],
        ])
        ->postJson('/mobile/scan/lookup', ['value' => '1112223334445'])
        ->assertOk()
        ->assertJson([
            'match' => [
                'availableActions' => ['waste', 'details'],
            ],
        ]);
});
