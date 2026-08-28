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
            'Add the essential inventory fields without leaving this',
        )
        ->toContain('page. Use Create with full details when you also need')
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

test('full inventory create form uses accessible sections and dirty navigation protection', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/create.tsx'),
    );
    $guardSource = File::get(
        resource_path('js/hooks/use-dirty-form-navigation.ts'),
    );

    expect($source)
        ->toContain('PageHeader')
        ->toContain('Identity')
        ->toContain('Classification')
        ->toContain('Product details')
        ->toContain('Stock configuration')
        ->toContain('label="Product family (optional)"')
        ->toContain('Record the usable percentage of this item.')
        ->toContain('This is the authoritative unit for stock.')
        ->toContain('useDirtyFormNavigation')
        ->toContain('dirty={isDirty}')
        ->toContain('NativeSelect')
        ->toContain('Creating…')
        ->toContain("title: 'Inventory items'")
        ->toContain("title: 'Create inventory item'")
        ->toContain('border-border')
        ->not->toContain('border-sidebar-border');

    expect($guardSource)
        ->toContain("router.on('before'")
        ->toContain("event.detail.visit.method !== 'get'")
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('confirmNavigation');
});

test('inventory detail and edit workspaces preserve server-authoritative editability contracts', function () {
    $showSource = File::get(
        resource_path('js/pages/inventory/items/show.tsx'),
    );
    $editSource = File::get(
        resource_path('js/pages/inventory/items/edit.tsx'),
    );

    expect($showSource)
        ->toContain('InventoryItemController.edit(')
        ->toContain('{canManage ?')
        ->toContain('Units and conversions')
        ->toContain('Barcodes')
        ->toContain('border-border');

    expect($editSource)
        ->toContain('Item details')
        ->toContain('Units and conversions')
        ->toContain('Barcodes')
        ->toContain('useDirtyFormNavigation')
        ->toContain('dirty={isDirty}')
        ->toContain('baseUnitOfMeasure')
        ->toContain('productFamily')
        ->toContain('name="base_unit_of_measure_id"')
        ->toContain('name="inventory_product_id"')
        ->toContain('Saving…')
        ->toContain('border-border')
        ->not->toContain('border-sidebar-border');
});
