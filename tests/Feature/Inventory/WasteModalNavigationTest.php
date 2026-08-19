<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Support\Facades\File;

/**
 * Create an authenticated owner with an active organization context.
 *
 * @return array{User, Organization}
 */
function wasteModalNavigationContext(): array
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

test('modal waste reason creation returns to the exact waste report context', function () {
    [$user, $organization] = wasteModalNavigationContext();

    $indexUrl = route('waste.index', [
        'from' => '2026-08-01',
        'to' => '2026-08-31',
        'page' => 2,
    ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($indexUrl)
        ->post(route('waste-reasons.store'), [
            '_modal' => '1',
            'name' => 'Trim loss',
        ])
        ->assertRedirect($indexUrl)
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('waste_reasons', [
        'organization_id' => $organization->id,
        'name' => 'Trim loss',
        'active' => true,
    ]);
});

test('modal waste reason status change returns to the exact waste report context', function () {
    [$user, $organization] = wasteModalNavigationContext();

    $reason = WasteReason::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $indexUrl = route('waste.index', [
        'waste_reason_id' => $reason->id,
        'page' => 3,
    ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($indexUrl)
        ->put(route('waste-reasons.update', $reason), [
            '_modal' => '1',
            'active' => '0',
        ])
        ->assertRedirect($indexUrl)
        ->assertSessionHasNoErrors();

    expect($reason->refresh()->active)->toBeFalse();
});

test('modal waste reason validation returns to the exact report context', function () {
    [$user, $organization] = wasteModalNavigationContext();

    WasteReason::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $indexUrl = route('waste.index', [
        'from' => '2026-08-01',
        'page' => 2,
    ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($indexUrl)
        ->post(route('waste-reasons.store'), [
            '_modal' => '1',
            'name' => 'Spoilage',
        ])
        ->assertRedirect($indexUrl)
        ->assertSessionHasErrors('name');
});

test('modal waste reason mutation rejects an external return destination', function () {
    [$user, $organization] = wasteModalNavigationContext();

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from('https://example.com/outside')
        ->post(route('waste-reasons.store'), [
            '_modal' => '1',
            'name' => 'Preparation loss',
        ])
        ->assertRedirect(route('waste.index'));
});

test('waste reason actions use guarded dialogs without adding modal history entries', function () {
    $source = File::get(resource_path('js/pages/waste/index.tsx'));

    expect($source)
        ->toContain('CreateWasteReasonDialog')
        ->toContain('WasteReasonStatusDialog')
        ->toContain('useGuardedDialog')
        ->toContain('name="_modal"')
        ->toContain("'destructive'")
        ->toContain('Deactivate waste reason?')
        ->toContain('Historical waste records')
        ->toContain("router.on('before'")
        ->toContain("window.addEventListener('beforeunload'")
        ->not->toContain('router.visit(')
        ->not->toContain('router.push(');

    $history = File::get(resource_path('js/lib/navigation-history.ts'));

    expect($history)
        ->toContain('window.history.back();')
        ->toContain('router.visit(fallbackUrl, {')
        ->toContain('replace: true');
});
