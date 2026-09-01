<?php

use Illuminate\Support\Facades\File;

test('admin theme keeps generic content tokens separate from sidebar tokens', function () {
    $theme = File::get(resource_path('css/app.css'));
    $designSystem = File::get(base_path('design-system.html'));
    $card = File::get(resource_path('js/components/ui/card.tsx'));
    $metricCard = File::get(
        resource_path('js/components/dashboard/dashboard-metric-card.tsx'),
    );
    $sidebar = File::get(resource_path('js/components/ui/sidebar.tsx'));

    expect($theme)
        ->toContain('@theme inline {')
        ->toContain('--color-background: var(--background);')
        ->toContain('--color-card: var(--card);')
        ->toContain('--color-border: var(--border);')
        ->toContain('--color-sidebar: var(--sidebar);')
        ->toContain('--color-sidebar-border: var(--sidebar-border);')
        ->toContain('--color-sidebar-ring: var(--sidebar-ring);')
        ->toContain('--radius-sm: calc(var(--radius) * 0.6);')
        ->toContain('--radius-md: calc(var(--radius) * 0.8);')
        ->toContain('--radius-lg: var(--radius);')
        ->toContain('--radius-xl: calc(var(--radius) * 1.4);')
        ->toContain('--radius-2xl: calc(var(--radius) * 1.8);')
        ->toContain('--radius-3xl: calc(var(--radius) * 2.2);')
        ->toContain('--radius-4xl: calc(var(--radius) * 2.6);')
        ->toContain('--background: oklch(0.975 0.004 95);')
        ->toContain('--card: oklch(1 0 0);')
        ->toContain('--popover: oklch(1 0 0);')
        ->toContain('--border: oklch(0.91 0.004 95);')
        ->toContain('--input: oklch(0.84 0.006 95);')
        ->toContain('--background: oklch(0.12 0.004 264);')
        ->toContain('--card: oklch(0.18 0.006 264);')
        ->toContain('--popover: oklch(0.18 0.006 264);')
        ->toContain('--border: oklch(0.29 0.006 264);')
        ->toContain('--input: oklch(0.38 0.008 264);')
        ->toContain('--sidebar: oklch(0.985 0 0);')
        ->toContain('--sidebar: oklch(0.205 0 0);');

    expect($card)
        ->toContain('bg-card text-card-foreground')
        ->toContain('rounded-xl py-6 shadow-sm')
        ->not->toContain('rounded-xl border')
        ->not->toContain('sidebar-border');

    expect($metricCard)
        ->toContain('bg-card')
        ->toContain('href?: InertiaLinkHref')
        ->toContain('focus-visible:ring-[3px]')
        ->not->toContain('border-sidebar-border');

    expect($sidebar)
        ->toContain('bg-sidebar')
        ->toContain('border-sidebar-border')
        ->toContain('ring-sidebar-ring');

    expect($designSystem)
        ->toContain('<link rel="stylesheet" href="./resources/css/app.css" />')
        ->toContain("const tokenNames = ['background', 'card', 'popover', 'border', 'input', 'sidebar'];")
        ->toContain('getComputedStyle(element)')
        ->toContain('const renderedTheme = container.lastElementChild;')
        ->not->toContain('fetch(')
        ->toContain('Canvas')
        ->toContain('Elevated surface')
        ->toContain('Structural border')
        ->toContain('Input boundary')
        ->toContain('Sidebar navigation surface')
        ->toContain("['Light mode', 'Dark mode'].forEach((label) => {")
        ->toContain("theme.classList.add('dark');");
});

test('admin theme exposes the canonical operational status semantics', function () {
    $theme = File::get(resource_path('css/app.css'));

    expect($theme)
        ->toContain('--color-success-subtle: var(--success-subtle);')
        ->toContain('--color-success-foreground: var(--success-foreground);')
        ->toContain('--color-success-border: var(--success-border);')
        ->toContain('--color-warning-subtle: var(--warning-subtle);')
        ->toContain('--color-warning-foreground: var(--warning-foreground);')
        ->toContain('--color-warning-border: var(--warning-border);')
        ->toContain('--color-info-subtle: var(--info-subtle);')
        ->toContain('--color-info-foreground: var(--info-foreground);')
        ->toContain('--color-info-border: var(--info-border);')
        ->toContain('--success-subtle: oklch(0.979 0.021 166.113);')
        ->toContain('--warning-subtle: oklch(0.987 0.022 95.277);')
        ->toContain('--info-subtle: oklch(0.97 0.014 254.604);')
        ->toContain('--success-subtle: oklch(0.262 0.051 172.552 / 0.4);')
        ->toContain('--warning-subtle: oklch(0.279 0.077 45.635 / 0.4);')
        ->toContain('--info-subtle: oklch(0.282 0.091 267.935 / 0.4);');
});

