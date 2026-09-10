<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function (): void {
    $this
        ->get(route('user-guide.index'))
        ->assertRedirect(route('login'));
});

test('unverified users are redirected to email verification', function (): void {
    $user = User::factory()->unverified()->create();

    $this
        ->actingAs($user)
        ->get(route('user-guide.index'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users can view the user guide index', function (): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('user-guide.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('user-guide/index'));
});

test('verified users can view each user guide module', function (string $module): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('user-guide.show', $module))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('user-guide/show')
            ->where('module', $module));
})->with([
    'getting started' => 'getting-started',
    'dashboard' => 'dashboard',
    'AI Assistant' => 'ai-assistant',
    'inventory' => 'inventory',
    'stock counts' => 'stock-counts',
    'waste' => 'waste',
    'stock transfers' => 'stock-transfers',
    'purchasing' => 'purchasing',
    'recipes' => 'recipes',
    'reports' => 'reports',
    'organization' => 'organization',
    'billing' => 'billing',
    'settings' => 'settings',
]);

test('unknown user guide modules return not found', function (): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('user-guide.show', 'unknown-module'))
        ->assertNotFound();
});

test('the user menu exposes the user guide in shared desktop and mobile navigation', function (): void {
    $menu = File::get(resource_path('js/components/user-menu-content.tsx'));
    $navigation = File::get(resource_path('js/components/nav-user.tsx'));

    expect($menu)
        ->toContain("import { index as userGuideIndex } from '@/routes/user-guide';")
        ->toContain('<BookOpen className="mr-2" />')
        ->toContain('href={userGuideIndex()}')
        ->toContain('User Guide');

    expect($navigation)
        ->toContain('<UserMenuContent user={auth.user} />')
        ->toContain('isMobile');
});

test('every sidebar navigation destination is assigned to a user guide module', function (): void {
    $sidebar = File::get(resource_path('js/components/app-sidebar.tsx'));
    $coverage = File::get(resource_path('js/user-guide/coverage.ts'));

    preg_match_all("/title:\s*'([^']+)'/", $sidebar, $matches);
    $sidebarLabels = array_values(array_diff(array_unique($matches[1]), [
        'Inventory',
        'Organization',
        'Purchasing',
        'Reports',
    ]));

    expect($sidebarLabels)->not->toBeEmpty();

    preg_match_all("/^\\s+(?:'([^']+)'|([A-Za-z]+)):/m", $coverage, $matches);
    $documentedLabels = array_values(array_filter([
        ...$matches[1],
        ...$matches[2],
    ]));

    sort($sidebarLabels);
    sort($documentedLabels);

    expect($documentedLabels)->toBe($sidebarLabels);
});

test('user guide home exposes release notes navigation action', function (): void {
    $page = File::get(resource_path('js/pages/user-guide/index.tsx'));

    expect($page)
        ->toContain("import { index as releaseNotesIndex } from '@/routes/release-notes';")
        ->toContain('Newspaper')
        ->toContain('actions={')
        ->toContain('Button')
        ->toContain('href={releaseNotesIndex()}')
        ->toContain('Release Notes');
});
