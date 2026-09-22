<?php

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Enums\OrganizationRole;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

test('guests are redirected from the platform alerts page to login', function () {
    $this->get(route('admin.alerts.index'))
        ->assertRedirect(route('login'));
});

test('normal authenticated users cannot access the platform alerts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.alerts.index'))
        ->assertForbidden();
});

test('organization owners cannot access the platform alerts page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)
        ->get(route('admin.alerts.index'))
        ->assertForbidden();
});

test('platform admins without an approved strong factor are routed to security settings from the platform alerts page', function () {
    $user = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $user->getKey()]);

    $this->actingAs($user)
        ->get(route('admin.alerts.index'))
        ->assertRedirect(route('security.edit'));
});

test('platform admins can view the bounded alerts index with only safe list fields', function () {
    $user = User::factory()->withTwoFactor()->create();
    PlatformAdmin::query()->create(['user_id' => $user->getKey()]);

    app(RecordPlatformAlertObservation::class)->handle(
        fingerprint: 'console.test.fingerprint',
        type: PlatformAlertType::DatabaseHealth,
        source: 'test-source',
        severity: PlatformAlertSeverity::Critical,
        title: 'Console visible alert',
        summary: 'Safe summary text',
        context: ['activeConnections' => 5],
    );

    $this->actingAs($user)
        ->get(route('admin.alerts.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/alerts/index')
                ->has('alerts', 1)
                ->where('alerts.0.title', 'Console visible alert')
                ->where('alerts.0.severity', 'critical')
                ->where('alerts.0.state', 'open')
                ->missing('alerts.0.summary')
                ->missing('alerts.0.context'),
        );
});

test('the alerts index filters by state and severity', function () {
    $user = User::factory()->withTwoFactor()->create();
    PlatformAdmin::query()->create(['user_id' => $user->getKey()]);

    $record = app(RecordPlatformAlertObservation::class);

    $record->handle(
        fingerprint: 'filter.critical',
        type: PlatformAlertType::DatabaseHealth,
        source: 'test-source',
        severity: PlatformAlertSeverity::Critical,
        title: 'Critical alert',
        summary: 'Summary',
        context: [],
    );

    $record->handle(
        fingerprint: 'filter.warning',
        type: PlatformAlertType::QueueBacklog,
        source: 'test-source',
        severity: PlatformAlertSeverity::Warning,
        title: 'Warning alert',
        summary: 'Summary',
        context: [],
    );

    $this->actingAs($user)
        ->get(route('admin.alerts.index', ['severity' => 'critical']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('alerts', 1)
                ->where('alerts.0.title', 'Critical alert'),
        );
});

test('an alert detail page exposes safe context and a deep link but no mutation controls', function () {
    $user = User::factory()->withTwoFactor()->create();
    PlatformAdmin::query()->create(['user_id' => $user->getKey()]);

    $alert = app(RecordPlatformAlertObservation::class)->handle(
        fingerprint: 'detail.test.fingerprint',
        type: PlatformAlertType::DatabaseHealth,
        source: 'test-source',
        severity: PlatformAlertSeverity::Warning,
        title: 'Detail test alert',
        summary: 'Detail summary',
        context: ['activeConnections' => 12],
    );

    $this->actingAs($user)
        ->get(route('admin.alerts.show', $alert))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/alerts/show')
                ->where('alert.summary', 'Detail summary')
                ->where('alert.context.activeConnections', 12)
                ->where('alert.targetUrl', route('admin.health.index')),
        );
});

test('the platform alerts console never renders a mutation control', function () {
    $show = (string) file_get_contents(
        resource_path('js/pages/admin/alerts/show.tsx'),
    );
    $index = (string) file_get_contents(
        resource_path('js/pages/admin/alerts/index.tsx'),
    );

    foreach ([$show, $index] as $source) {
        expect($source)
            ->not->toContain('method="post"')
            ->not->toContain("method: 'post'")
            ->not->toContain('router.post')
            ->not->toContain('router.delete')
            ->not->toContain('router.put')
            ->not->toContain('router.patch');
    }
});
