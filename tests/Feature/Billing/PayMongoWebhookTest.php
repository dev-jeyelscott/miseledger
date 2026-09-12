<?php

use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use App\Http\Controllers\Billing\PayMongoWebhookController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Config::set('billing.providers.paymongo.mode', 'test');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_test');
});

/**
 * Create one PayMongo customer and subscription projection for webhook tests.
 *
 * @return array{organization: Organization, customer: BillingCustomer, subscription: BillingSubscription}
 */
function payMongoWebhookSubscription(bool $livemode = false): array
{
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_paymongo_webhook',
        'livemode' => $livemode,
    ]);
    $subscription = BillingSubscription::factory()
        ->for($customer, 'billingCustomer')
        ->create([
            'organization_id' => $organization->getKey(),
            'provider' => BillingProvider::PayMongo,
            'external_subscription_id' => 'subs_paymongo_webhook',
            'provider_status' => 'incomplete',
            'livemode' => $livemode,
        ]);

    return compact('organization', 'customer', 'subscription');
}

/**
 * Build one documented PayMongo subscription webhook envelope.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payMongoSubscriptionWebhookPayload(
    string $eventId = 'evt_paymongo_webhook',
    string $type = 'subscription.activated',
    string $status = 'active',
    bool $livemode = false,
    array $overrides = [],
): array {
    return array_replace_recursive([
        'data' => [
            'id' => $eventId,
            'type' => 'event',
            'attributes' => [
                'type' => $type,
                'livemode' => $livemode,
                'data' => [
                    'id' => 'subs_paymongo_webhook',
                    'type' => 'subscription',
                    'attributes' => [
                        'customer_id' => 'cus_paymongo_webhook',
                        'livemode' => $livemode,
                        'status' => $status,
                        'next_billing_schedule' => '2026-10-01',
                        'cancelled_at' => null,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

/** Build one documented PayMongo subscription-invoice webhook envelope. */
function payMongoInvoiceWebhookPayload(
    string $eventId,
    string $type,
    string $status,
): array {
    return [
        'data' => [
            'id' => $eventId,
            'type' => 'event',
            'attributes' => [
                'type' => $type,
                'livemode' => false,
                'data' => [
                    'id' => 'inv_paymongo_webhook',
                    'type' => 'invoice',
                    'attributes' => [
                        'customer_id' => 'cus_paymongo_webhook',
                        'resource_id' => 'subs_paymongo_webhook',
                        'livemode' => false,
                        'status' => $status,
                    ],
                ],
            ],
        ],
    ];
}

