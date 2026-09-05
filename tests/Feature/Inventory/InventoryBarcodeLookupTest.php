<?php

use App\Actions\Inventory\LookupBarcode;
use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;

test('a known base-item barcode resolves to its inventory item', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '0123456789012',
            'symbology' => BarcodeSymbology::Ean13,
        ]);

    $result = (new LookupBarcode)->handle($organization, '0123456789012');

    expect($result->found)->toBeTrue()
        ->and($result->barcode->id)->toBe($barcode->id)
        ->and($result->barcode->inventoryItem->id)->toBe($item->id)
        ->and($result->barcode->active)->toBeTrue()
        ->and($result->barcode->symbology)->toBe(BarcodeSymbology::Ean13)
        ->and($result->barcode->inventoryItemUnit)->toBeNull();
});

test('a known alternate-unit barcode resolves with its unit information', function () {
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
            'barcode' => '1111111111111',
        ]);

    $result = (new LookupBarcode)->handle($organization, '1111111111111');

    expect($result->found)->toBeTrue()
        ->and($result->barcode->id)->toBe($barcode->id)
        ->and($result->barcode->inventoryItemUnit)->not->toBeNull()
        ->and($result->barcode->inventoryItemUnit->id)->toBe($unit->id);
});

test('an inactive barcode does not resolve as a valid scanner match', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '2222222222222',
            'active' => false,
        ]);

    $result = (new LookupBarcode)->handle($organization, '2222222222222');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});

test('an active barcode for an inactive inventory item does not resolve', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create(['active' => false]);

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444444444444',
        ]);

    $result = (new LookupBarcode)->handle($organization, '4444444444444');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});

test('an active alternate-unit barcode with an inactive item unit does not resolve', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();
    $itemUnit = InventoryItemUnit::factory()
        ->for($item)
        ->create(['active' => false]);

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $itemUnit->id,
            'barcode' => '5555555555555',
        ]);

    $result = (new LookupBarcode)->handle($organization, '5555555555555');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});

test('an active alternate-unit barcode with an inactive unit of measure does not resolve', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();
    $unitOfMeasure = UnitOfMeasure::factory()
        ->for($organization)
        ->create(['active' => false]);
    $itemUnit = InventoryItemUnit::factory()
        ->for($item)
        ->for($unitOfMeasure)
        ->create();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $itemUnit->id,
            'barcode' => '6666666666666',
        ]);

    $result = (new LookupBarcode)->handle($organization, '6666666666666');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});

test('an unknown barcode produces an explicit not-found result', function () {
    $organization = Organization::factory()->create();

    $result = (new LookupBarcode)->handle($organization, '9999999999999');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});

test('a barcode belonging to another organization cannot be resolved', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'barcode' => '3333333333333',
        ]);

    $result = (new LookupBarcode)->handle($organization, '3333333333333');

    expect($result->found)->toBeFalse()
        ->and($result->barcode)->toBeNull();
});
