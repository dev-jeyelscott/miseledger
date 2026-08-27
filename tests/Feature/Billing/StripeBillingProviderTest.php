<?php

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\Providers\StripeBillingProvider;
use Illuminate\Support\Facades\Config;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Fake Stripe's HTTP transport while exercising the real Cashier/Stripe SDK.
 */
final class FakeStripeBillingProviderHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /**
     * Return deterministic provider fixtures for Stripe adapter tests.
     *
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<int, string>}
     */
    public function request(
        $method,
        $absUrl,
        $headers,
        $params,
        $hasFile,
        $apiMode = 'v1',
        $maxNetworkRetries = null,
    ) {
        $this->requests[] = [
            'method' => $method,
            'url' => $absUrl,
            'params' => $params,
        ];

        if (str_contains(
            $absUrl,
            '/v1/subscriptions/sub_provider_test_123',
        )) {
            return [
                json_encode([
                    'id' => 'sub_provider_test_123',
                    'object' => 'subscription',
                    'customer' => 'cus_provider_test_123',
                    'status' => 'active',
                    'livemode' => false,
                    'cancel_at_period_end' => false,
                    'cancel_at' => null,
                    'trial_end' => null,
                    'canceled_at' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_provider_test_123',
                                'object' => 'subscription_item',
                                'current_period_start' => 1_787_040_000,
                                'current_period_end' => 1_789_718_400,
                                'price' => [
                                    'id' => 'price_provider_test',
                                    'object' => 'price',
                                ],
                            ],
                        ],
                        'has_more' => false,
                        'url' => '/v1/subscription_items?subscription=sub_provider_test_123',
                    ],
                ], JSON_THROW_ON_ERROR),
                200,
                [],
            ];
        }

        if (str_contains($absUrl, '/v1/customers/')) {
            return [
                json_encode([
                    'id' => 'cus_provider_test_123',
                    'object' => 'customer',
                ], JSON_THROW_ON_ERROR),
                200,
                [],
            ];
        }

        if (str_contains($absUrl, '/v1/checkout/sessions')) {
            return [
                json_encode([
                    'id' => 'cs_provider_test_123',
                    'object' => 'checkout.session',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_provider_test_123',
                    'mode' => 'subscription',
                ], JSON_THROW_ON_ERROR),
                200,
                [],
            ];
        }

        if (str_contains($absUrl, '/v1/billing_portal/sessions')) {
            return [
                json_encode([
                    'id' => 'bps_provider_test_123',
                    'object' => 'billing_portal.session',
                    'url' => 'https://billing.stripe.com/p/session/bps_provider_test_123',
                ], JSON_THROW_ON_ERROR),
                200,
                [],
            ];
        }

        throw new RuntimeException(
            "Unexpected Stripe request in test: {$method} {$absUrl}",
        );
    }
}

/** Configure the SDK to use the deterministic Stripe transport fake. */
function fakeStripeBillingProviderHttpClient(): FakeStripeBillingProviderHttpClient
{
    Config::set('cashier.secret', 'sk_test_fake');

    $client = new FakeStripeBillingProviderHttpClient;

    ApiRequestor::setHttpClient($client);

    return $client;
}

afterEach(function (): void {
    ApiRequestor::setHttpClient(null);
});

test('identity reports Stripe', function (): void {
    expect((new StripeBillingProvider)->identity())
        ->toBe(BillingProvider::Stripe);
});

test('startCheckout produces the expected Stripe Checkout Session request', function (): void {
    $client = fakeStripeBillingProviderHttpClient();
    $actor = User::factory()->create();

    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_provider_test_123',
    ]);

    $outcome = (new StripeBillingProvider)->startCheckout(
        $organization,
        'price_provider_test',
        'https://app.test/success',
        'https://app.test/cancel',
        [
            'organization_id' => (string) $organization->id,
        ],
        $actor,
    );

    expect($outcome->type)->toBe('redirect')
        ->and($outcome->redirectUrl)
        ->toBe(
            'https://checkout.stripe.com/c/pay/cs_provider_test_123',
        );

    $checkoutRequest = collect($client->requests)
        ->last(
            fn (array $request): bool => str_contains(
                $request['url'],
                '/v1/checkout/sessions',
            ),
        );

    $lineItemPrices = collect(
        $checkoutRequest['params']['line_items'] ?? [],
    )->pluck('price')->all();

    expect($lineItemPrices)
        ->toContain('price_provider_test')
        ->and($checkoutRequest['params']['success_url'])
        ->toBe('https://app.test/success')
        ->and($checkoutRequest['params']['cancel_url'])
        ->toBe('https://app.test/cancel');
});

test('billingPortalUrl produces the expected Stripe Billing Portal request', function (): void {
    $client = fakeStripeBillingProviderHttpClient();

    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_provider_test_123',
    ]);

    $url = (new StripeBillingProvider)->billingPortalUrl(
        $organization,
        'https://app.test/billing',
    );

    expect($url)
        ->toBe(
            'https://billing.stripe.com/p/session/bps_provider_test_123',
        );

    $portalRequest = collect($client->requests)
        ->last(
            fn (array $request): bool => str_contains(
                $request['url'],
                '/v1/billing_portal/sessions',
            ),
        );

    expect($portalRequest['params']['customer'])
        ->toBe('cus_provider_test_123')
        ->and($portalRequest['params']['return_url'])
        ->toBe('https://app.test/billing');
});

test('retrieveSubscription reads the current period from the Stripe subscription item', function (): void {
    fakeStripeBillingProviderHttpClient();

    $organization = Organization::factory()->create();

    $customer = BillingCustomer::factory()
        ->for($organization)
        ->create([
            'provider' => BillingProvider::Stripe,
            'external_customer_id' => 'cus_provider_test_123',
            'livemode' => false,
        ]);

    $subscription = BillingSubscription::factory()
        ->for($customer, 'billingCustomer')
        ->create([
            'organization_id' => $organization->id,
            'provider' => BillingProvider::Stripe,
            'external_subscription_id' => 'sub_provider_test_123',
            'external_plan_id' => 'price_provider_test',
            'provider_status' => 'active',
            'livemode' => false,
        ]);

    $remote = (new StripeBillingProvider)
        ->retrieveSubscription($subscription);

    expect($remote->externalSubscriptionId)
        ->toBe('sub_provider_test_123')
        ->and($remote->externalCustomerId)
        ->toBe('cus_provider_test_123')
        ->and($remote->externalPlanId)
        ->toBe('price_provider_test')
        ->and($remote->status)
        ->toBe('active')
        ->and($remote->livemode)
        ->toBeFalse()
        ->and($remote->currentPeriodEndsAt?->getTimestamp())
        ->toBe(1_789_718_400);
});
