<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function (): void {
    $this
        ->get(route('release-notes.index'))
        ->assertRedirect(route('login'));
});

test('unverified users are redirected to email verification', function (): void {
    $user = User::factory()->unverified()->create();

    $this
        ->actingAs($user)
        ->get(route('release-notes.index'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users can view the release notes page', function (): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('release-notes.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('release-notes/index'));
});

test('the account menu exposes release notes in shared navigation', function (): void {
    $menu = File::get(resource_path('js/components/user-menu-content.tsx'));

    expect($menu)
        ->toContain("import { index as releaseNotesIndex } from '@/routes/release-notes';")
        ->toContain('<Newspaper className="mr-2" />')
        ->toContain('href={releaseNotesIndex()}')
        ->toContain('Release Notes');
});
