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

    // Verify Settings, User Guide, Release Notes order with separator
    $settingsPos = strpos($menu, 'Settings');
    $userGuidePos = strpos($menu, 'User Guide');
    $releaseNotesPos = strpos($menu, 'Release Notes');
    $separatorPos = strpos($menu, 'DropdownMenu.Separator', $releaseNotesPos);

    expect($settingsPos)->toBeLessThan($userGuidePos);
    expect($userGuidePos)->toBeLessThan($releaseNotesPos);
    expect($releaseNotesPos)->toBeLessThan($separatorPos);
});
