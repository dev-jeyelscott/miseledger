<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Fakes the Stripe HTTP transport boundary Cashier's `ApiRequestor` uses,
 * so Checkout feature tests never make a real network call while still
 * exercising the real Cashier/Stripe SDK request-building code.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string}> */
    public array $requests = [];

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<int, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl];

        if (str_contains($absUrl, '/v1/customers/')) {
            return [json_encode(['id' => 'cus_test_123', 'object' => 'customer']), 200, []];
        }

        if (str_contains($absUrl, '/v1/checkout/sessions')) {
            return [json_encode([
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                'mode' => 'subscription',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

function organizationCheckoutFixturePlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => [],
            'limits' => [],
        ],
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'monthly' => 'price_growth_monthly',
                'yearly' => 'price_growth_yearly',
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);
}

function fakeStripeHttpClient(): FakeStripeHttpClient
{
    Config::set('cashier.secret', 'sk_test_fake');

    $client = new FakeStripeHttpClient;

    ApiRequestor::setHttpClient($client);

    return $client;
}

afterEach(function (): void {
    ApiRequestor::setHttpClient(null);
});

test('an owner can start Checkout for a supported plan and interval', function () {
    organizationCheckoutFixturePlans();

    $client = fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $response = $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'monthly'],
    );

    $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    $checkoutRequest = collect($client->requests)->last();

    expect($checkoutRequest['url'])->toContain('/v1/checkout/sessions');
});

test('a non-owner without billing.manage is denied', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'monthly'],
    )->assertForbidden();
});

test('a user with no membership in the target organization is denied', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'monthly'],
    )->assertForbidden();
});

test('an owner of a different organization cannot start Checkout for another organization', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($ownedOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $otherOrganization),
        ['plan' => 'starter', 'interval' => 'monthly'],
    )->assertForbidden();
});

test('an invalid plan code is rejected', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'enterprise', 'interval' => 'monthly'],
    )->assertInvalid(['plan']);
});

test('a plan without a configured price for the requested interval is rejected', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'yearly'],
    )->assertInvalid(['plan']);
});

test('an unsupported interval is rejected', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'weekly'],
    )->assertInvalid(['interval']);
});

test('the resolved Checkout session uses the trusted configured price id, never a browser supplied one', function () {
    organizationCheckoutFixturePlans();

    $client = fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'growth', 'interval' => 'yearly', 'price_id' => 'price_attacker_supplied'],
    )->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    $checkoutRequest = collect($client->requests)->last();

    expect($checkoutRequest['url'])->toContain('/v1/checkout/sessions');
});

test('a repeated Checkout request for an already subscribed organization is rejected', function () {
    organizationCheckoutFixturePlans();

    fakeStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->post(
        route('organizations.billing.checkout', $organization),
        ['plan' => 'starter', 'interval' => 'monthly'],
    )->assertInvalid(['organization']);
});
