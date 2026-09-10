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

    // Extract the DropdownMenuGroup block to avoid matching imports or other text
    preg_match(
        '/<DropdownMenuGroup>.*?<\/DropdownMenuGroup>/s',
        $menu,
        $matches,
    );
    expect($matches)->toHaveCount(1);
    $menuGroup = $matches[0];

    // Verify ordered menu items: Settings, User Guide, Release Notes
    $settingsPos = strpos($menuGroup, '<Settings className="mr-2" />');
    $userGuidePos = strpos($menuGroup, '<BookOpen className="mr-2" />');
    $releaseNotesPos = strpos($menuGroup, '<Newspaper className="mr-2" />');

    expect($settingsPos)->toBeGreaterThan(0)->toBeLessThan($userGuidePos);
    expect($userGuidePos)->toBeGreaterThan(0)->toBeLessThan($releaseNotesPos);
    expect($releaseNotesPos)->toBeGreaterThan(0);

    // Verify separator after Release Notes before Log out
    $afterGroupMatch = preg_match(
        '/<\/DropdownMenuGroup>\s*<DropdownMenuSeparator/s',
        $menu,
    );
    expect($afterGroupMatch)->toBe(1);
});

test('release notes page exposes user guide navigation action', function (): void {
    $page = File::get(resource_path('js/pages/release-notes/index.tsx'));

    expect($page)
        ->toContain("import { show } from '@/routes/user-guide';")
        ->toContain("import { BookOpen } from 'lucide-react';")
        ->toContain('actions={')
        ->toContain('Button')
        ->toContain('href={show(')
        ->toContain('Open User Guide');
});

test('release notes page renders related guide links when present', function (): void {
    $page = File::get(resource_path('js/pages/release-notes/index.tsx'));

    expect($page)
        ->toContain('relatedGuideSlugs')
        ->toContain('guideModulesBySlug')
        ->toContain('Read the')
        ->toContain('guide')
        ->toContain('variant="outline"');
});

test('release notes page safely handles missing guide modules', function (): void {
    $page = File::get(resource_path('js/pages/release-notes/index.tsx'));

    expect($page)
        ->toContain('if (!module)')
        ->toContain('return null');
});
