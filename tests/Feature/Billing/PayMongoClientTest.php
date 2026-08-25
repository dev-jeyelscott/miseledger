<?php

use App\Support\Billing\Providers\PayMongoClient;
use App\Support\Billing\Providers\PayMongoRequestException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    Config::set('billing.providers.paymongo.api_base_url', 'https://api.paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_never_leak');
});

test('PayMongo client uses configured base URL and secret-key basic authentication', function () {
    Http::fake(['api.paymongo.test/*' => Http::response(['data' => ['id' => 'plan_123']], 200)]);

    expect((new PayMongoClient)->get('retrieve_plan', '/plans/plan_123'))->toBe(['data' => ['id' => 'plan_123']]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.paymongo.test/v1/plans/plan_123'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_never_leak:'));
    });
});

test('PayMongo client sends an explicit idempotency key for supported resource-creation requests without retrying the post', function () {
    Http::fake(['api.paymongo.test/*' => Http::response(['data' => ['id' => 'cus_123']], 200)]);

    (new PayMongoClient)->post('create_customer', '/customers', ['data' => []], '1', 'customer-key');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->hasHeader('Idempotency-Key', 'customer-key');
    });
    Http::assertSentCount(1);
});

test('PayMongo client classifies provider responses without exposing response bodies or secrets', function (int $status, string $classification) {
    Http::fake(['api.paymongo.test/*' => Http::response(['errors' => [['detail' => 'sensitive provider response']]], $status)]);

    expect(fn () => (new PayMongoClient)->post('create_checkout', '/checkout_sessions', ['sensitive' => 'payload']))
        ->toThrow(PayMongoRequestException::class, "PayMongo {$classification} failure during create_checkout.");

    try {
        (new PayMongoClient)->post('create_checkout', '/checkout_sessions', ['sensitive' => 'payload']);
    } catch (PayMongoRequestException $exception) {
        expect($exception->getMessage())->not->toContain('sk_test_never_leak')
            ->not->toContain('sensitive provider response')
            ->not->toContain('payload');
    }
})->with([
    'validation' => [400, 'validation'],
    'authentication' => [401, 'authentication'],
    'rate limiting' => [429, 'rate_limit'],
    'provider failure' => [500, 'provider'],
]);

test('PayMongo client retries bounded safe reads after connection failures and never retries posts', function () {
    Http::fake(['api.paymongo.test/*' => Http::failedConnection()]);

    expect(fn () => (new PayMongoClient)->get('retrieve_plan', '/plans/plan_123'))
        ->toThrow(PayMongoRequestException::class, 'PayMongo connection failure during retrieve_plan.');

    Http::assertSentCount(2);

    Http::fake(['api.paymongo.test/*' => Http::failedConnection()]);

    expect(fn () => (new PayMongoClient)->post('create_checkout', '/checkout_sessions', []))
        ->toThrow(PayMongoRequestException::class, 'PayMongo connection failure during create_checkout.');

    Http::assertSentCount(1);
});

test('PayMongo client retries rate-limited safe reads but not non-idempotent posts', function () {
    Http::fakeSequence()
        ->push(['errors' => []], 429, ['Retry-After' => '0'])
        ->push(['data' => ['id' => 'plan_123']], 200);

    expect((new PayMongoClient)->get('retrieve_plan', '/plans/plan_123'))->toBe(['data' => ['id' => 'plan_123']]);

    Http::assertSentCount(2);
});

test('PayMongo client distinguishes request timeouts without exposing transport details', function () {
    Http::fake(['api.paymongo.test/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    expect(fn () => (new PayMongoClient)->get('retrieve_plan', '/plans/plan_123'))
        ->toThrow(PayMongoRequestException::class, 'PayMongo timeout failure during retrieve_plan.');
});

test('PayMongo client creates a QR Ph payment intent, method, and attachment with opaque identifiers only', function (): void {
    Http::fake([
        'api.paymongo.test/v1/payment_intents' => Http::response(['data' => ['id' => 'pi_qrph_123']], 200),
        'api.paymongo.test/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_qrph_123']], 200),
        'api.paymongo.test/v1/payment_intents/pi_qrph_123/attach' => Http::response(['data' => ['id' => 'pi_qrph_123']], 200),
    ]);

    $client = new PayMongoClient;
    $client->createPaymentIntent(49_900, 'PHP', ['organization_id' => 1, 'billing_invoice_id' => 2], '1', 'intent-key');
    $client->createQrPhPaymentMethod('1', 'method-key');
    $client->attachPaymentMethod('pi_qrph_123', 'pm_qrph_123', '1', 'attach-key');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.paymongo.test/v1/payment_intents'
            && $request->hasHeader('Idempotency-Key', 'intent-key')
            && data_get($payload, 'data.attributes.amount') === 49_900
            && data_get($payload, 'data.attributes.currency') === 'PHP'
            && data_get($payload, 'data.attributes.payment_method_allowed') === ['qrph']
            && data_get($payload, 'data.attributes.metadata') === ['organization_id' => 1, 'billing_invoice_id' => 2];
    });
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paymongo.test/v1/payment_methods'
        && data_get($request->data(), 'data.attributes.type') === 'qrph');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paymongo.test/v1/payment_intents/pi_qrph_123/attach'
        && data_get($request->data(), 'data.attributes.payment_method') === 'pm_qrph_123');
});
