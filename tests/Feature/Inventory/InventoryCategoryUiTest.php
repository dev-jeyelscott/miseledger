<?php

use Illuminate\Support\Facades\File;

test('inventory category index uses the approved compact modal-first management layout', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/index.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('useMemo')
        ->toContain('Search categories...')
        ->toContain('All statuses')
        ->toContain('filteredCategories')
        ->toContain('aria-live="polite"')
        ->toContain('Category name')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<StatusBadge')
        ->toContain('border-border')
        ->toContain('CreateInventoryCategoryDialog')
        ->toContain('EditInventoryCategoryDialog')
        ->toContain('DialogTrigger')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain('PreviousPageButton')
        ->toContain('Create category')
        ->toContain('name="active"')
        ->toContain('Creating…')
        ->toContain('Saving…')
        ->toContain('Inactive categories')
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

test('inventory category edit page uses shared header and form contracts', function () {
    $source = File::get(
        resource_path('js/pages/inventory/categories/edit.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('<PageHeader')
        ->toContain('border-border')
        ->toContain('Saving…');
});
