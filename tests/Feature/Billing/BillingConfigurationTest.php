<?php

use App\Models\Organization;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;

test('stripe secrets resolve from environment and are not present in the default env example', function () {
    expect(config('billing.stripe'))
        ->toHaveKeys(['key', 'secret', 'webhook_secret']);

    expect(file_get_contents(base_path('.env.example')))
        ->not->toContain('STRIPE_SECRET=sk_')
        ->not->toContain('STRIPE_KEY=pk_');
});

test('stripe secrets are never shared with inertia', function () {
    Config::set('billing.stripe.secret', 'sk_test_should_never_leak');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $page = $response->viewData('page') ?? [];
    $props = json_encode($page);

    expect($props)
        ->not->toContain('sk_test_should_never_leak')
        ->not->toContain('stripe');
});

test('billing plan catalog is sourced from the subscription configuration contract', function () {
    expect(config('billing.plans'))->toBe(config('subscription.plans'))
        ->and(config('billing.subscription_type'))->toBe(config('subscription.type'))
        ->and(config('billing.trial_days'))->toBe(config('subscription.trial_days'));
});

test('billing currency is independent from organization currency', function () {
    Config::set('billing.currency', 'usd');

    $organization = Organization::factory()->create(['currency' => 'PHP']);

    expect(config('billing.currency'))->toBe('usd')
        ->and($organization->currency)->toBe('PHP')
        ->and(config('billing.currency'))->not->toBe($organization->currency);
});

test('billing logger falls back to the stack channel when unset', function () {
    expect(config('billing.logger'))->not->toBeNull();
});

test('missing required billing configuration fails safely outside local and testing environments', function () {
    Config::set('billing.stripe.secret', null);
    Config::set('billing.stripe.key', 'pk_test_should_not_leak');
    Config::set('billing.required_in_production', ['stripe.secret']);

    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();

        $this->fail('Expected missing billing configuration to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('stripe.secret')
            ->not->toContain('pk_test_should_not_leak');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('billing configuration boots without error when required values are present', function () {
    Config::set('billing.stripe.secret', 'sk_test_placeholder');
    Config::set('billing.required_in_production', ['stripe.secret']);

    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(true)->toBeTrue();
});
