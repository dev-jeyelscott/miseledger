<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('public home renders the MiseLedger landing page', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page->component('welcome'),
        );
});

test('landing page keeps customer-facing copy and authentication-aware routes', function () {
    $source = File::get(resource_path('js/pages/welcome.tsx'));
    $normalizedSource = Str::squish($source);

    expect($normalizedSource)
        ->toContain('Know what you have before you buy more.')
        ->toContain('From delivery to plate')
        ->toContain('Keep every location on the same page')
        ->toContain('dashboard()')
        ->toContain('login()')
        ->toContain('register()')
        ->toContain('Boolean(auth.user)')
        ->not->toContain("Let's get started")
        ->not->toContain('Laracasts')
        ->not->toContain('Deploy now')
        ->not->toContain('No long forms. Get started in minutes.')
        ->not->toContain('Expiry tracking')
        ->not->toContain('real-time updates');
});
