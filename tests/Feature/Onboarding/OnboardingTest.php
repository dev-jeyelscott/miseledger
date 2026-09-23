<?php

use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Onboarding\OrganizationSetupReadiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Organization, 1: User}
 */
function onboardingTenant(OrganizationRole $role = OrganizationRole::Owner): array
{
    $organization = Organization::factory()->awaitingSetup()->create();
    $user = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => $role]);

    return [$organization, $user];
}

/**
 * @return array{0: Location, 1: StorageLocation}
 */
function onboardingLocation(Organization $organization): array
{
    $location = Location::factory()->for($organization)->create([
        'name' => 'Main Kitchen',
        'code' => 'MAIN',
    ]);

    $storageLocation = new StorageLocation([
        'name' => StorageLocation::DEFAULT_NAME,
        'code' => StorageLocation::DEFAULT_CODE,
        'active' => true,
    ]);
    $storageLocation->organization()->associate($organization);
    $storageLocation->location()->associate($location);
    $storageLocation->save();

    return [$location, $storageLocation];
}

function onboardingUnit(Organization $organization, string $symbol = 'kg'): UnitOfMeasure
{
    return UnitOfMeasure::factory()->for($organization)->create([
        'name' => $symbol === 'kg' ? 'Kilogram' : 'Piece',
        'symbol' => $symbol,
        'dimension' => $symbol === 'kg' ? 'weight' : 'count',
        'active' => true,
    ]);
}

function onboardingItem(Organization $organization, UnitOfMeasure $unit, string $sku = 'RICE'): InventoryItem
{
    return InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
        'name' => Str::headline(strtolower($sku)),
        'sku' => $sku,
        'active' => true,
    ]);
}

function onboardingCsv(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('inventory.csv', $contents);
}

test('registration continues into onboarding and verification preserves that destination', function () {
    $this->post(route('register.store'), [
        'name' => 'New Owner',
        'email' => 'owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('onboarding.show', absolute: false));

    $this->get(route('onboarding.show'))
        ->assertRedirect(route('verification.notice'));

    expect(session('url.intended'))->toBe(route('onboarding.show'));
});

test('a user without an organization starts onboarding at organization creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('onboarding/show')
            ->where('organization', null)
            ->where('currentStep', 'organization')
            ->where('ready', false)
            ->has('operationId'));
});

test('organization creation needs only a business name and is safe to retry', function () {
    $user = User::factory()->create();
    $operationId = (string) Str::uuid();

    foreach ([1, 2] as $attempt) {
        $this->actingAs($user)
            ->post(route('organizations.store'), [
                'name' => 'Sinta Kitchen',
                'operation_id' => $operationId,
            ])
            ->assertRedirect(route('onboarding.show'));
    }

    $organization = Organization::query()->sole();

    expect($organization->name)->toBe('Sinta Kitchen')
        ->and($organization->slug)->toStartWith('sinta-kitchen-')
        ->and($organization->timezone)->toBe('Asia/Manila')
        ->and($organization->currency)->toBe('PHP')
        ->and($organization->onboarding_completed_at)->toBeNull()
        ->and($organization->unitsOfMeasure()->count())->toBe(0)
        ->and($organization->memberships()->sole()->role)->toBe(OrganizationRole::Owner);
});

test('an organization creation operation id cannot be replayed by another user', function () {
    $operationId = (string) Str::uuid();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($owner)->post(route('organizations.store'), [
        'name' => 'Owner Kitchen',
        'operation_id' => $operationId,
    ]);

    $this->actingAs($intruder)
        ->post(route('organizations.store'), [
            'name' => 'Intruder Kitchen',
            'operation_id' => $operationId,
        ])
        ->assertSessionHasErrors('name');

    expect(Organization::query()->count())->toBe(1)
        ->and(OrganizationMembership::query()->where('user_id', $intruder->id)->exists())->toBeFalse();
});

test('the first location needs only a name and creates a default storage area', function () {
    [$organization, $owner] = onboardingTenant();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.locations.store'), [
            'name' => 'Makati Branch',
            'organization_id' => Organization::factory()->create()->id,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => 'units']));

    $location = Location::query()->where('organization_id', $organization->id)->sole();

    expect($location->name)->toBe('Makati Branch')
        ->and($location->code)->toBe('MAKATI-BRANCH')
        ->and($location->storageLocations()->sole()->code)->toBe(StorageLocation::DEFAULT_CODE);
});

