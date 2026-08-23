<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

test('an owner sees a pending processing state when webhook synchronization has not landed yet', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.checkout.success', $organization),
    );

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('organizations/billing/checkout-success')
            ->where('synchronized', false)
            ->where('subscription.status', null)
            ->where('subscription.accessMode', 'read_only'),
    );
});

test('an owner sees the synchronized subscription state once the webhook has landed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.checkout.success', $organization),
    );

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('organizations/billing/checkout-success')
            ->where('synchronized', true)
            ->where('subscription.status', 'active')
            ->where('subscription.accessMode', 'writable'),
    );
});

test('an owner can view the Checkout cancellation page without any subscription state change', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.checkout.cancel', $organization),
    );

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('organizations/billing/checkout-cancelled')
            ->where('organization.id', $organization->id),
    );

    expect($organization->fresh()->subscriptions()->count())->toBe(0);
});

test('a non-owner without billing.manage is denied the success page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.success', $organization),
    )->assertForbidden();
});

test('a non-owner without billing.manage is denied the cancellation page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.cancel', $organization),
    )->assertForbidden();
});

test('a user with no membership in the target organization is denied both pages', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.success', $organization),
    )->assertForbidden();

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.cancel', $organization),
    )->assertForbidden();
});

test('an owner of a different organization cannot view another organization Checkout status', function () {
    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($ownedOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $otherOrganization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.success', $otherOrganization),
    )->assertForbidden();

    $this->actingAs($user)->get(
        route('organizations.billing.checkout.cancel', $otherOrganization),
    )->assertForbidden();
});
