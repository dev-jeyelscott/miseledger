<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

function marketingLandingPageFixturePlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['recipes'],
            'limits' => ['locations' => 3],
        ],
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'monthly' => 'price_growth_monthly',
                'yearly' => 'price_growth_yearly',
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);
}

test('an unauthenticated visitor sees the landing page with only approved trial and plan claims', function () {
    marketingLandingPageFixturePlans();
    Config::set('billing.trial_days', 14);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('welcome')
            ->where('trialDays', 14)
            ->where('plans', [
                ['code' => 'starter', 'name' => 'Starter'],
                ['code' => 'growth', 'name' => 'Growth'],
            ]),
    );

    $props = json_encode($response->viewData('page')['props']);

    expect($props)
        ->not->toContain('price_starter_monthly')
        ->not->toContain('price_growth_monthly')
        ->not->toContain('price_growth_yearly')
        ->not->toContain('sk_')
        ->not->toContain('pk_');
});

test('the landing page never fabricates a trial length when none is configured', function () {
    Config::set('billing.plans', []);
    Config::set('billing.trial_days', null);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('welcome')
            ->where('trialDays', null)
            ->where('plans', []),
    );
});

test('an unauthenticated visitor is served the auth-aware contract needed to route them to register or log in', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null),
    );

    expect(route('register'))->toEndWith('/register');
    expect(route('login'))->toEndWith('/login');

    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
});

test('an authenticated visitor is served the auth-aware contract needed to route them to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user.id', $user->id),
    );

    expect(route('dashboard'))->toEndWith('/dashboard');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