test('location setup requires the locations permission', function () {
    [$organization, $staff] = onboardingTenant(OrganizationRole::KitchenStaff);

    $this->actingAs($staff)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.locations.store'), ['name' => 'Branch'])
        ->assertForbidden();

    expect(Location::query()->count())->toBe(0);
});

test('unit selection creates only selected catalog units without duplicates on retry', function () {
    [$organization, $owner] = onboardingTenant();

    foreach ([1, 2] as $attempt) {
        $this->actingAs($owner)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('onboarding.units.store'), ['symbols' => ['kg', 'piece', 'kg']])
            ->assertRedirect(route('onboarding.show', ['step' => 'inventory']));
    }

    expect($organization->unitsOfMeasure()->orderBy('symbol')->pluck('symbol')->all())
        ->toBe(['kg', 'piece']);

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.units.store'), ['symbols' => ['furlong']])
        ->assertSessionHasErrors('symbols.0');
});

test('manual inventory entry completes the inventory step but leaves opening stock unresolved', function () {
    [$organization, $owner] = onboardingTenant();
    onboardingLocation($organization);
    $unit = onboardingUnit($organization);

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->from(route('onboarding.show', ['step' => 'inventory']))
        ->post(route('inventory.items.store'), [
            'name' => 'Jasmine Rice',
            'sku' => 'RICE',
            'base_unit_of_measure_id' => $unit->id,
            'type' => 'ingredient',
            'yield_percentage' => '100',
            'active' => true,
            '_modal' => true,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => 'inventory']));

    $status = OrganizationSetupReadiness::resolve($organization->refresh());

    expect($status->activeItemCount)->toBe(1)
        ->and($status->unresolvedOpeningStockCount)->toBe(1)
        ->and($status->ready)->toBeFalse();
});

test('item-only CSV preview saves nothing and import is retry-safe', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    onboardingUnit($organization);

    $csv = "sku,name,base_unit_symbol\nRICE,Jasmine Rice,kg\nSUGAR,White Sugar,kg\n";

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.inventory-import.preview'), [
            'file' => onboardingCsv($csv),
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => 'inventory']))
        ->assertSessionHas('inertia.flash_data.inventoryImport.mode', 'items')
        ->assertSessionHas('inertia.flash_data.inventoryImport.created', 2)
        ->assertSessionHas('inertia.flash_data.inventoryImport.committed', false);

    expect(InventoryItem::query()->count())->toBe(0);

    foreach ([1, 2] as $attempt) {
        $this->actingAs($owner)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('onboarding.inventory-import.store'), [
                'file' => onboardingCsv($csv),
                'location_id' => $location->id,
            ])
            ->assertRedirect(route('onboarding.show', ['step' => 'opening_stock']));
    }

    expect(InventoryItem::query()->where('organization_id', $organization->id)->count())->toBe(2)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(OrganizationSetupReadiness::resolve($organization->refresh())->ready)->toBeFalse();
});

test('CSV with opening quantities records ledger movements, completes setup, and never duplicates on retry', function () {
    [$organization, $owner] = onboardingTenant();
    [$location, $storageLocation] = onboardingLocation($organization);
    onboardingUnit($organization);

    $csv = "sku,name,base_unit_symbol,opening_quantity,opening_unit_cost\nRICE,Jasmine Rice,kg,25.5,48.25\nSUGAR,White Sugar,kg,10,60\n";

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.inventory-import.store'), [
            'file' => onboardingCsv($csv),
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.inventory-import.store'), [
            'file' => onboardingCsv($csv),
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => 'opening_stock']));

    $rice = InventoryItem::query()->where('sku', 'RICE')->sole();
    $movement = StockMovement::query()->where('inventory_item_id', $rice->id)->sole();

    expect(StockMovement::query()->count())->toBe(2)
        ->and($movement->type)->toBe(StockMovementType::OpeningBalance)
        ->and($movement->storage_location_id)->toBe($storageLocation->id)
        ->and($movement->idempotency_key)->toBe('opening_balance:onboarding:'.$rice->id)
        ->and($organization->refresh()->onboarding_completed_at)->not->toBeNull();

    expect(StockBalance::query()->where('inventory_item_id', $rice->id)->sole()->quantity_on_hand)
        ->toEqual($movement->quantity);
});

