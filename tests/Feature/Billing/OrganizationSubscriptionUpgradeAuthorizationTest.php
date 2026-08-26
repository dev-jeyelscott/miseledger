<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 30_000, 'yearly' => null]);
    Config::set('billing.plans.growth.manual_amounts', ['monthly' => 60_000, 'yearly' => null]);
    Config::set('billing.providers.paymongo.manual_qrph', true);
    Config::set('billing.providers.paymongo.mode', 'test');
    Config::set('billing.providers.paymongo.api_base_url', 'https://api.paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_never_leak');
    Http::preventStrayRequests();
});

function fakeUpgradeQrPhCheckout(string $paymentIntentId = 'pi_upgrade_test'): void
{
    Http::fake([
        'api.paymongo.test/v1/payment_intents' => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'amount' => 10_000, 'currency' => 'PHP', 'livemode' => false,
            ]],
        ]),
        'api.paymongo.test/v1/payment_methods' => Http::response([
            'data' => ['id' => 'pm_qrph_test', 'type' => 'payment_method', 'attributes' => []],
        ]),
        "api.paymongo.test/v1/payment_intents/{$paymentIntentId}/attach" => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'next_action' => ['code' => [
                    'image_url' => "https://paymongo.test/{$paymentIntentId}.png",
                    'expires_at' => now()->addMinutes(30)->timestamp,
                ]],
            ]],
        ]),
    ]);
}

function upgradeSubscriptionForOrganization(Organization $organization, array $attributes = []): BillingSubscription
{
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create(array_merge([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'current_period_ends_at' => now()->addDays(10),
    ], $attributes));
}

test('a member without billing access cannot request a plan upgrade', function (): void {
    $organization = Organization::factory()->create();
    upgradeSubscriptionForOrganization($organization);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)
        ->postJson(route('organizations.billing.upgrade', $organization), ['plan' => 'growth'])
        ->assertForbidden();
});

test('an owner cannot request a plan upgrade for another organization', function (): void {
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    upgradeSubscriptionForOrganization($otherOrganization);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($ownedOrganization)->for($user)->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->postJson(route('organizations.billing.upgrade', $otherOrganization), ['plan' => 'growth'])
        ->assertForbidden();
});

test('an unauthenticated request cannot reach the upgrade endpoint', function (): void {
    $organization = Organization::factory()->create();
    upgradeSubscriptionForOrganization($organization);

    $this->post(route('organizations.billing.upgrade', $organization), ['plan' => 'growth'])
        ->assertRedirect(route('login'));
});

test('only the internal plan code field is accepted -- forged interval, amount, or external plan id are ignored', function (): void {
    $organization = Organization::factory()->create();
    $subscription = upgradeSubscriptionForOrganization($organization);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);
    fakeUpgradeQrPhCheckout();

    $this->actingAs($user)->postJson(route('organizations.billing.upgrade', $organization), [
        'plan' => 'growth',
        'interval' => 'yearly',
        'amount' => 1,
        'external_plan_id' => 'plan_forged',
        'currency' => 'USD',
    ])->assertOk();

    expect($subscription->fresh()->interval)->toBe('monthly')
        ->and($subscription->invoices()->sole()->amount)->toBe(10_000)
        ->and($subscription->invoices()->sole()->currency)->toBe('PHP');
});

test('an invalid plan code is rejected', function (): void {
    $organization = Organization::factory()->create();
    upgradeSubscriptionForOrganization($organization);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->postJson(route('organizations.billing.upgrade', $organization), ['plan' => 'Not A Valid Code!'])
        ->assertStatus(422);
});
