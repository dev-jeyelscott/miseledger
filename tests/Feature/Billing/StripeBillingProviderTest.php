<?php

use App\Enums\BillingProvider;
use App\Models\Organization;
use App\Support\Billing\Providers\StripeBillingProvider;
use Illuminate\Support\Facades\Config;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Fakes the Stripe HTTP transport boundary Cashier's `ApiRequestor` uses,
 * so this adapter's tests never make a real network call while still
 * exercising the real Cashier/Stripe SDK request-building code.
 */
final class FakeStripeBillingProviderHttpClient implements ClientInterface
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

        if (str_contains($absUrl, '/v1/customers/')) {
            return [json_encode(['id' => 'cus_provider_test_123', 'object' => 'customer']), 200, []];
        }

        if (str_contains($absUrl, '/v1/checkout/sessions')) {
            return [json_encode([
                'id' => 'cs_provider_test_123',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/cs_provider_test_123',
                'mode' => 'subscription',
            ]), 200, []];
        }

        if (str_contains($absUrl, '/v1/billing_portal/sessions')) {
            return [json_encode([
                'id' => 'bps_provider_test_123',
                'object' => 'billing_portal.session',
                'url' => 'https://billing.stripe.com/p/session/bps_provider_test_123',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

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

test('identity reports Stripe', function () {
    expect((new StripeBillingProvider)->identity())->toBe(BillingProvider::Stripe);
});

test('startCheckout produces the expected Stripe Checkout Session request', function () {
    $client = fakeStripeBillingProviderHttpClient();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_provider_test_123']);

    $url = (new StripeBillingProvider)->startCheckout(
        $organization,
        'price_provider_test',
        'https://app.test/success',
        'https://app.test/cancel',
        ['organization_id' => (string) $organization->getKey()],
    );

    expect($url)->toBe('https://checkout.stripe.com/c/pay/cs_provider_test_123');

    $checkoutRequest = collect($client->requests)
        ->last(fn (array $request): bool => str_contains($request['url'], '/v1/checkout/sessions'));

    $lineItemPrices = collect($checkoutRequest['params']['line_items'] ?? [])->pluck('price')->all();

    expect($lineItemPrices)->toContain('price_provider_test')
        ->and($checkoutRequest['params']['success_url'])->toBe('https://app.test/success')
        ->and($checkoutRequest['params']['cancel_url'])->toBe('https://app.test/cancel');
});

test('billingPortalUrl produces the expected Stripe Billing Portal request', function () {
    $client = fakeStripeBillingProviderHttpClient();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_provider_test_123']);

    $url = (new StripeBillingProvider)->billingPortalUrl($organization, 'https://app.test/billing');

    expect($url)->toBe('https://billing.stripe.com/p/session/bps_provider_test_123');

    $portalRequest = collect($client->requests)
        ->last(fn (array $request): bool => str_contains($request['url'], '/v1/billing_portal/sessions'));

    expect($portalRequest['params']['customer'])->toBe('cus_provider_test_123')
        ->and($portalRequest['params']['return_url'])->toBe('https://app.test/billing');
});
