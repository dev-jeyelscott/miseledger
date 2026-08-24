<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

test('sidebar keeps grouped permission aware navigation and the authorized organization switcher', function () {
    $sidebar = (string) file_get_contents(
        resource_path('js/components/app-sidebar.tsx'),
    );

    $navigation = (string) file_get_contents(
        resource_path('js/components/nav-main.tsx'),
    );

    $switcher = (string) file_get_contents(
        resource_path('js/components/organization-switcher.tsx'),
    );

    expect($sidebar)
        ->toContain("title: 'Inventory'")
        ->toContain("title: 'Purchasing'")
        ->toContain("title: 'Recipes'")
        ->toContain("title: 'Reports'")
        ->toContain("title: 'Organization'")
        ->toContain("permissions.has('inventory.view')")
        ->toContain("permissions.has('purchasing.view')")
        ->toContain("permissions.has('reports.view')")
        ->toContain("permissions.has('locations.manage')")
        ->toContain("permissions.has('users.manage')")
        ->toContain("permissions.has('organization.manage')")
        ->toContain('<OrganizationSwitcher');

    expect($sidebar)
        ->toContain("permissions.has('purchasing.view') && grants?.purchasing")
        ->toContain("permissions.has('recipes.view') && grants?.recipes")
        ->toContain("permissions.has('locations.manage') &&\n            grants?.['locations.multi']");

    expect($sidebar)
        ->not->toContain('laravel/react-starter-kit')
        ->not->toContain('laravel.com/docs/starter-kits');

    expect($navigation)
        ->toContain('isCurrentOrParentUrl')
        ->toContain('aria-current')
        ->toContain('SidebarGroupLabel');

    expect($switcher)
        ->toContain('organizationContext.memberships')
        ->toContain('OrganizationController.activate.form')
        ->toContain('router.flushAll()')
        ->toContain('Current organization');
});

test('organization switching rejects an organization outside the users memberships', function () {
    $user = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this
        ->actingAs($user)
        ->withSession([
            'active_organization_id' => $ownOrganization->id,
        ])
        ->put(
            route(
                'organizations.activate',
                $otherOrganization,
            ),
        )
        ->assertForbidden()
        ->assertSessionHas(
            'active_organization_id',
            $ownOrganization->id,
        );
});
