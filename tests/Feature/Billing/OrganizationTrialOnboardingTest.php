<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner sees the active organization trial status, end date, plan, and a billing setup CTA', function () {
    Config::set('billing.trial_days', 14);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('organizations.store'), [
        'name' => 'Onboarding Restaurant',
    ]);

    $organization = Organization::query()->sole();
    $response->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard')
                ->where('organizationContext.active.id', $organization->id)
                ->where('organizationContext.subscription.onTrial', true)
                ->where(
                    'organizationContext.subscription.trialEndsAt',
                    $organization->trial_ends_at->toISOString(),
                )
                ->where('organizationContext.subscription.plan', null)
                ->where(
                    'organizationContext.memberships.0.permissions',
                    fn ($permissions) => collect($permissions)->contains(
                        'billing.manage',
                    ),
                ),
        );
});

test('a non-billing-authorized member does not receive billing.manage authority on the dashboard', function () {
    Config::set('billing.trial_days', 14);

    $member = User::factory()->create();
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->addDays(14),
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($member)
        ->create(['role' => OrganizationRole::InventoryStaff]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.active.id', $organization->id)
                ->where('organizationContext.subscription.onTrial', true)
                ->where(
                    'organizationContext.memberships.0.permissions',
                    fn ($permissions) => ! collect($permissions)->contains(
                        'billing.manage',
                    ),
                ),
        );

    $this->actingAs($member)
        ->get(route('organizations.billing.show', $organization))
        ->assertForbidden();
});

test('a user cannot see or start billing for an organization other than the active one', function () {
    Config::set('billing.trial_days', 14);

    $owner = User::factory()->create();

    $ownedOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->addDays(14),
    ]);
    $otherOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->addDays(14),
    ]);

    OrganizationMembership::factory()
        ->for($ownedOrganization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession([
        'active_organization_id' => $ownedOrganization->id,
    ])
        ->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->where(
                'organizationContext.active.id',
                $ownedOrganization->id,
            ),
        );

    $this->actingAs($owner)
        ->get(route('organizations.billing.show', $otherOrganization))
        ->assertForbidden();
});
