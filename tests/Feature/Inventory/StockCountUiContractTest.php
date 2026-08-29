<?php

use Illuminate\Support\Facades\File;

test('stock count index follows the canonical admin ui contract', function () {
    $source = File::get(resource_path('js/pages/stock-counts/index.tsx'));

    expect($source)
        ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { PaginationControls } from '@/components/pagination-controls';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('Active filters')
        ->toContain('aria-live="polite"')
        ->toContain('aria-busy={isNavigating}')
        ->toContain('aria-sort={ariaSortFor(')
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('border border-border bg-card')
        ->toContain('border-info-border bg-info-subtle')
        ->toContain('motion-reduce:transition-none')
        ->toContain('Finalized counts are locked for audit integrity')
        ->not->toContain('border-sidebar-border')
        ->not->toContain('const selectClassName')
        ->not->toContain('function StatusBadge(')
        ->not->toContain('bg-emerald-50')
        ->not->toContain('bg-blue-50/60')
        ->not->toContain('text-blue-600');
});

test('stock count index preserves permission aware primary actions', function () {
    $source = File::get(resource_path('js/pages/stock-counts/index.tsx'));

    expect($source)
        ->toContain('{canViewReport && (')
        ->toContain('{canCreate && (')
        ->toContain("canCreate && row.status === 'draft'")
        ->toContain('StockCountController.variance()')
        ->toContain('StockCountController.create()')
        ->toContain('StockCountController.edit(');
});

test('stock count index keeps server authoritative query semantics', function () {
    $source = File::get(resource_path('js/pages/stock-counts/index.tsx'));

    expect($source)
        ->toContain('name="search"')
        ->toContain('name="view"')
        ->toContain('name="location_id"')
        ->toContain('name="storage_location_id"')
        ->toContain('name="from"')
        ->toContain('name="to"')
        ->toContain('name="sort"')
        ->toContain('name="direction"')
        ->toContain('name="per_page"')
        ->toContain("params.set('sort', filters.sort)")
        ->toContain("params.set('direction', filters.direction)")
        ->toContain("params.set('per_page', filters.perPage.toString())")
        ->toContain('preserveScroll')
        ->toContain('preserveState');
});
