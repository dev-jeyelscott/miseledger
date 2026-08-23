<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;

test('organization uses the Cashier Billable trait', function () {
    expect(class_uses_recursive(Organization::class))->toHaveKey(Billable::class);
});

test('user does not use the Cashier Billable trait', function () {
    expect(class_uses_recursive(User::class))->not->toHaveKey(Billable::class);
});

test('cashier resolves stripe customers through organization', function () {
    expect(Cashier::$customerModel)->toBe(Organization::class);
});

test('organization membership relationships remain intact alongside billing configuration', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create();

    expect($organization->users)->toHaveCount(1)
        ->and($organization->users->first()->is($user))->toBeTrue()
        ->and($user->organizations)->toHaveCount(1)
        ->and($user->organizations->first()->is($organization))->toBeTrue();
});
