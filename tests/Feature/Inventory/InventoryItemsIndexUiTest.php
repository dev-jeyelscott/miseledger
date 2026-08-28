<?php

use Illuminate\Support\Facades\File;

test('inventory items index preserves filter names and barcode search guidance', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('name="search"')
        ->toContain('name="category"')
        ->toContain('name="brand"')
        ->toContain('name="type"')
        ->toContain('name="status"')
        ->toContain('brand: filters.brandId ?? undefined')
        ->toContain('filters.brandId !== null')
        ->toContain('barcode, model number, or')
        ->toContain('manufacturer part number')
        ->toContain('Search or scan items…')
        ->toContain('name="sort"')
        ->toContain('name="direction"')
        ->toContain('value={filters.sort}')
        ->toContain('value={filters.direction}');
});

test('inventory items index exposes explicit creation paths and organized related actions', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('Quick add')
        ->toContain('Create with full details')
        ->toContain('InventoryItemController.create()')
        ->toContain('aria-label="Related inventory actions"')
        ->toContain('<DropdownMenu>')
        ->toContain('Inventory master data')
        ->toContain('Inventory stock actions')
        ->toContain('Units of measure')
        ->toContain('Categories')
        ->toContain('Brands')
        ->toContain('Product families')
        ->toContain('Opening balance')
        ->toContain('Adjust inventory');
});

test('inventory items index identifies inventory items as the current breadcrumb page', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain("title: 'Dashboard'")
        ->toContain("title: 'Inventory items'")
        ->toContain('href: InventoryItemController.index()');
});

test('quick add uses canonical native selects and field error relationships', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );
    $inputErrorSource = File::get(
        resource_path('js/components/input-error.tsx'),
    );

    expect($source)
        ->toContain('import { NativeSelect }')
        ->toContain('import { FilterToolbar }')
        ->toContain('import { PaginationControls }')
        ->toContain('import { StatusBadge }')
        ->toContain('aria-invalid=')
        ->toContain('aria-describedby=')
        ->toContain('modal-item-name-error')
        ->toContain('modal-item-sku-error')
        ->toContain('modal-item-type-error')
        ->toContain('modal-item-category-error')
        ->toContain('modal-item-yield-error')
        ->toContain('modal-item-base-unit-error')
        ->toContain("'Creating…'")
        ->toContain("'Applying…'");

    expect($inputErrorSource)
        ->toContain('text-destructive')
        ->not->toContain('text-red-');
});

test('inventory item index links all viewers to the read-only item detail while retaining management-only edit actions', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('InventoryItemController.show(')
        ->toContain('View details')
        ->toContain('{canManage && (')
        ->toContain('InventoryItemController.edit(')
        ->toContain('Edit')
        ->not->toContain('Read-only inventory item details.');
});

test('inventory items index keeps semantic sorting and responsive record composition', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('<article')
        ->toContain('Conversions')
        ->toContain('inventory-mobile-sort')
        ->toContain('Sort inventory items')
        ->toContain('<table')
        ->toContain('scope="col"')
        ->toContain('aria-sort=')
        ->toContain('preserveScroll')
        ->toContain('preserveState');
});

test('inventory items index exposes distinct empty and no-match states', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('No inventory items match these filters.')
        ->toContain('Adjust or reset the filters to see more items.')
        ->toContain('No inventory items have been created.')
        ->toContain('Create an inventory item to begin managing stock master data.');
});

test('inventory content surfaces use generic border tokens instead of sidebar borders', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('border-border')
        ->not->toContain('border-sidebar-border');
});
