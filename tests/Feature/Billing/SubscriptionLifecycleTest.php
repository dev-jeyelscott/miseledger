<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationAccessMode;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

function lifecycleSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_lifecycle_starter',
        'quantity' => 1,
    ], $attributes));
}

function lifecyclePlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'price_lifecycle_starter', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
        'growth' => [
            'name' => 'Growth',
            'prices' => ['monthly' => 'price_lifecycle_growth', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);
}

/**
 * @return array{location: Location, storageLocation: StorageLocation, item: InventoryItem, unit: UnitOfMeasure}
 */
function lifecycleStockFixture(Organization $organization): array
{
    $location = Location::factory()->for($organization)->create(['active' => true]);
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Main storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();
    $unit = UnitOfMeasure::factory()->for($organization)->create([
        'dimension' => 'count',
        'active' => true,
    ]);
    $item = InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $unit,
        referenceType: 'opening_balance',
        referenceId: $item->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "lifecycle:opening:{$item->id}",
        inboundUnitCost: '2',
    );

    return compact('location', 'storageLocation', 'item', 'unit');
}

/**
 * @param  array{location: Location, storageLocation: StorageLocation, item: InventoryItem, unit: UnitOfMeasure}  $fixture
 * @return array<string, mixed>
 */
function lifecycleAdjustmentPayload(Organization $organization, array $fixture): array
{
    return [
        'operation_id' => (string) Str::uuid(),
        'location_id' => $fixture['location']->id,
        'storage_location_id' => $fixture['storageLocation']->id,
        'inventory_item_id' => $fixture['item']->id,
        'quantity' => '2',
        'unit_id' => $fixture['unit']->id,
        'reason' => 'Lifecycle test adjustment',
        'occurred_at' => now()->setTimezone($organization->timezone)->format('Y-m-d\TH:i'),
    ];
}

test('synchronized billing lifecycle states resolve deterministically', function (
    ?string $status,
    ?Carbon $trialEndsAt,
    ?Carbon $endsAt,
    OrganizationAccessMode $expectedMode,
    bool $expectedTrial,
    bool $expectedGracePeriod,
    bool $expectedWarning,
) {
    lifecyclePlans();

    $organization = Organization::factory()->create([
        'trial_ends_at' => $status === null ? $trialEndsAt : Carbon::now()->subDay(),
    ]);

    if ($status !== null) {
        lifecycleSubscription($organization, [
            'stripe_status' => $status,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $endsAt,
        ]);
    }

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe($expectedMode)
        ->and($access->onTrial)->toBe($expectedTrial)
        ->and($access->onGracePeriod)->toBe($expectedGracePeriod)
        ->and($access->billingWarning)->toBe($expectedWarning)
        ->and($access->subscriptionStatus)->toBe($status);
})->with([
    'generic trial' => [null, Carbon::now()->addDays(7), null, OrganizationAccessMode::Writable, true, false, false],
    'expired generic trial' => [null, Carbon::now()->subSecond(), null, OrganizationAccessMode::ReadOnly, false, false, false],
    'Stripe trial' => ['trialing', Carbon::now()->addDays(7), null, OrganizationAccessMode::Writable, true, false, false],
    'active subscription' => ['active', null, null, OrganizationAccessMode::Writable, false, false, false],
    'past due subscription' => ['past_due', null, null, OrganizationAccessMode::Writable, false, false, true],
    'scheduled cancellation' => ['active', null, Carbon::now()->addDays(7), OrganizationAccessMode::Writable, false, true, true],
    'canceled in grace period' => ['canceled', null, Carbon::now()->addDays(7), OrganizationAccessMode::Writable, false, true, true],
    'ended cancellation' => ['canceled', null, Carbon::now()->subSecond(), OrganizationAccessMode::ReadOnly, false, false, false],
    'unpaid subscription' => ['unpaid', null, Carbon::now()->addDays(7), OrganizationAccessMode::ReadOnly, false, true, false],
]);

test('a resumed subscription and plan changes restore writable access from synchronized state', function () {
    lifecyclePlans();

    $organization = Organization::factory()->create(['active' => false]);
    lifecycleSubscription($organization, [
        'stripe_status' => 'canceled',
        'ends_at' => Carbon::now()->subSecond(),
    ]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization->fresh())->accessMode)
        ->toBe(OrganizationAccessMode::ReadOnly);

    $subscription = $organization->fresh()->subscription(config('billing.subscription_type'));
    $subscription->update([
        'stripe_status' => 'active',
        'ends_at' => null,
        'stripe_price' => 'price_lifecycle_growth',
    ]);

    $resumedAccess = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($resumedAccess->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($resumedAccess->billingWarning)->toBeFalse()
        ->and($resumedAccess->plan?->value)->toBe('growth')
        ->and($organization->fresh()->active)->toBeFalse();

    $subscription->update(['stripe_price' => 'price_lifecycle_starter']);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization->fresh())->plan?->value)
        ->toBe('starter');
});

test('read-only lifecycle access preserves RBAC, organization isolation, history, and stock balances', function () {
    $owner = User::factory()->create();
    $kitchenUser = User::factory()->create();
    $readOnlyOrganization = Organization::factory()->create();
    $writableOrganization = Organization::factory()->create();

    OrganizationMembership::factory()->for($readOnlyOrganization)->for($owner)->create([
        'role' => OrganizationRole::Owner,
    ]);
    OrganizationMembership::factory()->for($readOnlyOrganization)->for($kitchenUser)->create([
        'role' => OrganizationRole::KitchenStaff,
    ]);
    OrganizationMembership::factory()->for($writableOrganization)->for($owner)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $readOnlyFixture = lifecycleStockFixture($readOnlyOrganization);
    $writableFixture = lifecycleStockFixture($writableOrganization);
    lifecycleSubscription($readOnlyOrganization, ['stripe_status' => 'unpaid']);

    $readOnlyBalancesBefore = StockBalance::query()
        ->where('organization_id', $readOnlyOrganization->id)
        ->get(['id', 'quantity_on_hand'])
        ->map(fn (StockBalance $balance): array => $balance->only(['id', 'quantity_on_hand']))
        ->all();

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($owner)
        ->post(route('inventory.adjustments.store'), lifecycleAdjustmentPayload($readOnlyOrganization, $readOnlyFixture))
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($owner)
        ->get(route('inventory.stock-movements.index'))
        ->assertOk();

    $this->withSession(['active_organization_id' => $writableOrganization->id])
        ->actingAs($owner)
        ->post(route('inventory.adjustments.store'), lifecycleAdjustmentPayload($writableOrganization, $writableFixture))
        ->assertRedirect(route('inventory.items.index'));

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($kitchenUser)
        ->post(route('inventory.adjustments.store'), lifecycleAdjustmentPayload($readOnlyOrganization, $readOnlyFixture))
        ->assertForbidden();

    expect(StockBalance::query()
        ->where('organization_id', $readOnlyOrganization->id)
        ->get(['id', 'quantity_on_hand'])
        ->map(fn (StockBalance $balance): array => $balance->only(['id', 'quantity_on_hand']))
        ->all())->toBe($readOnlyBalancesBefore)
        ->and(StockMovement::query()->where('organization_id', $readOnlyOrganization->id)->count())->toBe(1)
        ->and(StockBalance::query()->where('organization_id', $writableOrganization->id)->sole()->quantity_on_hand)->toBe('12.000000')
        ->and($readOnlyOrganization->fresh()->active)->toBeTrue();
});
