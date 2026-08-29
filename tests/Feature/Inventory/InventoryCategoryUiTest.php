<?php

use Illuminate\Support\Facades\File;

test('inventory category index uses the approved server-backed responsive discovery layout', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('InventoryCategoryController.index().url')
        ->toContain('method="get"')
        ->toContain('name="search"')
        ->toContain('name="status"')
        ->toContain('Search categories...')
        ->toContain('All statuses')
        ->toContain('Category name')
        ->toContain('Status')
        ->toContain('Used by')
        ->toContain('Actions')
        ->toContain('usageCount')
        ->toContain('<StatusBadge')
        ->toContain("label={active ? 'Active' : 'Inactive'}")
        ->toContain('border-border')
        ->toContain('divide-y divide-border md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('categories.map')
        ->toContain('CreateInventoryCategoryDialog')
        ->toContain('EditInventoryCategoryDialog')
        ->toContain('DialogTrigger')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain('PreviousPageButton')
        ->toContain('Create category')
        ->toContain('name="active"')
        ->toContain('Applying…')
        ->toContain('Creating…')
        ->toContain('Saving…')
        ->toContain('Inactive categories')
        ->toContain('hasQueryState')
        ->toContain('Reset')
        ->not->toContain('useState')
        ->not->toContain('useMemo')
        ->not->toContain('filteredCategories')
        ->not->toContain('create-category-heading')
        ->not->toContain('Create a category using the form on this page.')
        ->not->toContain('Drag to reorder')
        ->not->toContain('DropdownMenu')
        ->not->toContain("import { Badge } from '@/components/ui/badge';");
});

test('inventory category redesign keeps management controls permission gated', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain('canManage')
        ->toContain('{canManage && (')
        ->toContain('InventoryCategoryController.store.form()')
        ->toContain('InventoryCategoryController.update.form(');
});

test('inventory category index breadcrumbs are dashboard inventory and categories', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain('InventoryCategoriesIndex.layout')
        ->toContain("title: 'Dashboard'")
        ->toContain("title: 'Inventory'")
        ->toContain("title: 'Categories'");
});

test('standalone inventory category editor has been removed', function () {
    expect(
        File::exists(
            resource_path('js/pages/inventory/categories/edit.tsx'),
        ),
    )->toBeFalse();

    $routes = File::get(base_path('routes/web.php'));

    expect($routes)
        ->not->toContain("'categories/{inventoryCategory}/edit'")
        ->not->toContain("->name('categories.edit')");
});
