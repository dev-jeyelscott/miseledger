<?php

use Illuminate\Support\Facades\File;

test('inventory brand index uses the approved compact modal-first management layout', function () {
    $source = File::get(
        resource_path('js/pages/inventory/brands/index.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('useMemo')
        ->toContain('Search brands...')
        ->toContain('All statuses')
        ->toContain('filteredBrands')
        ->toContain('aria-live="polite"')
        ->toContain('Brand name')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<StatusBadge')
        ->toContain("label={active ? 'Active' : 'Inactive'}")
        ->toContain('border-border')
        ->toContain('divide-y divide-border md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('CreateInventoryBrandDialog')
        ->toContain('EditInventoryBrandDialog')
        ->toContain('DialogTrigger')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain('PreviousPageButton')
        ->toContain('Create brand')
        ->toContain('name="active"')
        ->toContain('Creating…')
        ->toContain('Saving…')
        ->toContain('Inactive brands')
        ->not->toContain('DropdownMenu')
        ->not->toContain("import { Badge } from '@/components/ui/badge';");
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

test('inventory brand edit page uses shared header and form contracts', function () {
    $source = File::get(
        resource_path('js/pages/inventory/brands/edit.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('<PageHeader')
        ->toContain('border-border')
        ->toContain('Saving…');
});
