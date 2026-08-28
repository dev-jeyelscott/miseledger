<?php

use Illuminate\Support\Facades\File;

test('inventory brand index uses the approved compact modal-first management layout', function () {
    $source = File::get(
        resource_path('js/pages/inventory/brands/index.tsx'),
    );

    expect($source)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain('useMemo')
        ->toContain('Search brands...')
        ->toContain('All statuses')
        ->toContain('filteredBrands')
        ->toContain('aria-live="polite"')
        ->toContain('Brand name')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<Badge')
        ->toContain('CreateInventoryBrandDialog')
        ->toContain('EditInventoryBrandDialog')
        ->toContain('DialogTrigger')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain('PreviousPageButton')
        ->toContain('Create brand')
        ->toContain('name="active"')
        ->toContain('Inactive brands')
        ->not->toContain('DropdownMenu');
});

test('inventory brand index keeps management controls permission gated', function () {
    $source = File::get(
        resource_path('js/pages/inventory/brands/index.tsx'),
    );

    expect($source)
        ->toContain('canManage')
        ->toContain('{canManage && (')
        ->toContain('InventoryBrandController.store.form()')
        ->toContain('InventoryBrandController.update.form(');
});
