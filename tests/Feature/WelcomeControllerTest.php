<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the public landing page renders with server-derived trial days and safe plan data', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('trialDays', config('billing.trial_days') !== null
                ? (int) config('billing.trial_days')
                : null)
            ->has('plans')
        );
});

test('every serialized plan exposes only code, name, features, and limits', function () {
    $this->get('/')
        ->assertInertia(function (Assert $page) {
            $page->has('plans', function (Assert $plans) {
                $plans->each(fn (Assert $plan) => $plan
                    ->hasAll(['code', 'name', 'features', 'limits'])
                    ->etc()
                );
            });
        });
});

test('an explicit unlimited plan limit is serialized as null rather than omitted', function () {
    $response = $this->get('/');

    $plans = collect($response->viewData('page')['props']['plans']);

    $unlimitedPlan = $plans->first(
        fn (array $plan) => in_array(null, $plan['limits'], true),
    );

    expect($unlimitedPlan)->not->toBeNull();
});

test('no provider identifiers, provider configuration, or secrets are exposed to the public landing page', function () {
    $response = $this->get('/');

    $payload = json_encode($response->viewData('page')['props']);

    expect($payload)
        ->not->toContain('stripe')
        ->not->toContain('paymongo')
        ->not->toContain('price_')
        ->not->toContain('secret')
        ->not->toContain('webhook');
});
