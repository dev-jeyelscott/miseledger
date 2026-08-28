<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemOptionValue;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function inventoryItemShowContext(OrganizationRole $role = OrganizationRole::Owner): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => $role]);
    $unit = UnitOfMeasure::factory()->for($organization)->create(['name' => 'Kilogram', 'symbol' => 'kg']);

    return [$user, $organization, $unit];
}

test('an inventory viewer can inspect a tenant-owned item without an edit capability', function () {
    [$user, $organization, $unit] = inventoryItemShowContext(OrganizationRole::Auditor);
    $item = InventoryItem::factory()->for($organization)->create(['base_unit_of_measure_id' => $unit->id]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.show', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('inventory/items/show')
            ->where('item.id', $item->id)
            ->where('canManage', false));
});

test('inventory item detail is scoped to the active organization', function () {
    [$user, $organization] = inventoryItemShowContext();
    $otherOrganization = Organization::factory()->create();
    $otherUnit = UnitOfMeasure::factory()->for($otherOrganization)->create();
    $otherItem = InventoryItem::factory()->for($otherOrganization)->create(['base_unit_of_measure_id' => $otherUnit->id]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.show', $otherItem))
        ->assertNotFound();
});

test('inventory item detail includes conversions barcode state and editability metadata', function () {
    [$user, $organization, $unit] = inventoryItemShowContext();
    $alternateUnit = UnitOfMeasure::factory()->for($organization)->create(['name' => 'Case', 'symbol' => 'cs']);
    $item = InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
        'name' => 'Flour',
        'sku' => 'FLOUR-001',
    ]);
    $conversion = InventoryItemUnit::factory()->for($item)->create([
        'unit_of_measure_id' => $alternateUnit->id,
        'quantity_in_base_unit' => '10.000000',
    ]);
    InventoryItemBarcode::factory()->for($item)->create([
        'inventory_item_unit_id' => $conversion->id,
        'barcode' => '0123456789012',
        'primary' => true,
        'active' => true,
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.show', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('inventory/items/show')
            ->where('item.name', 'Flour')
            ->where('item.baseUnitOfMeasure.symbol', 'kg')
            ->where('item.unitConversions.0.unitOfMeasure.symbol', 'cs')
            ->where('item.barcodes.0.value', '0123456789012')
            ->where('item.barcodes.0.isPrimary', true)
            ->where('item.barcodes.0.active', true)
            ->where('item.editability.baseUnitOfMeasure.editable', false)
            ->where('item.editability.productFamily.editable', true));
});

test('inventory item edit metadata locks product family after option values are saved', function () {
    [$user, $organization, $unit] = inventoryItemShowContext();
    $item = InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
    ]);
    InventoryItemOptionValue::factory()->for($item)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('item.editability.productFamily.editable', false)
            ->where(
                'item.editability.productFamily.reason',
                'The product family cannot be changed while this item has assigned option values.',
            ));
});