/** Build one PayMongo signature header for the exact raw request body. */
function payMongoSignatureHeader(
    string $body,
    string $secret = 'whsk_paymongo_webhook_test',
    string $mode = 'test',
    ?string $timestamp = null,
): string {
    $timestamp ??= (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return $mode === 'live'
        ? "t={$timestamp},te=,li={$signature}"
        : "t={$timestamp},te={$signature},li=";
}

/** Post an exact raw body to the PayMongo callback. */
function postRawPayMongoWebhook(
    string $body,
    ?string $header = null,
    string $secret = 'whsk_paymongo_webhook_test',
    string $signatureMode = 'test',
): TestResponse {
    return test()->call(
        'POST',
        route('billing.webhooks.paymongo'),
        [],
        [],
        [],
        [
            'HTTP_PAYMONGO-SIGNATURE' => $header ?? payMongoSignatureHeader(
                $body,
                $secret,
                $signatureMode,
            ),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );
}

/** Post one JSON PayMongo payload with a signature over its exact encoded bytes. */
function postPayMongoWebhook(
    array $payload,
    ?string $header = null,
    string $secret = 'whsk_paymongo_webhook_test',
    string $signatureMode = 'test',
): TestResponse {
    return postRawPayMongoWebhook(
        json_encode($payload, JSON_THROW_ON_ERROR),
        $header,
        $secret,
        $signatureMode,
    );
}

test('billing webhook routes remain explicit provider-specific infrastructure callbacks', function (): void {
    $stripeRoute = app('router')->getRoutes()->getByName('cashier.webhook');
    $payMongoRoute = app('router')->getRoutes()->getByName('billing.webhooks.paymongo');

    expect($stripeRoute)->not->toBeNull()
        ->and($payMongoRoute)->not->toBeNull()
        ->and($stripeRoute?->uri())->toBe('billing/webhooks/stripe')
        ->and($payMongoRoute?->uri())->toBe('billing/webhooks/paymongo')
        ->and($stripeRoute?->getActionName())->toContain(StripeWebhookController::class)
        ->and($payMongoRoute?->getActionName())->toContain(PayMongoWebhookController::class);

    $stripeMiddleware = $stripeRoute?->middleware() ?? [];
    $payMongoMiddleware = $payMongoRoute?->middleware() ?? [];

    expect($stripeMiddleware)->toContain('stripe.webhook')
        ->not->toContain('paymongo.webhook')
        ->not->toContain('auth')
        ->not->toContain('verified');

    expect($payMongoMiddleware)->toContain('paymongo.webhook')
        ->not->toContain('stripe.webhook')
        ->not->toContain('auth')
        ->not->toContain('verified');
});

test('an authenticated PayMongo subscription lifecycle event atomically projects audits and queues once', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $payload = payMongoSubscriptionWebhookPayload();

    postPayMongoWebhook($payload)->assertOk()->assertExactJson(['received' => true]);
    postPayMongoWebhook($payload)->assertOk()->assertExactJson(['received' => true]);

    expect($context['subscription']->fresh()->provider_status)->toBe('active')
        ->and(BillingWebhookEffect::query()->count())->toBe(1)
        ->and(BillingWebhookEffect::query()->sole()->lifecycle_event)->toBe(BillingLifecycleEvent::SubscriptionStarted)
        ->and(AuditLog::query()->count())->toBe(1);

    Queue::assertPushed(SendOrganizationBillingLifecycleNotification::class, 1);
});

test('documented PayMongo subscription failure states map to one controlled lifecycle effect', function (
    string $type,
    string $status,
): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    postPayMongoWebhook(
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_'.$status,
            type: $type,
            status: $status,
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    expect($context['subscription']->fresh()->provider_status)->toBe($status)
        ->and(BillingWebhookEffect::query()->sole()->lifecycle_event)->toBe(BillingLifecycleEvent::PaymentFailed)
        ->and(AuditLog::query()->where('action', 'billing.subscription.past_due')->count())->toBe(1);

    Queue::assertPushed(SendOrganizationBillingLifecycleNotification::class, 1);
})->with([
    ['subscription.past_due', 'past_due'],
    ['subscription.unpaid', 'unpaid'],
]);

test('documented subscription invoice outcomes map only to the owned subscription lifecycle', function (
    string $type,
    string $status,
    BillingLifecycleEvent $expectedLifecycle,
): void {
    Queue::fake();
    payMongoWebhookSubscription();

    postPayMongoWebhook(
        payMongoInvoiceWebhookPayload(
            eventId: 'evt_'.str_replace('.', '_', $type),
            type: $type,
            status: $status,
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    expect(BillingWebhookEffect::query()->sole()->lifecycle_event)->toBe($expectedLifecycle)
        ->and(AuditLog::query()->count())->toBe(1);

    Queue::assertPushed(SendOrganizationBillingLifecycleNotification::class, 1);
})->with([
    ['subscription.invoice.paid', 'paid', BillingLifecycleEvent::Recovered],
    ['subscription.invoice.payment_failed', 'open', BillingLifecycleEvent::PaymentFailed],
]);

test('subscription updated with no local status transition acknowledges without a false lifecycle effect', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $context['subscription']->update(['provider_status' => 'active']);

    postPayMongoWebhook(
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_subscription_updated_noop',
            type: 'subscription.updated',
            status: 'active',
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    expect(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($context['subscription']->fresh()->provider_status)->toBe('active');

    Queue::assertNothingPushed();
});

test('subscription updated cancellation preserves the established paid access boundary', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $paidAccessEndsAt = Carbon::parse('2026-10-01 00:00:00', 'UTC');

    $context['subscription']->update([
        'provider_status' => 'active',
        'current_period_ends_at' => $paidAccessEndsAt,
        'next_billing_at' => $paidAccessEndsAt,
        'ends_at' => $paidAccessEndsAt,
        'cancelled_at' => now()->subMinute(),
    ]);

    postPayMongoWebhook(
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_subscription_cancelled_update',
            type: 'subscription.updated',
            status: 'cancelled',
            overrides: [
                'data' => [
                    'attributes' => [
                        'data' => [
                            'attributes' => [
                                'next_billing_schedule' => '2026-09-15',
                                'cancelled_at' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    $subscription = $context['subscription']->fresh();

    expect($subscription->provider_status)->toBe('cancelled')
        ->and($subscription->next_billing_at)->toBeNull()
        ->and($subscription->ends_at?->equalTo($paidAccessEndsAt))->toBeTrue()
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and(BillingWebhookEffect::query()->sole()->lifecycle_event)->toBe(BillingLifecycleEvent::ScheduledCancellation);
});

test('PayMongo webhook authentication rejects invalid missing tampered wrong-lane and malformed signatures without effects', function (
    string $case,
): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();
    $payload = payMongoSubscriptionWebhookPayload();
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = match ($case) {
        'invalid' => postRawPayMongoWebhook(
            $body,
            secret: 'wrong-secret',
        ),
        'missing' => test()->call(
            'POST',
            route('billing.webhooks.paymongo'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        ),
        'tampered raw body' => postRawPayMongoWebhook(
            $body."\n",
            payMongoSignatureHeader($body),
        ),
        'wrong signature lane' => postRawPayMongoWebhook(
            $body,
            payMongoSignatureHeader($body, mode: 'live'),
        ),
        'malformed' => postRawPayMongoWebhook(
            $body,
            't=not-a-timestamp,te=bad,li=',
        ),
    };

    $response->assertForbidden();

    expect(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');

    Queue::assertNothingPushed();
})->with([
    'invalid',
    'missing',
    'tampered raw body',
    'wrong signature lane',
    'malformed',
]);

test('live mode accepts only the live signature lane and matching live payload', function (): void {
    Queue::fake();
    Config::set('billing.providers.paymongo.mode', 'live');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_live');
    $context = payMongoWebhookSubscription(livemode: true);
    $payload = payMongoSubscriptionWebhookPayload(livemode: true);

    postPayMongoWebhook(
        $payload,
        secret: 'whsk_paymongo_webhook_live',
        signatureMode: 'live',
    )->assertOk()->assertExactJson(['received' => true]);

    expect($context['subscription']->fresh()->provider_status)->toBe('active')
        ->and(BillingWebhookEffect::query()->count())->toBe(1);
});

test('a cryptographically valid wrong-environment payload is rejected before persistence', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    postPayMongoWebhook(
        payMongoSubscriptionWebhookPayload(livemode: true),
    )->assertForbidden();

    expect(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');

    Queue::assertNothingPushed();
});

test('a signed malformed JSON payload is rejected without billing side effects', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    postRawPayMongoWebhook('{')->assertUnprocessable();

    expect(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');

    Queue::assertNothingPushed();
});

test('unknown unsupported or unowned authenticated PayMongo events acknowledge without billing mutation', function (): void {
    Queue::fake();
    $context = payMongoWebhookSubscription();

    $payloads = [
        [
            'data' => [
                'id' => 'evt_unknown',
                'type' => 'event',
                'attributes' => [
                    'type' => 'customer.updated',
                    'livemode' => false,
                ],
            ],
        ],
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_unsupported_subscription_cancelled',
            type: 'subscription.cancelled',
            status: 'cancelled',
        ),
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_unsupported_canceled_status',
            type: 'subscription.updated',
            status: 'canceled',
        ),
        payMongoSubscriptionWebhookPayload(
            eventId: 'evt_unowned',
            overrides: [
                'data' => [
                    'attributes' => [
                        'data' => [
                            'attributes' => [
                                'customer_id' => 'cus_other',
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ];

    foreach ($payloads as $payload) {
        postPayMongoWebhook($payload)->assertOk()->assertExactJson(['received' => true]);
    }

    expect(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($context['subscription']->fresh()->provider_status)->toBe('incomplete');

    Queue::assertNothingPushed();
});
