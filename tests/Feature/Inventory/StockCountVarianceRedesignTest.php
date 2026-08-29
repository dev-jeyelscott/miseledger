<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/** Configure one tenant manager for variance-analysis regression tests. */
beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Pacific/Auckland',
        'currency' => 'NZD',
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test(
    'stock count variance exposes the active organization timezone with existing cost authorization',
    function () {
        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-counts.variance'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-counts/variance')
                    ->has('rows', 0)
                    ->where('currency', 'NZD')
                    ->where('timezone', 'Pacific/Auckland')
                    ->where('canViewCosts', true),
            );
    },
);

test(
    'stock count variance frontend follows the canonical responsive report contract',
    function () {
        $source = File::get(
            resource_path('js/pages/stock-counts/variance.tsx'),
        );
        $emptyState = File::get(
            resource_path('js/components/empty-state.tsx'),
        );

        expect($source)
            ->toContain("import { EmptyState } from '@/components/empty-state';")
            ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
            ->toContain("import { PageHeader } from '@/components/page-header';")
            ->toContain("import { StatusBadge } from '@/components/status-badge';")
            ->toContain("import { Field } from '@/components/ui/field';")
            ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
            ->toContain('DashboardMetricCard')
            ->toContain("organizationContext.entitlements?.grants['reports.export']")
            ->toContain('StockCountController.exportVariance().url')
            ->toContain('name="location_id"')
            ->toContain('name="from"')
            ->toContain('name="to"')
            ->toContain('timeZone: timezone')
            ->toContain('Negative variance')
            ->toContain('Positive variance')
            ->toContain('Zero variance')
            ->toContain('md:hidden')
            ->toContain('hidden overflow-x-auto md:block')
            ->toContain('border border-border bg-card')
            ->toContain('StockCountController.edit(')
            ->toContain('canViewCosts')
            ->not->toContain('border-sidebar-border')
            ->not->toContain('<select')
            ->not->toContain('new Date(value).toLocaleString()')
            ->not->toContain('function StatusBadge(');

        expect($emptyState)
            ->toContain('data-slot="empty-state"')
            ->toContain('text-muted-foreground');
    },
);
