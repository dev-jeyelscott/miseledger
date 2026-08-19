<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Supplier;
use App\Models\User;

/**
 * Create an owner with an active organization for supplier modal tests.
 *
 * @return array{0: User, 1: Organization}
 */
function createSupplierModalNavigationContext(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    return [$user, $organization];
}

test(
    'supplier modal creation returns to the exact filtered supplier context',
    function () {
        [$user, $organization] = createSupplierModalNavigationContext();

        $indexUrl = route('suppliers.index', [
            'search' => 'metro',
            'status' => 'active',
            'sort' => 'code_desc',
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->from($indexUrl)
            ->post(route('suppliers.store'), [
                '_modal' => '1',
                'name' => 'Metro Food Supply',
                'code' => 'METRO',
                'contact_name' => 'Maria Santos',
                'email' => 'sales@example.com',
                'phone' => '09170000000',
                'payment_terms' => 'Net 30',
                'lead_time_days' => '3',
                'active' => '1',
            ])
            ->assertRedirect($indexUrl);

        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $organization->id,
            'name' => 'Metro Food Supply',
            'code' => 'METRO',
        ]);
    },
);

test(
    'supplier modal update returns to the exact filtered supplier context',
    function () {
        [$user, $organization] = createSupplierModalNavigationContext();

        $supplier = Supplier::factory()
            ->for($organization)
            ->create([
                'name' => 'Original Supplier',
                'code' => 'ORIGINAL',
                'active' => true,
            ]);

        $indexUrl = route('suppliers.index', [
            'search' => 'original',
            'status' => 'active',
            'sort' => 'name_desc',
            'per_page' => 10,
        ]);

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->from($indexUrl)
            ->put(route('suppliers.update', $supplier), [
                '_modal' => '1',
                'name' => 'Updated Supplier',
                'code' => 'UPDATED',
                'contact_name' => 'Updated Contact',
                'email' => 'updated@example.com',
                'phone' => '09171111111',
                'payment_terms' => 'Net 15',
                'lead_time_days' => '2',
                'active' => '0',
            ])
            ->assertRedirect($indexUrl);

        $supplier->refresh();

        expect($supplier->name)->toBe('Updated Supplier')
            ->and($supplier->code)->toBe('UPDATED')
            ->and($supplier->active)->toBeFalse();
    },
);

test(
    'supplier modal validation returns to the exact supplier context',
    function () {
        [$user, $organization] = createSupplierModalNavigationContext();

        $indexUrl = route('suppliers.index', [
            'status' => 'inactive',
            'sort' => 'code_asc',
            'per_page' => 50,
        ]);

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->from($indexUrl)
            ->post(route('suppliers.store'), [
                '_modal' => '1',
                'name' => '',
                'code' => '',
                'active' => '1',
            ])
            ->assertRedirect($indexUrl)
            ->assertSessionHasErrors([
                'name',
                'code',
            ]);

        $this->assertDatabaseCount('suppliers', 0);
    },
);

test(
    'standalone supplier submissions retain their existing edit redirects',
    function () {
        [$user, $organization] = createSupplierModalNavigationContext();

        $response = $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->post(route('suppliers.store'), [
                'name' => 'Standalone Supplier',
                'code' => 'STANDALONE',
                'active' => '1',
            ]);

        $supplier = Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'STANDALONE')
            ->firstOrFail();

        $response->assertRedirect(
            route('suppliers.edit', $supplier),
        );

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->put(route('suppliers.update', $supplier), [
                'name' => 'Standalone Updated',
                'code' => 'STANDALONE',
                'active' => '1',
            ])
            ->assertRedirect(
                route('suppliers.edit', $supplier),
            );
    },
);

test(
    'supplier ui uses modal actions and shared history aware return controls',
    function () {
        $indexSource = (string) file_get_contents(
            resource_path('js/pages/suppliers/index.tsx'),
        );

        $createSource = (string) file_get_contents(
            resource_path('js/pages/suppliers/create.tsx'),
        );

        $supplierItemSource = (string) file_get_contents(
            resource_path('js/pages/suppliers/items/edit.tsx'),
        );

        expect($indexSource)
            ->toContain('function CreateSupplierDialog')
            ->toContain('function EditSupplierDialog')
            ->toContain('useGuardedDialog')
            ->toContain('name="_modal"')
            ->not->toContain('href={SupplierController.create()}');

        expect($createSource)
            ->toContain('PreviousPageButton')
            ->toContain('fallback={')
            ->toContain('SupplierController.index().url');

        expect($supplierItemSource)
            ->toContain('PreviousPageButton')
            ->toContain(
                'fallback={SupplierController.edit(supplier.id).url}',
            );
    },
);
