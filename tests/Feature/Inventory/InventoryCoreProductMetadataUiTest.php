<?php

use Illuminate\Support\Facades\File;

test('full inventory create and edit forms expose core product metadata controls', function () {
    $createSource = File::get(
        resource_path('js/pages/inventory/items/create.tsx'),
    );

    $editSource = File::get(
        resource_path('js/pages/inventory/items/edit.tsx'),
    );

    expect($createSource)
        ->toContain('InventoryItemController.store.form()')
        ->toContain('name="inventory_brand_id"')
        ->toContain('name="model_number"')
        ->toContain('name="manufacturer_part_number"')
        ->toContain('name="description"')
        ->toContain('errors.inventory_brand_id')
        ->toContain('errors.model_number')
        ->toContain('errors.manufacturer_part_number')
        ->toContain('errors.description');

    expect($editSource)
        ->toContain('InventoryItemController.update.form(item.id)')
        ->toContain('name="inventory_brand_id"')
        ->toContain('name="model_number"')
        ->toContain('name="manufacturer_part_number"')
        ->toContain('name="description"')
        ->toContain('item.modelNumber')
        ->toContain('item.manufacturerPartNumber')
        ->toContain('item.description')
        ->toContain('errors.inventory_brand_id')
        ->toContain('errors.model_number')
        ->toContain('errors.manufacturer_part_number')
        ->toContain('errors.description');
});

test('inventory quick create remains intentionally compact', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain(
            'Create a compact inventory master record without leaving index context.',
        )
        ->not->toContain('name="inventory_brand_id"')
        ->not->toContain('name="model_number"')
        ->not->toContain('name="manufacturer_part_number"')
        ->not->toContain('name="description"');
});

test('inventory TypeScript contracts expose core product metadata', function () {
    $source = File::get(
        resource_path('js/types/inventory.ts'),
    );

    expect($source)
        ->toContain('inventoryBrand: InventoryBrandData | null;')
        ->toContain('modelNumber: string | null;')
        ->toContain('manufacturerPartNumber: string | null;')
        ->toContain('description: string | null;');
});