test('an invalid CSV row rolls back the whole import', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    onboardingUnit($organization);

    $csv = "sku,name,base_unit_symbol,opening_quantity,opening_unit_cost\nRICE,Jasmine Rice,kg,5,40\nBAD,Bad Item,gallon,,\nZERO,Zero Item,kg,0,10\n";

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.inventory-import.store'), [
            'file' => onboardingCsv($csv),
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('onboarding.show', ['step' => 'inventory']))
        ->assertSessionHas('inertia.flash_data.inventoryImport.committed', false)
        ->assertSessionHas('inertia.flash_data.inventoryImport.errors.0.row', 3)
        ->assertSessionHas('inertia.flash_data.inventoryImport.errors.1.row', 4);

    expect(InventoryItem::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('a CSV missing required columns is rejected before anything runs', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.inventory-import.preview'), [
            'file' => onboardingCsv("name\nRice\n"),
            'location_id' => $location->id,
        ])
        ->assertSessionHasErrors('file');
});

test('opening stock is recorded once through the ledger and completion redirects to the dashboard', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    $item = onboardingItem($organization, onboardingUnit($organization));

    $payload = [
        'resolution' => 'quantity',
        'location_id' => $location->id,
        'quantity' => '12.5',
        'base_unit_cost' => '40.00',
    ];

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $item), $payload)
        ->assertRedirect(route('dashboard'));

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $item), $payload)
        ->assertRedirect(route('onboarding.show', ['step' => 'opening_stock']));

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $item), [...$payload, 'quantity' => '99'])
        ->assertSessionHasErrors('quantity');

    $movement = StockMovement::query()->sole();

    expect($movement->type)->toBe(StockMovementType::OpeningBalance)
        ->and($movement->reference_type)->toBe('onboarding_opening_stock')
        ->and($movement->created_by)->toBe($owner->id)
        ->and(StockBalance::query()->sole()->quantity_on_hand)->toEqual($movement->quantity);
});

test('no opening stock is an explicit state distinct from unresolved and zero quantity', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    $unit = onboardingUnit($organization);
    $waived = onboardingItem($organization, $unit, 'SALT');
    $pending = onboardingItem($organization, $unit, 'PEPPER');

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $pending), [
            'resolution' => 'quantity',
            'location_id' => $location->id,
            'quantity' => '0',
            'base_unit_cost' => '1',
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $waived), ['resolution' => 'none'])
        ->assertRedirect(route('onboarding.show', ['step' => 'opening_stock']));

    $status = OrganizationSetupReadiness::resolve($organization->refresh());

    expect($waived->refresh()->opening_stock_waived_at)->not->toBeNull()
        ->and($waived->opening_stock_waived_by)->toBe($owner->id)
        ->and($pending->refresh()->opening_stock_waived_at)->toBeNull()
        ->and($status->resolvedOpeningStockCount)->toBe(1)
        ->and($status->unresolvedOpeningStockCount)->toBe(1)
        ->and($status->ready)->toBeFalse()
        ->and(StockMovement::query()->count())->toBe(0);

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $pending), ['resolution' => 'none'])
        ->assertRedirect(route('dashboard'));
});

test('opening stock cannot target another organization item or location', function () {
    [$organization, $owner] = onboardingTenant();
    onboardingLocation($organization);
    onboardingItem($organization, onboardingUnit($organization));

    $otherOrganization = Organization::factory()->create();
    [$otherLocation] = onboardingLocation($otherOrganization);
    $otherItem = onboardingItem($otherOrganization, onboardingUnit($otherOrganization), 'FOREIGN');

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $otherItem), ['resolution' => 'none'])
        ->assertNotFound();

    $ownItem = InventoryItem::query()->where('organization_id', $organization->id)->sole();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $ownItem), [
            'resolution' => 'quantity',
            'location_id' => $otherLocation->id,
            'quantity' => '1',
            'base_unit_cost' => '1',
        ])
        ->assertSessionHasErrors('location_id');

    expect($otherItem->refresh()->opening_stock_waived_at)->toBeNull()
        ->and(StockMovement::query()->count())->toBe(0);
});

test('staff without inventory permission cannot resolve opening stock', function () {
    [$organization, $staff] = onboardingTenant(OrganizationRole::KitchenStaff);
    onboardingLocation($organization);
    $item = onboardingItem($organization, onboardingUnit($organization));

    $this->actingAs($staff)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.opening-stock.resolve', $item), ['resolution' => 'none'])
        ->assertForbidden();

    expect($item->refresh()->opening_stock_waived_at)->toBeNull();
});

