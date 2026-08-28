<?php

use Illuminate\Support\Facades\File;

test('inventory items index exposes a brand filter control bound to persisted state', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('htmlFor="inventory-brand"')
        ->toContain('id="inventory-brand"')
        ->toContain('name="brand"')
        ->toContain('filters.brandId?.toString() ?? \'\'')
        ->toContain('brandOptions.map((brand) =>')
        ->toContain('All brands');
});

test('inventory items index preserves the brand filter in sorting and reset query state', function () {
    $source = File::get(
        resource_path('js/pages/inventory/items/index.tsx'),
    );

    expect($source)
        ->toContain('brand: filters.brandId ?? undefined')
        ->toContain('filters.brandId !== null')
        ->toContain('barcode, model number, or manufacturer');
});
