<?php

use App\Actions\Billing\ProcessOrganizationBillingWebhookEffect;
use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\BillingLifecycleNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function postIdempotencyWebhook(array $payload, string $secret = 'whsec_billing_idempotency'): TestResponse
{
    Config::set('cashier.webhook.secret', $secret);

    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        [
            'HTTP_STRIPE-SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );
}

function paymentFailurePayload(string $customerId, string $eventId): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => ['customer' => $customerId],
        ],
    ];
}

function billingWebhookRecipient(string $stripeCustomerId): User
{
    $organization = Organization::factory()->create(['stripe_id' => $stripeCustomerId]);
    $user = User::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'role' => OrganizationRole::Owner,
    ]);

    return $user;
}

test('a duplicate Stripe event records and dispatches custom lifecycle effects once', function () {
    Notification::fake();

    $recipient = billingWebhookRecipient('cus_duplicate_effect');
    $payload = paymentFailurePayload('cus_duplicate_effect', 'evt_duplicate_effect');

    postIdempotencyWebhook($payload)->assertOk();
    postIdempotencyWebhook($payload)->assertOk();

    Notification::assertSentTo($recipient, BillingLifecycleNotification::class, 1);
    expect(BillingWebhookEffect::query()->where('stripe_event_id', 'evt_duplicate_effect')->count())
        ->toBe(1)
        ->and(AuditLog::query()->where('correlation_id', 'evt_duplicate_effect')->count())
        ->toBe(1);
});

test('a failed notification dispatch leaves an ambiguous claim that blocks resend without duplicating its audit transition', function () {
    billingWebhookRecipient('cus_retry_effect');
    $payload = paymentFailurePayload('cus_retry_effect', 'evt_retry_effect');

    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('mail transport unavailable'));

    postIdempotencyWebhook($payload)->assertOk();

    $effect = BillingWebhookEffect::query()->where('stripe_event_id', 'evt_retry_effect')->firstOrFail();

    expect($effect->notification_dispatched_at)->toBeNull()
        ->and($effect->notification_claimed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('correlation_id', 'evt_retry_effect')->count())
        ->toBe(1);

    Notification::fake();

    // Whether the failed send above already reached the recipient cannot be determined
    // locally, so a retry must refuse to resend instead of risking a duplicate.
    postIdempotencyWebhook($payload)->assertOk();

    Notification::assertNothingSent();
    expect(AuditLog::query()->where('correlation_id', 'evt_retry_effect')->count())
        ->toBe(1)
        ->and($effect->fresh()->notification_dispatched_at)->toBeNull()
        ->and($effect->fresh()->notification_claimed_at)->not->toBeNull();
});

test('a duplicate PayMongo event records and dispatches custom lifecycle effects once through the provider-neutral effect boundary', function () {
    Notification::fake();

    $recipient = billingWebhookRecipient('cus_paymongo_recipient_placeholder');
    $organization = $recipient->organizations()->sole();
    BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_paymongo_duplicate',
    ]);

    $process = app(ProcessOrganizationBillingWebhookEffect::class);

    $process->handle(BillingProvider::PayMongo, 'evt_paymongo_duplicate', 'cus_paymongo_duplicate', BillingLifecycleEvent::PaymentFailed, 'billing.subscription.past_due');
    $process->handle(BillingProvider::PayMongo, 'evt_paymongo_duplicate', 'cus_paymongo_duplicate', BillingLifecycleEvent::PaymentFailed, 'billing.subscription.past_due');

    Notification::assertSentTo($recipient, BillingLifecycleNotification::class, 1);
    expect(BillingWebhookEffect::query()->where('provider', BillingProvider::PayMongo)->where('external_event_id', 'evt_paymongo_duplicate')->count())
        ->toBe(1)
        ->and(AuditLog::query()->where('correlation_id', 'evt_paymongo_duplicate')->count())
        ->toBe(1);
});

test('Stripe and PayMongo may independently reuse the same raw external event id without colliding', function () {
    Notification::fake();

    $recipient = billingWebhookRecipient('cus_shared_event_id');
    $organization = $recipient->organizations()->sole();
    BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_shared_event_id_paymongo',
    ]);

    $process = app(ProcessOrganizationBillingWebhookEffect::class);

    $process->handle(BillingProvider::Stripe, 'evt_shared_identifier', 'cus_shared_event_id', BillingLifecycleEvent::PaymentFailed, 'billing.subscription.past_due');
    $process->handle(BillingProvider::PayMongo, 'evt_shared_identifier', 'cus_shared_event_id_paymongo', BillingLifecycleEvent::PaymentFailed, 'billing.subscription.past_due');

    expect(BillingWebhookEffect::query()->where('external_event_id', 'evt_shared_identifier')->count())->toBe(2)
        ->and(AuditLog::query()->where('correlation_id', 'evt_shared_identifier')->count())->toBe(2);

    Notification::assertSentTo($recipient, BillingLifecycleNotification::class, 2);
});
