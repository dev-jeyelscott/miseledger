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

test('stock count form follows the canonical lifecycle workspace contract', function () {
    $source = File::get(resource_path('js/pages/stock-counts/form.tsx'));

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('Finalized audit evidence')
        ->toContain('Submitted evidence')
        ->toContain('Cancelled evidence')
        ->toContain('Physical count evidence')
        ->toContain('formatOrganizationDate(')
        ->toContain('timeZone: timezone')
        ->toMatch('/Review the highlighted\\s+fields/')
        ->toContain('focusErrorTarget(')
        ->toContain("'(prefers-reduced-motion: reduce)'")
        ->toContain("behavior: prefersReducedMotion ? 'auto' : 'smooth'")
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('rounded-xl border border-border bg-card')
        ->not->toContain('border-sidebar-border')
        ->not->toContain('<Label>')
        ->not->toContain('new Date(value).toLocaleString()');
});

test('stock count form keeps field and server error recovery accessible', function () {
    $source = File::get(resource_path('js/pages/stock-counts/form.tsx'));

    expect($source)
        ->toContain('function firstActionError(')
        ->toContain('role="alert"')
        ->toContain('stock-count-line-${index}-item')
        ->toContain('stock-count-line-${index}-quantity')
        ->toContain('stock-count-line-${index}-unit')
        ->toContain('stock-count-line-${index}-notes')
        ->toContain('element.focus(')
        ->toContain('preventScroll: true');
});

test('stock count form guards every lifecycle transition', function () {
    $source = File::get(resource_path('js/pages/stock-counts/form.tsx'));

    expect($source)
        ->toContain('Submit stock count?')
        ->toContain('It does not adjust')
        ->toContain('inventory.')
        ->toContain('Finalize count and commit')
        ->toContain('inventory adjustments?')
        ->toContain('Finalize and commit adjustments')
        ->toContain('Cancel stock count?')
        ->toContain('StockCountController.submit.form(')
        ->toContain('StockCountController.finalize.form(')
        ->toContain('StockCountController.cancel.form(')
        ->toContain("stockCount?.status === 'draft' && canCreate")
        ->toContain("stockCount?.status === 'submitted' && canFinalize")
        ->toContain('server validation')
        ->toContain('stock-ledger workflow')
        ->toContain("'Submitting…'")
        ->toContain("'Finalizing…'")
        ->toContain("'Cancelling…'");
});

test('stock count form receives the organization timezone from its server options', function () {
    $controller = File::get(
        app_path('Http/Controllers/Inventory/StockCountController.php'),
    );

    expect($controller)
        ->toContain("'timezone' => \$organization->timezone,");
});
