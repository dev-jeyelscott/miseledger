<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard exposes active organization settings to an authorized owner', function () {
    $organization = Organization::factory()->create([
        'name' => 'Modal Settings Restaurant',
        'slug' => 'modal-settings-restaurant',
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
        'active' => true,
    ]);
    $owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->actingAs($owner)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where(
                    'dashboard.organizationSettings.id',
                    $organization->id,
                )
                ->where(
                    'dashboard.organizationSettings.name',
                    'Modal Settings Restaurant',
                )
                ->where(
                    'dashboard.organizationSettings.slug',
                    'modal-settings-restaurant',
                )
                ->where(
                    'dashboard.organizationSettings.timezone',
                    'Asia/Manila',
                )
                ->where(
                    'dashboard.organizationSettings.currency',
                    'PHP',
                )
                ->where('dashboard.organizationSettings.active', true),
        );
});

test('dashboard withholds organization settings without organization manage permission', function () {
    $organization = Organization::factory()->create();
    $manager = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $this->actingAs($manager)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('dashboard.organizationSettings', null),
        );
});
