<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/** Configure one tenant manager for Waste UX regression tests. */
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
    'waste workspace exposes the active organization timezone without changing permission scoped reporting',
    function () {
        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('currency', 'NZD')
                    ->where('timezone', 'Pacific/Auckland')
                    ->where('canRecord', true)
                    ->where('canManageReasons', true)
                    ->where('canViewReport', true)
                    ->where('canViewCosts', true)
                    ->has('wasteReasons')
                    ->has('recordForm')
                    ->has('reportOptions'),
            );
    },
);

test(
    'waste frontend follows the canonical operations reporting and evidence contract',
    function () {
        $source = File::get(
            resource_path('js/pages/waste/index.tsx'),
        );

        expect($source)
            ->toContain(
                "import { EmptyState } from '@/components/empty-state';",
            )
            ->toContain(
                "import { FilterToolbar } from '@/components/filter-toolbar';",
            )
            ->toContain(
                "import { PageHeader } from '@/components/page-header';",
            )
            ->toContain(
                "import { StatusBadge } from '@/components/status-badge';",
            )
            ->toContain(
                "import { Field } from '@/components/ui/field';",
            )
            ->toContain(
                "import { NativeSelect } from '@/components/ui/native-select';",
            )
            ->toContain('RecordWasteForm')
            ->toContain('WasteReasonsPanel')
            ->toContain('Report overview')
            ->toContain('Breakdown analysis')
            ->toContain('Immutable Waste evidence')
            ->toContain('Confirm record waste')
            ->toContain('Confirm and reduce stock')
            ->toContain('Inventory decrease')
            ->toContain("router.on('before'")
            ->toContain("window.addEventListener('beforeunload'")
            ->toContain('timeZone: timezone')
            ->toContain('Active filters')
            ->toContain('<details')
            ->toContain('md:hidden')
            ->toContain('hidden overflow-x-auto md:block')
            ->toContain(
                "organizationContext.entitlements?.grants['reports.export']",
            )
            ->toContain('WasteController.export().url')
            ->toContain('canViewCosts')
            ->toContain('border border-border bg-card')
            ->toContain('Deactivate waste reason?')
            ->toContain('Historical waste records')
            ->not->toContain('border-sidebar-border')
            ->not->toContain('<select')
            ->not->toContain('new Date(value).toLocaleString()')
            ->not->toContain('bg-emerald-50')
            ->not->toContain('bg-blue-50')
            ->not->toContain('router.visit(')
            ->not->toContain('router.push(');
    },
);
