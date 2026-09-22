<?php

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * POC-V10.5: re-audit every Platform Console prop surface. Sensitive values
 * must be absent from the server payload itself, not merely hidden in the
 * UI. These are stable negative assertions run against the full rendered
 * Inertia payload for each platform page.
 */
function platformSensitiveMarkers(): array
{
    return [
        'two_factor_secret',
        'twoFactorSecret',
        'two_factor_recovery_codes',
        'twoFactorRecoveryCodes',
        '"password"',
        'remember_token',
        'rememberToken',
        'credential_id',
        'credentialId',
        '"credential"',
        'webhook_secret',
        'webhookSecret',
        'api_key',
        'apiKey',
        'client_secret',
        'clientSecret',
        'stripe_secret',
        'paymongo_secret',
        'DB_PASSWORD',
        'REDIS_PASSWORD',
        'postgres://',
        'redis://',
    ];
}

function assertPlatformPageOmitsSecrets(Assert $page): void
{
    $json = json_encode($page->toArray());

    foreach (platformSensitiveMarkers() as $marker) {
        expect($json)->not->toContain($marker);
    }
}

test('platform console read-only pages never expose sensitive server payload values', function () {
    Queue::fake();

    $platformUser = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create(['user_id' => $platformUser->getKey()]);

    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => User::factory()->create()->getKey(),
    ]);

    $customer = BillingCustomer::factory()->create([
        'organization_id' => $organization->getKey(),
    ]);

    BillingSubscription::factory()->create([
        'billing_customer_id' => $customer->id,
        'organization_id' => $customer->organization_id,
        'provider' => $customer->provider,
    ]);

    $alert = app(RecordPlatformAlertObservation::class)->handle(
        fingerprint: 'privacy.test.fingerprint',
        type: PlatformAlertType::DatabaseHealth,
        source: 'test',
        severity: PlatformAlertSeverity::Warning,
        title: 'Test alert',
        summary: 'Test alert summary.',
        context: [],
    );

    $this->actingAs($platformUser);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.users.show', $platformUser))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.billing.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.billing.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.observability.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.health.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.alerts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.alerts.show', $alert))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));

    $this->get(route('admin.product-catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertPlatformPageOmitsSecrets($page));
});

test('platform error pages do not confirm cross-boundary record existence unnecessarily', function () {
    $platformUser = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create(['user_id' => $platformUser->getKey()]);

    $this->actingAs($platformUser)
        ->get(route('admin.users.show', 999999))
        ->assertNotFound();

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.show', 999999))
        ->assertNotFound();

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.show', 'not_a_real_plan'))
        ->assertNotFound();
});