test('native select preserves the canonical native form control contract', function () {
    $nativeSelect = File::get(
        resource_path('js/components/ui/native-select.tsx'),
    );

    expect($nativeSelect)
        ->toContain("React.ComponentProps<'select'>")
        ->toContain('data-slot="native-select"')
        ->toContain('border-input')
        ->toContain('bg-background')
        ->toContain('h-9')
        ->toContain('focus-visible:ring-[3px]')
        ->toContain('focus-visible:ring-ring/50')
        ->toContain('aria-invalid:border-destructive')
        ->toContain('disabled:opacity-50');
});

test('shared inventory UI primitives follow the canonical admin contract', function () {
    $field = File::get(resource_path('js/components/ui/field.tsx'));
    $statusBadge = File::get(resource_path('js/components/status-badge.tsx'));
    $pageHeader = File::get(resource_path('js/components/page-header.tsx'));
    $filterToolbar = File::get(resource_path('js/components/filter-toolbar.tsx'));
    $paginationControls = File::get(
        resource_path('js/components/pagination-controls.tsx'),
    );

    expect($field)
        ->toContain('data-slot="field"')
        ->toContain('htmlFor={controlId}')
        ->toContain("'aria-invalid': error ? true")
        ->toContain("'aria-describedby': describedBy")
        ->toContain('id={resolvedHelperId}')
        ->toContain('id={resolvedErrorId}')
        ->toContain('message={error}');

    expect($statusBadge)
        ->toContain("'neutral' | 'success' | 'warning' | 'info' | 'danger'")
        ->toContain('label: string;')
        ->toContain('border-success-border bg-success-subtle text-success-foreground')
        ->toContain('border-warning-border bg-warning-subtle text-warning-foreground')
        ->toContain('border-info-border bg-info-subtle text-info-foreground')
        ->toContain('border-destructive/30 bg-destructive/10 text-destructive')
        ->not->toContain('sidebar-border');

    expect($pageHeader)
        ->toContain("Omit<ComponentProps<'header'>, 'title'>")
        ->toContain('title: ReactNode;')
        ->toContain('data-slot="page-header"')
        ->toContain('text-2xl font-semibold tracking-tight')
        ->toContain('mt-1 text-sm text-muted-foreground')
        ->toContain('flex flex-wrap gap-2')
        ->not->toContain('sidebar-border');

    expect($filterToolbar)
        ->toContain('data-slot="filter-toolbar"')
        ->toContain('rounded-xl bg-card p-4 text-card-foreground shadow-sm')
        ->not->toContain('rounded-xl border border-border bg-card')
        ->not->toContain('sidebar-border');

    expect($paginationControls)
        ->toContain('data-slot="pagination-controls"')
        ->toContain('border-t border-border')
        ->toContain("InertiaLinkProps['href'] | null")
        ->toContain('preserveScroll={preserveScroll}')
        ->toContain('preserveState={preserveState}')
        ->toContain('Page {currentPage} of {lastPage}')
        ->not->toContain('sidebar-border');
});

test('dashboard follows the canonical operational UI contract', function () {
    $dashboard = File::get(resource_path('js/pages/dashboard.tsx'));

    expect($dashboard)
        ->toContain('Receive stock')
        ->toContain('Create purchase order')
        ->toContain('Low-stock alerts')
        ->toContain('Pending work')
        ->toContain('Finalize receipt')
        ->toContain('Record waste')
        ->toContain('Recent inventory activity')
        ->toContain('Organization summary')
        ->toContain('scope="col"')
        ->toContain('border-warning-border')
        ->toContain('bg-warning-subtle')
        ->toContain('text-warning-foreground')
        ->toContain('bg-destructive/10')
        ->toContain('text-destructive')
        ->toContain('focus-visible:ring-[3px]')
        ->toContain("'Creating…'")
        ->toContain("'Saving…'")
        ->toContain("'Refreshing…'")
        ->toContain('aria-live="polite"')
        ->toContain('motion-reduce:animate-none')
        ->toContain('Out of stock')
        ->toContain('Negative stock')
        ->toContain('Full access')
        ->toContain('Read-only')
        ->toContain('inventory_item_id')
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain("only: ['dashboard']")
        ->toContain('NativeSelect')
        ->not->toContain('tenant boundary for your restaurant inventory')
        ->not->toContain("'Writable'")
        ->not->toContain("? 'Critical'")
        ->not->toContain('border-sidebar-border')
        ->not->toContain('text-amber-600')
        ->not->toContain('dark:text-amber-400');
});
