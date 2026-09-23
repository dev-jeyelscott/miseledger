<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Sign in a Manager as an organization member with an active mobile session.
 *
 * @return array{0: Organization, 1: User, 2: Location}
 */
function actingAsSearchManager(): array
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

test('matches items by name substring', function () {
    [$organization] = actingAsSearchManager();

    $tomato = InventoryItem::factory()->for($organization)->create(['name' => 'Roma Tomato']);
    InventoryItem::factory()->for($organization)->create(['name' => 'Basil']);

    $this->getJson('/mobile/scan/search?q=tomato')
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.inventoryItemId', $tomato->id);
});

test('matches items by SKU substring', function () {
    [$organization] = actingAsSearchManager();

    $item = InventoryItem::factory()->for($organization)->create(['sku' => 'SKU-90123']);

    $this->getJson('/mobile/scan/search?q=90123')
        ->assertOk()
        ->assertJsonPath('matches.0.inventoryItemId', $item->id);
});

test('matches items by barcode substring', function () {
    [$organization] = actingAsSearchManager();

    $item = InventoryItem::factory()->for($organization)->create();

    InventoryItemBarcode::factory()->for($item)->create([
        'organization_id' => $organization->id,
        'barcode' => '5551234567890',
    ]);

    $this->getJson('/mobile/scan/search?q=1234567')
        ->assertOk()
        ->assertJsonPath('matches.0.inventoryItemId', $item->id);
});

test('an empty or single-character query is rejected by validation', function () {
    actingAsSearchManager();

    $this->getJson('/mobile/scan/search?q=')
        ->assertUnprocessable();

    $this->getJson('/mobile/scan/search?q=a')
        ->assertUnprocessable();
});

test('results are scoped to the active organization', function () {
    [$organization] = actingAsSearchManager();

    $otherOrganization = Organization::factory()->create();
    InventoryItem::factory()->for($otherOrganization)->create(['name' => 'Shared Widget']);
    $ownItem = InventoryItem::factory()->for($organization)->create(['name' => 'Shared Widget']);

    $this->getJson('/mobile/scan/search?q=widget')
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.inventoryItemId', $ownItem->id);
});
