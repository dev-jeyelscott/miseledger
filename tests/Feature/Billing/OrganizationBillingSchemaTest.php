<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

test('organizations table carries the published cashier billing columns', function () {
    expect(Schema::hasColumns('organizations', [
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('users', [
            'stripe_id',
            'pm_type',
            'pm_last_four',
            'trial_ends_at',
        ]))->toBeFalse();
});

test('subscriptions table owns subscriptions by organization, not user', function () {
    expect(Schema::hasColumn('subscriptions', 'organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('subscriptions', 'user_id'))->toBeFalse();
});

test('a subscription created for an organization resolves its owner back to that organization', function () {
    $organization = Organization::factory()->create();

    $subscription = $organization->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);

    expect($subscription->organization_id)->toBe($organization->id)
        ->and($subscription->owner->is($organization))->toBeTrue();
});