test('suppliers stay optional and can be skipped', function () {
    [$organization, $owner] = onboardingTenant();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.suppliers.skip'))
        ->assertRedirect(route('onboarding.show'));

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->get(route('onboarding.show'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('steps.5.key', 'suppliers')
            ->where('steps.5.status', 'skipped')
            ->where('steps.5.required', false)
            ->where('steps.6.status', 'locked'));

    [$location] = onboardingLocation($organization);
    $item = onboardingItem($organization, onboardingUnit($organization));

    $item->forceFill(['opening_stock_waived_at' => now()])->save();

    expect(Supplier::query()->count())->toBe(0)
        ->and(OrganizationSetupReadiness::resolve($organization->refresh())->ready)->toBeTrue();
});

test('team invitations can be skipped only after minimum setup', function () {
    [$organization, $owner] = onboardingTenant();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.team.skip'))
        ->assertStatus(409);

    onboardingLocation($organization);
    onboardingItem($organization, onboardingUnit($organization))
        ->forceFill(['opening_stock_waived_at' => now()])
        ->save();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('onboarding.team.skip'))
        ->assertRedirect(route('dashboard'));

    expect($organization->refresh()->onboarding_team_skipped_at)->not->toBeNull()
        ->and(OrganizationSetupReadiness::resolve($organization)->hasPendingOptionalSteps())->toBeTrue();
});

test('incomplete setup blocks operational mutations but keeps navigation available', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    onboardingItem($organization, onboardingUnit($organization));

    $session = ['active_organization_id' => $organization->id];

    $this->actingAs($owner)->withSession($session)
        ->post(route('stock-counts.store'), ['location_id' => $location->id])
        ->assertRedirect(route('onboarding.show'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->actingAs($owner)->withSession($session)
        ->postJson(route('waste.store'), [])
        ->assertStatus(409);

    foreach (['inventory.adjustments.store', 'purchase-orders.store', 'stock-transfers.store'] as $routeName) {
        $this->actingAs($owner)->withSession($session)
            ->post(route($routeName), [])
            ->assertRedirect(route('onboarding.show'));
    }

    foreach (['dashboard', 'inventory.items.index', 'stock-counts.index', 'inventory.opening-balances.create'] as $routeName) {
        $this->actingAs($owner)->withSession($session)
            ->get(route($routeName))
            ->assertOk();
    }

    expect(StockCount::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('a ready organization passes the setup blocker to normal validation', function () {
    [$organization, $owner] = onboardingTenant();
    onboardingLocation($organization);
    onboardingItem($organization, onboardingUnit($organization))
        ->forceFill(['opening_stock_waived_at' => now()])
        ->save();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->from(route('stock-counts.create'))
        ->post(route('stock-counts.store'), [])
        ->assertRedirect(route('stock-counts.create'))
        ->assertSessionHasErrors();
});

test('readiness is recalculated from current records after completion', function () {
    [$organization, $owner] = onboardingTenant();
    [$location] = onboardingLocation($organization);
    $unit = onboardingUnit($organization);
    onboardingItem($organization, $unit)
        ->forceFill(['opening_stock_waived_at' => now()])
        ->save();

    expect(OrganizationSetupReadiness::isReady($organization))->toBeTrue()
        ->and($organization->refresh()->onboarding_completed_at)->not->toBeNull();

    onboardingItem($organization, $unit, 'NEWITEM');

    expect(OrganizationSetupReadiness::isReady($organization->refresh()))->toBeTrue();

    $location->update(['active' => false]);

    expect(OrganizationSetupReadiness::isReady($organization->refresh()))->toBeFalse();

    $this->actingAs($owner)
        ->withSession(['active_organization_id' => $organization->id])
        ->post(route('stock-counts.store'), [])
        ->assertRedirect(route('onboarding.show'));
});

test('the setup checklist is server derived and never completed by visiting a step', function () {
    [$organization, $owner] = onboardingTenant();

    foreach (['location', 'units', 'inventory', 'opening_stock'] as $step) {
        $this->actingAs($owner)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('onboarding.show', ['step' => $step]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('currentStep', $step)
                ->where('ready', false)
                ->where('steps.0.status', 'complete')
                ->where('steps.1.status', 'not_started')
                ->where('steps.4.status', 'locked')
                ->where('setup.ready', false));
    }

    expect($organization->refresh()->onboarding_completed_at)->toBeNull();
});
