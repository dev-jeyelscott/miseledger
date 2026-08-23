<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function createSubscriptionNoticeOrganizationSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ], $attributes));
}

test('switching the active organization changes the exposed notice state and never leaks another organizations billing state', function () {
    $user = User::factory()->create();

    $pastDueOrganization = Organization::factory()->create();
    $readOnlyOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    OrganizationMembership::factory()
        ->for($pastDueOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    OrganizationMembership::factory()
        ->for($readOnlyOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createSubscriptionNoticeOrganizationSubscription($pastDueOrganization, [
        'stripe_status' => 'past_due',
    ]);

    $this->withSession(['active_organization_id' => $pastDueOrganization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.active.id', $pastDueOrganization->id)
                ->where('organizationContext.subscription.status', 'past_due')
                ->where('organizationContext.subscription.accessMode', 'writable')
                ->where('organizationContext.subscription.billingWarning', true)
        );

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.active.id', $readOnlyOrganization->id)
                ->where('organizationContext.subscription.status', null)
                ->where('organizationContext.subscription.accessMode', 'read_only')
                ->where('organizationContext.subscription.billingWarning', false)
        );
});

test('a past due organization retains its billing warning even when also scheduled to end', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createSubscriptionNoticeOrganizationSubscription($organization, [
        'stripe_status' => 'past_due',
        'ends_at' => Carbon::now()->addDays(5),
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.status', 'past_due')
                ->where('organizationContext.subscription.accessMode', 'writable')
                ->where('organizationContext.subscription.billingWarning', true)
                ->has('organizationContext.subscription.endsAt')
        );
});

test('an unpaid organization is exposed as read-only for the persistent read-only notice', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createSubscriptionNoticeOrganizationSubscription($organization, [
        'stripe_status' => 'unpaid',
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.status', 'unpaid')
                ->where('organizationContext.subscription.accessMode', 'read_only')
        );
});

test('a scheduled cancellation retains writable access while exposing an ends-at date for the notice', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createSubscriptionNoticeOrganizationSubscription($organization, [
        'stripe_status' => 'active',
        'ends_at' => Carbon::now()->addDays(5),
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.accessMode', 'writable')
                ->has('organizationContext.subscription.endsAt')
        );
});

test('a billing-authorized owner receives billing.manage among their active organization permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'organizationContext.memberships.0.permissions',
                    fn ($permissions) => collect($permissions)->contains('billing.manage'),
                )
        );
});

test('a non-billing role does not receive billing.manage among their active organization permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::KitchenStaff]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'organizationContext.memberships.0.permissions',
                    fn ($permissions) => ! collect($permissions)->contains('billing.manage'),
                )
        );
});
