<?php

use App\Actions\Platform\CaptureDatabaseTableSizeSnapshots;
use App\Enums\OrganizationRole;
use App\Models\DatabaseTableSizeSnapshot;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the platform health page to login', function () {
    $this->get(route('admin.health.index'))
        ->assertRedirect(route('login'));
});

test('normal authenticated users cannot access the platform health page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertForbidden();
});

test('organization owners cannot access the platform health page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertForbidden();
});

test('platform admins without an approved strong factor are routed to security settings from the platform health page', function () {
    $user = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertRedirect(route('security.edit'));
});

test('platform admins can access the platform health page and see bounded read-only evidence', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/health')
                ->has('database.status')
                ->has('database.source')
                ->has('migrations.status')
                ->has('slowQueries.status')
                ->has('tableGrowth.status')
                ->has('backup.status')
                ->where('backup.lastVerifiedRestore', null)
                ->has('redis.status')
                ->has('queues.status'),
        );
});

test('the database health probe reports connectivity, version, size, and connection capacity', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('database.status', 'healthy')
                ->whereType('database.serverVersion', 'string')
                ->whereType('database.databaseSizeBytes', 'integer')
                ->whereType('database.activeConnections', 'integer')
                ->whereType('database.maxConnections', 'integer'),
        );
});

test('the migration health probe reports no pending migrations on a fully migrated database', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('migrations.status', 'healthy')
                ->where('migrations.pendingCount', 0),
        );
});

test('the backup readiness evidence never fabricates a last verified restore value', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('backup.checkedAt')
                ->where('backup.verificationSource', 'external')
                ->where('backup.lastVerifiedRestore', null)
                ->where(
                    'backup.workflowPath',
                    '.github/workflows/billing-restore-readiness.yml',
                ),
        );
});

test('the table growth probe reports no growth history yet on the first captured day', function () {
    app(CaptureDatabaseTableSizeSnapshots::class)->handle();

    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.health.index'))
        ->assertInertia(
            fn (Assert $page) => $page->where('tableGrowth.hasHistory', false),
        );
});

test('capturing table size snapshots is idempotent for the same capture date', function () {
    $first = app(CaptureDatabaseTableSizeSnapshots::class)->handle();
    $second = app(CaptureDatabaseTableSizeSnapshots::class)->handle();

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe($first)
        ->and(DatabaseTableSizeSnapshot::query()->count())->toBe($first);
});

test('capturing table size snapshots never persists a system schema', function () {
    app(CaptureDatabaseTableSizeSnapshots::class)->handle();

    expect(
        DatabaseTableSizeSnapshot::query()
            ->whereIn('schema_name', ['pg_catalog', 'information_schema', 'pg_toast'])
            ->exists(),
    )->toBeFalse();
});

test('the platform navigation links to the health page', function () {
    $layout = (string) file_get_contents(
        resource_path('js/layouts/platform-layout.tsx'),
    );

    expect($layout)
        ->toContain('Health')
        ->toContain('PlatformHealthController');
});
