<?php

use App\Enums\BillingProvider;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Config::set('billing.providers.paymongo.mode', 'test');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_test');
});

function payMongoWebhookSubscription(): array
{
    $organization = Organization::factory()->create(['trial_ends_at' => now()->subDay()]);
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo, 'external_customer_id' => 'cus_paymongo_webhook', 'livemode' => false]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create(['organization_id' => $organization->getKey(), 'provider' => BillingProvider::PayMongo, 'external_subscription_id' => 'subs_paymongo_webhook', 'provider_status' => 'incomplete', 'livemode' => false]);

    return compact('organization', 'customer', 'subscription');
}

function payMongoSubscriptionWebhookPayload(string $eventId = 'evt_paymongo_webhook', string $type = 'subscription.activated', array $overrides = []): array
{
    return array_replace_recursive(['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => ['type' => $type, 'livemode' => false, 'data' => ['id' => 'subs_paymongo_webhook', 'type' => 'subscription', 'attributes' => ['customer_id' => 'cus_paymongo_webhook', 'livemode' => false, 'status' => 'active', 'next_billing_schedule' => '2026-09-01', 'cancelled_at' => null]]]]], $overrides);
}

function postPayMongoWebhook(array $payload, ?string $header = null, ?string $secret = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? 'whsk_paymongo_webhook_test');

    return test()->call('POST', route('billing.webhooks.paymongo'), [], [], [], ['HTTP_PAYMONGO-SIGNATURE' => $header ?? "t={$timestamp},te={$signature},li=", 'CONTENT_TYPE' => 'application/json'], $body);
}

test('an authenticated PayMongo subscription lifecycle event atomically projects, audits, and queues once', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $payload = payMongoSubscriptionWebhookPayload();

    postPayMongoWebhook($payload)->assertNoContent();
    postPayMongoWebhook($payload)->assertNoContent();

    expect($context['subscription']->fresh()->provider_status)->toBe('active')
        ->and(BillingWebhookEffect::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(1);
    Queue::assertPushed(SendOrganizationBillingLifecycleNotification::class, 1);
});

test('PayMongo webhook authentication rejects invalid, missing, modified, wrong-mode, and malformed signatures without effects', function (string $case): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $payload = payMongoSubscriptionWebhookPayload();

    $response = match ($case) {
        'invalid' => postPayMongoWebhook($payload, null, 'wrong-secret'),
        'missing' => test()->call('POST', route('billing.webhooks.paymongo'), [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload)),
        'modified' => postPayMongoWebhook($payload, 't=1,te='.str_repeat('a', 64).',li='),
        'wrong mode' => (function () use ($payload): TestResponse {
            Config::set('billing.providers.paymongo.mode', 'live');

            return postPayMongoWebhook($payload);
        })(),
        'malformed' => postPayMongoWebhook($payload, 't=not-a-timestamp,te=bad,li='),
    };

    $response->assertForbidden();
    expect(BillingWebhookEffect::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe(0)->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');
    Queue::assertNothingPushed();
})->with(['invalid', 'missing', 'modified', 'wrong mode', 'malformed']);

test('a cryptographically valid wrong-environment payload is rejected before persistence', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    postPayMongoWebhook(payMongoSubscriptionWebhookPayload(overrides: ['data' => ['attributes' => ['livemode' => true, 'data' => ['attributes' => ['livemode' => true]]]]]))->assertForbidden();

    expect(BillingWebhookEffect::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe(0)->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');
    Queue::assertNothingPushed();
});

test('unknown or unowned authenticated PayMongo events acknowledge without billing mutation', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    postPayMongoWebhook(payMongoSubscriptionWebhookPayload(type: 'subscription.unknown'))->assertNoContent();
    postPayMongoWebhook(payMongoSubscriptionWebhookPayload('evt_unowned', overrides: ['data' => ['attributes' => ['data' => ['attributes' => ['customer_id' => 'cus_other']]]]]))->assertNoContent();

    expect(BillingWebhookEffect::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe(0)->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');
    Queue::assertNothingPushed();
});
