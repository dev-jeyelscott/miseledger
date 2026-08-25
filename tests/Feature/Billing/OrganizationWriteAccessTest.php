<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Fakes the Stripe HTTP transport boundary Cashier's `ApiRequestor` uses,
 * so this billing recovery test never makes a real network call while still
 * exercising the real Cashier/Stripe SDK request-building code.
 */
final class FakeOrganizationWriteAccessStripeHttpClient implements ClientInterface
{
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if (str_contains($absUrl, '/v1/billing_portal/sessions')) {
            return [json_encode([
                'id' => 'bps_test_123',
                'object' => 'billing_portal.session',
                'url' => 'https://billing.stripe.com/p/session/bps_test_123',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

function organizationWriteAccessSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ], $attributes));
}

test('a writable organization can create a business record', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    $this->assertDatabaseHas('inventory_categories', [
        'organization_id' => $organization->id,
        'name' => 'Dry goods',
    ]);
});

test('a past due organization retains write access with a billing warning', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    organizationWriteAccessSubscription($organization, [
        'stripe_status' => 'past_due',
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    $this->assertDatabaseHas('inventory_categories', [
        'organization_id' => $organization->id,
        'name' => 'Dry goods',
    ]);
});

test('a commercially read-only organization cannot create a business record', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('inventory_categories', [
        'organization_id' => $organization->id,
        'name' => 'Dry goods',
    ]);
});

test('an unpaid organization cannot mutate organization settings', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    organizationWriteAccessSubscription($organization, [
        'stripe_status' => 'unpaid',
    ]);

    $this->actingAs($user)
        ->put(route('organizations.settings.update', $organization), [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => $organization->name,
    ]);
});

test('an unpaid organization recovering to active regains write access without any ledger mutation from the billing transition itself', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    organizationWriteAccessSubscription($organization, [
        'stripe_status' => 'unpaid',
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('inventory_categories', [
        'organization_id' => $organization->id,
        'name' => 'Dry goods',
    ]);

    expect(StockMovement::query()->count())->toBe(0);
    expect(StockBalance::query()->count())->toBe(0);

    $organization->subscription(config('billing.subscription_type'))->update([
        'stripe_status' => 'active',
    ]);

    expect(StockMovement::query()->count())->toBe(0);
    expect(StockBalance::query()->count())->toBe(0);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    $this->assertDatabaseHas('inventory_categories', [
        'organization_id' => $organization->id,
        'name' => 'Dry goods',
    ]);

    expect(StockMovement::query()->count())->toBe(0);
    expect(StockBalance::query()->count())->toBe(0);
});

test('a read-only organization still exposes GET reports and history routes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.stock-movements.index'))
        ->assertOk();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('organizations.billing.show', $organization))
        ->assertOk();
});

test('a read-only organization can still reach billing recovery and organization switching routes', function () {
    Config::set('cashier.secret', 'sk_test_fake');
    ApiRequestor::setHttpClient(new FakeOrganizationWriteAccessStripeHttpClient);

    $user = User::factory()->create();
    $readOnlyOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'stripe_id' => 'cus_test_read_only',
    ]);
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($readOnlyOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    OrganizationMembership::factory()
        ->for($otherOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->post(route('organizations.billing.portal', $readOnlyOrganization))
        ->assertRedirect();

    $this->actingAs($user)
        ->put(route('organizations.activate', $otherOrganization))
        ->assertRedirect(route('dashboard'));

    ApiRequestor::setHttpClient(null);
});

test('organization creation follows onboarding policy and is never blocked as an existing organization', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'New Restaurant',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('organizations', [
        'name' => 'New Restaurant',
    ]);
});

test('an inactive organization remains blocked by existing administrative authorization regardless of commercial state', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'active' => false,
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->put(route('organizations.settings.update', $organization), [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
            'active' => true,
        ])
        ->assertForbidden();
});

test('an unauthorized user targeting a read-only organization receives the same denial as targeting a writable one', function () {
    $user = User::factory()->create();

    $writableOrganization = Organization::factory()->create();
    $readOnlyOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $writableResponse = $this->actingAs($user)
        ->put(route('organizations.settings.update', $writableOrganization), [
            'name' => $writableOrganization->name,
            'slug' => $writableOrganization->slug,
            'timezone' => $writableOrganization->timezone,
            'currency' => $writableOrganization->currency,
            'active' => true,
        ]);

    $readOnlyResponse = $this->actingAs($user)
        ->put(route('organizations.settings.update', $readOnlyOrganization), [
            'name' => $readOnlyOrganization->name,
            'slug' => $readOnlyOrganization->slug,
            'timezone' => $readOnlyOrganization->timezone,
            'currency' => $readOnlyOrganization->currency,
            'active' => true,
        ]);

    $writableResponse->assertForbidden();
    $readOnlyResponse->assertForbidden();

    expect($readOnlyResponse->getContent())->toBe($writableResponse->getContent());
});

test('cross organization write access is denied regardless of the target organizations commercial state', function () {
    $owner = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($owner)
        ->put(route('organizations.settings.update', $otherOrganization), [
            'name' => $otherOrganization->name,
            'slug' => $otherOrganization->slug,
            'timezone' => $otherOrganization->timezone,
            'currency' => $otherOrganization->currency,
            'active' => true,
        ])
        ->assertForbidden();
});
