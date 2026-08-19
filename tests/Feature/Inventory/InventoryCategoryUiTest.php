<?php

use Illuminate\Support\Facades\File;

test('inventory category index uses the approved compact management layout', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain('useMemo')
        ->toContain('Search categories...')
        ->toContain('All statuses')
        ->toContain('filteredCategories')
        ->toContain('aria-live="polite"')
        ->toContain('Category name')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<Badge')
        ->toContain('EditInventoryCategoryDialog')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain('PreviousPageButton')
        ->toContain('Create category')
        ->toContain('name="active"')
        ->toContain('Inactive categories')
        ->not->toContain('Drag to reorder')
        ->not->toContain('DropdownMenu');
});

test('inventory category redesign keeps management controls permission gated', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain('canManage')
        ->toContain('{canManage && (')
        ->toContain('InventoryCategoryController.store.form()')
        ->toContain('InventoryCategoryController.update.form(category.id)');
});
