<?php

use App\Enums\OrganizationRole;
use App\Enums\PlatformAdminAuditAction;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\PlatformAdminAuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the platform console to login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('unverified platform admins are redirected to email verification', function () {
    $user = User::factory()->unverified()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('normal authenticated users cannot access the platform console', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('organization owners and tenant commercial state do not confer platform authority', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    expect($organization->active)->toBeTrue()
        ->and($user->isPlatformAdmin())->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('explicitly granted platform admins can access the platform console', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('auth.isPlatformAdmin', true),
        );
});

test('platform admins with zero organization memberships can access the platform console', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->organizationMemberships()->exists())->toBeFalse();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('auth.isPlatformAdmin', true)
                ->where('organizationContext.active', null)
                ->where('organizationContext.memberships', []),
        );
});

test('platform admins without an approved strong factor are routed to security settings', function () {
    $user = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    expect($user->hasApprovedStrongFactor())->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('platform admins with an unconfirmed two-factor secret are routed to security settings', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => null,
    ]);

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('platform admins with a registered passkey can access the platform console without two-factor', function () {
    $user = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $user->passkeys()->create([
        'name' => 'Security key',
        'credential_id' => 'test-credential-id',
        'credential' => ['aaguid' => null],
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('revoked platform grant still applies even with an approved strong factor', function () {
    $user = User::factory()->withTwoFactor()->create();

    $grant = PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $grant->delete();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('user platform capability is derived only from the explicit platform grant', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    expect($user->isPlatformAdmin())->toBeFalse();

    $grant = PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    expect($user->isPlatformAdmin())->toBeTrue();

    $grant->delete();

    expect($user->isPlatformAdmin())->toBeFalse();
});

test('shared inertia auth exposes only the safe platform capability boolean', function () {
    $normalUser = User::factory()->create();

    $this->actingAs($normalUser)
        ->get(route('user-guide.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.isPlatformAdmin', false)
                ->missing('auth.platformAdmin')
                ->missing('auth.platformAdminId'),
        );

    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $this->actingAs($platformUser)
        ->get(route('user-guide.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.isPlatformAdmin', true)
                ->missing('auth.platformAdmin')
                ->missing('auth.platformAdminId'),
        );
});

test('database enforces one platform administrator grant per user without organization scope', function () {
    $user = User::factory()->create();

    expect(Schema::hasColumn('platform_admins', 'organization_id'))
        ->toBeFalse();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    expect(
        fn () => DB::transaction(
            fn () => PlatformAdmin::query()->create([
                'user_id' => $user->getKey(),
            ]),
        ),
    )->toThrow(QueryException::class);

    expect(PlatformAdmin::query()->count())->toBe(1);
});

test('grant and revoke commands reject unknown users without creating identities', function () {
    $email = 'missing-platform-admin@example.com';

    $this->artisan('platform-admin:grant', ['email' => $email, '--reason' => 'Onboarding'])
        ->assertExitCode(Command::FAILURE);

    $this->artisan('platform-admin:revoke', ['email' => $email, '--reason' => 'Offboarding'])
        ->assertExitCode(Command::FAILURE);

    expect(
        User::query()->where('email', $email)->exists(),
    )->toBeFalse()
        ->and(PlatformAdmin::query()->count())->toBe(0)
        ->and(PlatformAdminAuditEvent::query()->count())->toBe(0);
});

test('grant and revoke commands reject a blank reason', function () {
    $user = User::factory()->create();

    $this->artisan('platform-admin:grant', ['email' => $user->email, '--reason' => '   '])
        ->assertExitCode(Command::FAILURE);

    $this->artisan('platform-admin:revoke', ['email' => $user->email, '--reason' => ''])
        ->assertExitCode(Command::FAILURE);

    expect(PlatformAdmin::query()->count())->toBe(0)
        ->and(PlatformAdminAuditEvent::query()->count())->toBe(0);
});

test('grant and revoke commands are idempotent and preserve identity and membership data', function () {
    $user = User::factory()->withTwoFactor()->create();
    $organization = Organization::factory()->create();

    $membership = OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $identityBefore = [
        'password' => $user->getRawOriginal('password'),
        'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
        'two_factor_recovery_codes' => $user->getRawOriginal(
            'two_factor_recovery_codes',
        ),
        'two_factor_confirmed_at' => $user->getRawOriginal(
            'two_factor_confirmed_at',
        ),
        'updated_at' => $user->getRawOriginal('updated_at'),
    ];

    $this->artisan('platform-admin:grant', ['email' => $user->email, '--reason' => 'Incident response lead'])
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('platform-admin:grant', ['email' => $user->email, '--reason' => 'Incident response lead'])
        ->assertExitCode(Command::SUCCESS);

    expect(
        PlatformAdmin::query()
            ->where('user_id', $user->getKey())
            ->count(),
    )->toBe(1)
        ->and(
            PlatformAdminAuditEvent::query()
                ->where('user_id', $user->getKey())
                ->count(),
        )->toBe(1);

    $this->artisan('platform-admin:revoke', ['email' => $user->email, '--reason' => 'Incident resolved'])
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('platform-admin:revoke', ['email' => $user->email, '--reason' => 'Incident resolved'])
        ->assertExitCode(Command::SUCCESS);

    expect(
        PlatformAdmin::query()
            ->where('user_id', $user->getKey())
            ->count(),
    )->toBe(0);

    $history = PlatformAdminAuditEvent::query()
        ->where('user_id', $user->getKey())
        ->orderBy('created_at')
        ->get();

    expect($history)->toHaveCount(2)
        ->and($history->first()->action)->toBe(PlatformAdminAuditAction::Grant)
        ->and($history->first()->reason)->toBe('Incident response lead')
        ->and($history->first()->source)->toBe('platform-admin:grant')
        ->and($history->last()->action)->toBe(PlatformAdminAuditAction::Revoke)
        ->and($history->last()->reason)->toBe('Incident resolved')
        ->and($history->last()->source)->toBe('platform-admin:revoke')
        ->and($history->last()->target_email)->toBe($user->email);

    $user->refresh();

    expect([
        'password' => $user->getRawOriginal('password'),
        'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
        'two_factor_recovery_codes' => $user->getRawOriginal(
            'two_factor_recovery_codes',
        ),
        'two_factor_confirmed_at' => $user->getRawOriginal(
            'two_factor_confirmed_at',
        ),
        'updated_at' => $user->getRawOriginal('updated_at'),
    ])->toBe($identityBefore)
        ->and(
            $user->organizationMemberships()
                ->whereKey($membership->getKey())
                ->exists(),
        )->toBeTrue();
});

test('platform user menu uses the generated route and server capability', function () {
    $menu = (string) file_get_contents(
        resource_path('js/components/user-menu-content.tsx'),
    );

    expect($menu)
        ->toContain('auth.isPlatformAdmin')
        ->toContain('Platform Console')
        ->toContain("from '@/routes/admin'")
        ->toContain('href={platformDashboard()}')
        ->not->toContain('href="/admin"');
});

test('platform layout excludes tenant-specific application chrome', function () {
    $layout = (string) file_get_contents(
        resource_path('js/layouts/platform-layout.tsx'),
    );

    $app = (string) file_get_contents(
        resource_path('js/app.tsx'),
    );

    expect($layout)
        ->not->toContain('OrganizationSwitcher')
        ->not->toContain('SubscriptionNotice')
        ->not->toContain('AiAssistantDrawer')
        ->not->toContain('organizationContext')
        ->not->toContain('entitlements')
        ->and($app)
        ->toContain("case name.startsWith('admin/'):")
        ->toContain('return PlatformLayout;');
});
