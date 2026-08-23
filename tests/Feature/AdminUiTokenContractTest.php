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
