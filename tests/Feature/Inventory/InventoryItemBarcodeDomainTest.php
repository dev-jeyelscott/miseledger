<?php

use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use Illuminate\Database\QueryException;

test('barcode strings preserve leading zeroes and cast domain state', function () {
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
            'primary' => true,
            'active' => false,
        ]);

    $barcode->refresh();

    expect($barcode->barcode)
        ->toBe('0123456789012')
        ->and($barcode->symbology)
        ->toBe(BarcodeSymbology::Ean13)
        ->and($barcode->primary)
        ->toBeTrue()
        ->and($barcode->active)
        ->toBeFalse();
});

test('duplicate barcode values are rejected within an organization', function () {
    $organization = Organization::factory()->create();

    $firstItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    $secondItem = InventoryItem::factory()
        ->for($organization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($firstItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '0123456789012',
        ]);

    expect(
        fn () => InventoryItemBarcode::factory()
            ->for($secondItem)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => '0123456789012',
            ]),
    )->toThrow(QueryException::class);
});

test('the same barcode value can exist in different organizations', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    $firstItem = InventoryItem::factory()
        ->for($firstOrganization)
        ->create();

    $secondItem = InventoryItem::factory()
        ->for($secondOrganization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($firstItem)
        ->create([
            'organization_id' => $firstOrganization->id,
            'barcode' => '0123456789012',
        ]);

    InventoryItemBarcode::factory()
        ->for($secondItem)
        ->create([
            'organization_id' => $secondOrganization->id,
            'barcode' => '0123456789012',
        ]);

    expect(
        InventoryItemBarcode::query()
            ->where('barcode', '0123456789012')
            ->count(),
    )->toBe(2);
});

test('barcode relationships resolve through organization item and alternate unit', function () {
    $organization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    $itemUnit = InventoryItemUnit::factory()
        ->for($item)
        ->create();

    $barcode = InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $itemUnit->id,
            'barcode' => '1111111111111',
        ]);

    expect($barcode->organization->is($organization))
        ->toBeTrue()
        ->and($barcode->inventoryItem->is($item))
        ->toBeTrue()
        ->and($barcode->inventoryItemUnit?->is($itemUnit))
        ->toBeTrue()
        ->and($item->barcodes->contains($barcode))
        ->toBeTrue()
        ->and($itemUnit->barcodes->contains($barcode))
        ->toBeTrue()
        ->and(
            $organization
                ->inventoryItemBarcodes
                ->contains($barcode),
        )
        ->toBeTrue();
});

test('a barcode cannot reference an inventory item from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create();

    expect(
        fn () => InventoryItemBarcode::factory()
            ->for($otherItem)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => '2222222222222',
            ]),
    )->toThrow(QueryException::class);
});

test('alternate unit association must belong to the exact barcode item', function () {
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

    expect(
        fn () => InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'inventory_item_unit_id' => $otherItemUnit->id,
                'barcode' => '3333333333333',
            ]),
    )->toThrow(QueryException::class);
});

test('an inventory item may have multiple non primary barcodes', function () {
    $organization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    InventoryItemBarcode::factory()
        ->count(3)
        ->for($item)
        ->sequence(
            ['barcode' => '4000000000001'],
            ['barcode' => '4000000000002'],
            ['barcode' => '4000000000003'],
        )
        ->create([
            'organization_id' => $organization->id,
            'primary' => false,
        ]);

    expect(
        $item->barcodes()
            ->where('primary', true)
            ->count(),
    )->toBe(0);
});

test('database prevents more than one primary barcode per item', function () {
    $organization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '5000000000001',
            'primary' => true,
        ]);

    expect(
        fn () => InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => '5000000000002',
                'primary' => true,
            ]),
    )->toThrow(QueryException::class);
});

test('all initial barcode symbologies persist through the typed enum', function () {
    $organization = Organization::factory()->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create();

    foreach (
        BarcodeSymbology::cases() as $index => $symbology
    ) {
        $barcode = InventoryItemBarcode::factory()
            ->for($item)
            ->create([
                'organization_id' => $organization->id,
                'barcode' => sprintf(
                    '60000000000%02d',
                    $index,
                ),
                'symbology' => $symbology,
            ]);

        expect($barcode->symbology)->toBe($symbology);
    }
});
