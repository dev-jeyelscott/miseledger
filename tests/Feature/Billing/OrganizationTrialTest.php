<?php

use App\Actions\Organizations\CreateOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('a newly created organization receives a trial_ends_at derived from the configured trial duration', function () {
    Config::set('billing.trial_days', 14);

    Carbon::setTestNow(Carbon::parse('2026-08-23 00:00:00'));

    $organization = app(CreateOrganization::class)->handle(User::factory()->create(), 'Trial Restaurant');

    expect($organization->trial_ends_at)->not->toBeNull()
        ->and($organization->trial_ends_at->equalTo(Carbon::parse('2026-09-06 00:00:00')))->toBeTrue();

    Carbon::setTestNow();
});

test('the trial is stored on the organization and not on the owner user', function () {
    Config::set('billing.trial_days', 14);

    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'Owner Trial Restaurant');

    expect($organization->trial_ends_at)->not->toBeNull();
    expect($user->getAttributes())->not->toHaveKey('trial_ends_at');
});

test('organizations owned by the same user each receive an independent trial', function () {
    Config::set('billing.trial_days', 14);

    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-08-23 00:00:00'));
    $first = app(CreateOrganization::class)->handle($user, 'First Restaurant');

    Carbon::setTestNow(Carbon::parse('2026-08-25 00:00:00'));
    $second = app(CreateOrganization::class)->handle($user, 'Second Restaurant');

    Carbon::setTestNow();

    expect($first->trial_ends_at->equalTo(Carbon::parse('2026-09-06 00:00:00')))->toBeTrue()
        ->and($second->trial_ends_at->equalTo(Carbon::parse('2026-09-08 00:00:00')))->toBeTrue()
        ->and($first->trial_ends_at->equalTo($second->trial_ends_at))->toBeFalse();
});

test('organization creation makes no Stripe API request and succeeds when Stripe is unreachable', function () {
    Config::set('billing.trial_days', 14);
    Config::set('billing.stripe.secret', 'sk_test_unreachable');

    Http::fake(function () {
        throw new RuntimeException('Stripe must never be contacted during organization creation.');
    });

    $organization = app(CreateOrganization::class)->handle(User::factory()->create(), 'Offline Restaurant');

    expect($organization->exists)->toBeTrue()
        ->and($organization->trial_ends_at)->not->toBeNull();

    Http::assertNothingSent();
});

test('existing organizations are not modified when the trial rollout runs', function () {
    $existing = Organization::factory()->create(['trial_ends_at' => null]);

    Config::set('billing.trial_days', 14);

    app(CreateOrganization::class)->handle(User::factory()->create(), 'New Restaurant');

    expect($existing->refresh()->trial_ends_at)->toBeNull();
});
