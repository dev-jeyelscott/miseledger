<?php

use Illuminate\Support\Facades\File;

test('admin theme keeps generic content tokens separate from sidebar tokens', function () {
    $theme = File::get(resource_path('css/app.css'));
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
        ->toContain('--background: oklch(1 0 0);')
        ->toContain('--background: oklch(0.145 0 0);')
        ->toContain('--sidebar: oklch(0.985 0 0);')
        ->toContain('--sidebar: oklch(0.205 0 0);');

    expect($card)
        ->toContain('bg-card text-card-foreground')
        ->toContain('rounded-xl border')
        ->not->toContain('sidebar-border');

    expect($metricCard)
        ->toContain('border-border')
        ->toContain('bg-card')
        ->not->toContain('border-sidebar-border');

    expect($sidebar)
        ->toContain('bg-sidebar')
        ->toContain('border-sidebar-border')
        ->toContain('ring-sidebar-ring');
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
        ->not->toContain('border-sidebar-border')
        ->not->toContain('text-amber-600')
        ->not->toContain('dark:text-amber-400');
});
