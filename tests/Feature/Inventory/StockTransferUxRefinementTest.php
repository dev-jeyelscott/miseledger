<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/** Configure one tenant manager for Stock Transfer UX regression tests. */
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
    'stock transfer form exposes the active organization timezone without changing transfer authorization',
    function () {
        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-transfers.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-transfers/form')
                    ->where('timezone', 'Pacific/Auckland')
                    ->where('currency', 'NZD')
                    ->where('canCreate', true)
                    ->where('canShip', true)
                    ->where('canReceive', true)
                    ->has('locationOptions')
                    ->has('storageLocationOptions')
                    ->has('inventoryItemOptions')
                    ->has('unitOptions'),
            );
    },
);

test(
    'stock transfer workspace follows canonical lifecycle accessibility and safety contracts',
    function () {
        $source = File::get(
            resource_path('js/pages/stock-transfers/form.tsx'),
        );
        $normalizedSource = preg_replace('/\s+/', ' ', $source);

        expect($normalizedSource)
            ->not->toBeNull()
            ->and($normalizedSource)
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
            ->toContain('Transfer from source to destination')
            ->toContain('Requested to Shipped to Received')
            ->toContain('timeZone: timezone')
            ->toContain('already selected')
            ->toContain('disabled={')
            ->toContain('Save or discard unsaved draft changes')
            ->toContain('Inventory decrease')
            ->toContain('Confirm shipment')
            ->toContain('Confirm transfer receipt?')
            ->toContain('Confirm receipt')
            ->toContain('Discard unsaved changes?')
            ->toContain("router.on('before'")
            ->toContain(
                "window.addEventListener('beforeunload'",
            )
            ->toContain('md:hidden')
            ->toContain(
                'hidden overflow-x-auto rounded-xl',
            )
            ->toContain('canViewCosts')
            ->not->toContain(
                'new Date(value).toLocaleString()',
            )
            ->not->toContain(
                'border-sidebar-border',
            );
    },
);

test(
    'stock transfer draft validation remains server authoritative for duplicate items and endpoint collision',
    function () {
        $source = File::get(
            app_path(
                'Http/Requests/Inventory/SaveStockTransferRequest.php',
            ),
        );

        expect($source)
            ->toContain(
                "'different:from_storage_location_id'",
            )
            ->toContain("'distinct'")
            ->toContain(
                "'lines.*.inventory_item_id'",
            );
    },
);
