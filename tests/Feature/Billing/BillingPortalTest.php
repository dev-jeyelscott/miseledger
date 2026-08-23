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
 * so Billing Portal feature tests never make a real network call while
 * still exercising the real Cashier/Stripe SDK request-building code.
 */
final class FakeBillingPortalStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<int, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        if (str_contains($absUrl, '/v1/billing_portal/sessions')) {
            return [json_encode([
                'id' => 'bps_test_123',
                'object' => 'billing_portal.session',
                'url' => 'https://billing.stripe.com/p/session/bps_test_123',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

function fakeBillingPortalStripeHttpClient(): FakeBillingPortalStripeHttpClient
{
    Config::set('cashier.secret', 'sk_test_fake');

    $client = new FakeBillingPortalStripeHttpClient;

    ApiRequestor::setHttpClient($client);

    return $client;
}

afterEach(function (): void {
    ApiRequestor::setHttpClient(null);
});

test('a billing manager can create a billing portal session for their organization', function () {
    $client = fakeBillingPortalStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $response = $this->actingAs($user)->post(
        route('organizations.billing.portal', $organization),
    );

    $response->assertRedirect('https://billing.stripe.com/p/session/bps_test_123');

    $portalRequest = collect($client->requests)
        ->last(fn (array $request): bool => str_contains($request['url'], '/v1/billing_portal/sessions'));

    expect($portalRequest['params']['customer'])->toBe('cus_test_123')
        ->and($portalRequest['params']['return_url'])->toBe(
            route('organizations.billing.show', $organization),
        );
});

test('a non-owner without billing.manage is denied a billing portal session', function () {
    fakeBillingPortalStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)->post(
        route('organizations.billing.portal', $organization),
    )->assertForbidden();
});

test('a user with no membership in the target organization is denied a billing portal session', function () {
    fakeBillingPortalStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    $this->actingAs($user)->post(
        route('organizations.billing.portal', $organization),
    )->assertForbidden();
});

test('an owner of a different organization cannot open the billing portal for another organization', function () {
    fakeBillingPortalStripeHttpClient();

    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($ownedOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.portal', $otherOrganization),
    )->assertForbidden();
});

test('a guest is redirected to login when requesting a billing portal session', function () {
    fakeBillingPortalStripeHttpClient();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    $this->post(
        route('organizations.billing.portal', $organization),
    )->assertRedirect(route('login'));
});

test('an organization without a Stripe customer cannot create a billing portal session', function () {
    fakeBillingPortalStripeHttpClient();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => null]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(
        route('organizations.billing.portal', $organization),
    )->assertStatus(500);
});
