<?php

use App\Enums\BarcodeSymbology;
use App\Models\Barcode;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use Illuminate\Database\QueryException;

test('an inventory item can have multiple barcodes across supported symbologies', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    foreach (BarcodeSymbology::cases() as $symbology) {
        Barcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'symbology' => $symbology,
                'value' => $symbology->value.'-value',
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

    Barcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'value' => '0123456789012',
        ]);

    Barcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'value' => '0123456789012',
        ]);

    expect(Barcode::query()->count())->toBe(2);

    expect(function () use ($organization, $item): void {
        Barcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'value' => '0123456789012',
            ]);
    })->toThrow(QueryException::class);
});

test('at most one barcode per item can be marked primary', function () {
    $organization = Organization::factory()->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    Barcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'value' => '1111111111111',
            'is_primary' => true,
        ]);

    expect(function () use ($organization, $item): void {
        Barcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'value' => '2222222222222',
                'is_primary' => true,
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

    $barcode = Barcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $unit->id,
            'value' => '3333333333333',
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
        Barcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'inventory_item_unit_id' => $otherItemUnit->id,
                'value' => '4444444444444',
            ]);
    })->toThrow(QueryException::class);
});
