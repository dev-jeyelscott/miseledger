<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create an authenticated user with access to one organization.
 *
 * @return array{User, Organization}
 */
function unitOfMeasureIndexContext(
    OrganizationRole $role = OrganizationRole::Owner,
): array {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => $role,
        ]);

    return [$user, $organization];
}

test('unit index exposes tenant scoped filtered rows and usage summary', function () {
    [$user, $organization] = unitOfMeasureIndexContext();

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

    UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
            'active' => false,
        ]);

    $bottle = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Bottle',
            'symbol' => 'bottle',
            'dimension' => 'count',
            'active' => true,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
        ]);

    $alternateItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $bottle->id,
        ]);

    InventoryItemUnit::factory()
        ->for($alternateItem)
        ->create([
            'unit_of_measure_id' => $gram->id,
            'quantity_in_base_unit' => '1.000000',
            'active' => true,
        ]);

    $otherOrganization = Organization::factory()->create();

    UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other tenant Gram',
            'symbol' => 'other-g',
            'dimension' => 'weight',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.units.index', [
            'search' => 'gram',
            'dimension' => 'weight',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/units/index')
                ->where('canManage', true)
                ->where('summary.total', 3)
                ->where('summary.active', 2)
                ->where('summary.dimensions', 2)
                ->where('filters.search', 'gram')
                ->where('filters.dimension', 'weight')
                ->where('filters.status', 'active')
                ->has('units', 1)
                ->where('units.0.id', $gram->id)
                ->where('units.0.name', 'Gram')
                ->where('units.0.symbol', 'g')
                ->where('units.0.dimension', 'weight')
                ->where('units.0.active', true)
                ->where('units.0.usageCount', 2)
                ->where(
                    'units.0.updatedOn',
                    $gram->updated_at
                        ?->timezone($organization->timezone)
                        ->format('M j, Y'),
                ),
        );
});

test('an inventory reader receives a read only unit index', function () {
    [$user, $organization] = unitOfMeasureIndexContext(
        OrganizationRole::Auditor,
    );

    UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Piece',
            'symbol' => 'piece',
            'dimension' => 'count',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.units.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/units/index')
                ->where('canManage', false)
                ->has('units', 1),
        );
});

test('modal unit creation returns to the current filtered index context', function () {
    [$user, $organization] = unitOfMeasureIndexContext();

    $indexUrl = route('inventory.units.index', [
        'search' => 'each',
        'dimension' => 'count',
        'status' => 'active',
    ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($indexUrl)
        ->post(route('inventory.units.store'), [
            '_modal' => '1',
            'name' => 'Each',
            'symbol' => 'ea',
            'dimension' => 'count',
            'active' => true,
        ])
        ->assertRedirect($indexUrl);

    $this->assertDatabaseHas('units_of_measure', [
        'organization_id' => $organization->id,
        'name' => 'Each',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);
});

test('unit index frontend uses guarded dialogs filters and dense table', function () {
    $source = file_get_contents(
        resource_path('js/pages/inventory/units/index.tsx'),
    );
    $normalizedSource = preg_replace('/\s+/', ' ', $source);

    expect($normalizedSource)
        ->toContain('{processing ? \'Creating…\' : \'Create unit\'}')
        ->toContain('{processing ? \'Saving…\' : \'Save unit\'}');

    expect($source)
        ->toContain("import { FilterToolbar } from '@/components/filter-toolbar';")
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { StatusBadge } from '@/components/status-badge';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('CreateUnitOfMeasureDialog')
        ->toContain('EditUnitOfMeasureDialog')
        ->toContain('useGuardedDialog')
        ->toContain('<DialogContent')
        ->toContain('name="_modal"')
        ->toContain('name="search"')
        ->toContain('name="dimension"')
        ->toContain('name="status"')
        ->toContain('border-border')
        ->toContain('divide-y divide-border md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain("label={active ? 'Active' : 'Inactive'}")
        ->toContain("variant={active ? 'success' : 'neutral'}")
        ->toContain('overflow-x-auto')
        ->toContain('Used by')
        ->toContain('Updated')
        ->toContain('PreviousPageButton')
        ->toContain("? 'Applying…'")
        ->toContain("title: 'Units of measure'")
        ->not->toContain('CreateUnitOfMeasureSheet')
        ->not->toContain('border-sidebar-border')
        ->not->toContain("import { Badge } from '@/components/ui/badge';");
});

test('unit edit page frontend uses shared page and field contracts', function () {
    $source = file_get_contents(
        resource_path('js/pages/inventory/units/edit.tsx'),
    );

    expect($source)
        ->toContain("import { PageHeader } from '@/components/page-header';")
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain("import { NativeSelect } from '@/components/ui/native-select';")
        ->toContain('<PageHeader')
        ->toContain('border-border')
        ->toContain("processing ? 'Saving…' : 'Save unit'")
        ->not->toContain('border-sidebar-border');
});
