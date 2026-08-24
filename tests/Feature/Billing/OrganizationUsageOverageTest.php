<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function overagePlansConfig(): void
{
    Config::set('billing.plans', [
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'monthly' => 'price_growth_monthly',
                'yearly' => null,
            ],
            'features' => ['locations.multi'],
            'limits' => ['seats' => 5, 'locations' => 5, 'inventory_items' => 5],
        ],
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['locations.multi'],
            'limits' => ['seats' => 1, 'locations' => 1, 'inventory_items' => 1],
        ],
    ]);
}

function overageSubscribe(Organization $organization, string $priceId): void
{
    // Re-fetch to avoid Cashier's cached `subscriptions` relation masking a
    // subscription created earlier in the same test, mirroring how a real
    // webhook-synchronized plan change is observed on a fresh model load.
    $organization = $organization->fresh();

    $subscription = $organization->subscription(config('billing.subscription_type'));

    if ($subscription !== null) {
        $subscription->update(['stripe_price' => $priceId]);

        return;
    }

    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);

    $organization->update(['trial_ends_at' => null]);
}

test('downgrading below current usage preserves memberships, locations, and inventory items', function () {
    overagePlansConfig();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $extraMember = User::factory()->create();
    OrganizationMembership::factory()
        ->for($organization)
        ->for($extraMember)
        ->create(['role' => OrganizationRole::InventoryStaff]);

    Location::factory()->for($organization)->count(3)->create();
    InventoryItem::factory()->for($organization)->count(3)->create();

    overageSubscribe($organization, 'price_growth_monthly');

    $this->assertDatabaseCount('organization_memberships', 2);
    $this->assertDatabaseCount('locations', 3);
    $this->assertDatabaseCount('inventory_items', 3);

    // Downgrade below current usage on every dimension.
    overageSubscribe($organization, 'price_starter_monthly');

    $this->assertDatabaseCount('organization_memberships', 2);
    $this->assertDatabaseCount('locations', 3);
    $this->assertDatabaseCount('inventory_items', 3);

    $organization->refresh();
    expect($organization->active)->toBeTrue();
});

test('a downgraded organization can still read existing over-limit data', function () {
    overagePlansConfig();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    Location::factory()->for($organization)->count(3)->create();

    overageSubscribe($organization, 'price_growth_monthly');
    overageSubscribe($organization, 'price_starter_monthly');

    $this->actingAs($owner)
        ->get(route('organizations.locations.index', $organization))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->has('locations', 3),
        );
});

test('a downgraded organization is blocked from creating new resources above the limit', function () {
    overagePlansConfig();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    Location::factory()->for($organization)->count(3)->create();
    InventoryItem::factory()->for($organization)->count(3)->create();

    $applicant = User::factory()->create();

    overageSubscribe($organization, 'price_growth_monthly');
    overageSubscribe($organization, 'price_starter_monthly');

    $this->actingAs($owner)
        ->post(
            route('organizations.locations.store', $organization),
            ['name' => 'Extra Kitchen', 'code' => 'EXTRA'],
        )
        ->assertSessionHasErrors('name');

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertSessionHasErrors('email');

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('inventory.items.store'), [
            'name' => 'Extra Item',
            'sku' => 'SKU-EXTRA',
            'base_unit_of_measure_id' => UnitOfMeasure::factory()->for($organization)->create()->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('locations', 3);
    $this->assertDatabaseCount('organization_memberships', 1);
    $this->assertDatabaseCount('inventory_items', 3);
});

test('restoring a sufficient plan removes the creation block after synchronized commercial state changes', function () {
    overagePlansConfig();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    Location::factory()->for($organization)->count(3)->create();

    overageSubscribe($organization, 'price_growth_monthly');
    overageSubscribe($organization, 'price_starter_monthly');

    $this->actingAs($owner)
        ->post(
            route('organizations.locations.store', $organization),
            ['name' => 'Extra Kitchen', 'code' => 'EXTRA'],
        )
        ->assertSessionHasErrors('name');

    // Restore a plan with enough capacity, mirroring a Cashier-synced upgrade webhook.
    overageSubscribe($organization, 'price_growth_monthly');

    $this->actingAs($owner)
        ->post(
            route('organizations.locations.store', $organization),
            ['name' => 'Extra Kitchen', 'code' => 'EXTRA'],
        )
        ->assertRedirect(route('organizations.locations.index', $organization));

    $this->assertDatabaseCount('locations', 4);
});
