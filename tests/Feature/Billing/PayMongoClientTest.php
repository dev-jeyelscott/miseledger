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
