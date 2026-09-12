<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Jobs\SendManualRenewalPaymentReceipt;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\ManualRenewalPaymentReceiptNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Config::set('billing.providers.paymongo.mode', 'test');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_test');
});

/** Create one pending manual QR Ph payment attempt and its billing chain. */
function manualWebhookPayment(): BillingPayment
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'livemode' => false,
    ]);
    $subscription = BillingSubscription::factory()
        ->for($customer, 'billingCustomer')
        ->create([
            'organization_id' => $organization->getKey(),
            'provider' => BillingProvider::PayMongo,
            'external_subscription_id' => null,
            'collection_method' => BillingCollectionMethod::Manual,
            'plan_code' => 'starter',
            'interval' => 'monthly',
            'provider_status' => 'pending',
            'livemode' => false,
        ]);
    $invoice = BillingInvoice::factory()
        ->for($subscription, 'billingSubscription')
        ->create([
            'organization_id' => $organization->getKey(),
            'provider' => BillingProvider::PayMongo,
            'status' => BillingInvoiceStatus::PaymentPending,
        ]);

    return BillingPayment::factory()->for($invoice, 'billingInvoice')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'payment_method' => BillingPaymentMethod::QrPh,
        'external_payment_intent_id' => 'pi_manual_webhook',
        'amount' => $invoice->amount,
        'currency' => $invoice->currency,
        'status' => BillingPaymentStatus::AwaitingPayment,
        'livemode' => false,
    ]);
}

/**
 * Build one PayMongo payment webhook payload for a local Payment Intent.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function manualPaymentPayload(
    string $eventId = 'evt_manual_payment_paid',
    string $type = 'payment.paid',
    string $status = 'paid',
    int $amount = 49_900,
    string $currency = 'PHP',
    ?string $paymentIntentId = 'pi_manual_webhook',
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
                    'id' => 'pay_manual_webhook',
                    'type' => 'payment',
                    'attributes' => [
                        'payment_intent_id' => $paymentIntentId,
                        'amount' => $amount,
                        'currency' => $currency,
                        'livemode' => $livemode,
                        'status' => $status,
                        'paid_at' => now()->timestamp,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

/** Build the exact raw-body PayMongo signature for a manual-payment test. */
function manualPayMongoSignature(
    string $body,
    string $secret = 'whsk_paymongo_webhook_test',
    string $mode = 'test',
): string {
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return $mode === 'live'
        ? "t={$timestamp},te=,li={$signature}"
        : "t={$timestamp},te={$signature},li=";
}

/** Post one exact manual-payment JSON payload to the PayMongo callback. */
function postManualPaymentWebhook(
    array $payload,
    string $secret = 'whsk_paymongo_webhook_test',
    string $signatureMode = 'test',
): TestResponse {
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call(
        'POST',
        route('billing.webhooks.paymongo'),
        [],
        [],
        [],
        [
            'HTTP_PAYMONGO-SIGNATURE' => manualPayMongoSignature(
                $body,
                $secret,
                $signatureMode,
            ),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );
}

test('payment paid settles exactly one matching QR Ph payment invoice audit and receipt', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();
    $payload = manualPaymentPayload(
        amount: $payment->amount,
        currency: $payment->currency,
    );

    postManualPaymentWebhook($payload)->assertOk()->assertExactJson(['received' => true]);
    postManualPaymentWebhook($payload)->assertOk()->assertExactJson(['received' => true]);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::Paid)
        ->and($payment->fresh()->billingInvoice->status)->toBe(BillingInvoiceStatus::Paid)
        ->and($payment->fresh()->billingInvoice->billingSubscription->provider_status)->toBe('active')
        ->and(BillingWebhookEffect::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'billing.payment.settled')->count())->toBe(1);

    Queue::assertPushed(SendManualRenewalPaymentReceipt::class, 1);
});

test('payment failed mutates only the exact pending QR Ph attempt and never grants entitlement', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook(
        manualPaymentPayload(
            eventId: 'evt_manual_payment_failed',
            type: 'payment.failed',
            status: 'failed',
            amount: $payment->amount,
            currency: $payment->currency,
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::Failed)
        ->and($payment->fresh()->billingInvoice->status)->toBe(BillingInvoiceStatus::PaymentPending)
        ->and($payment->fresh()->billingInvoice->billingSubscription->provider_status)->toBe('pending')
        ->and(BillingWebhookEffect::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'billing.payment.failed')->count())->toBe(1);
});

test('payment events with mismatched amount or currency fail closed without entitlement or webhook effects', function (
    string $field,
): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    $payload = manualPaymentPayload(
        amount: $field === 'amount' ? 1 : $payment->amount,
        currency: $field === 'currency' ? 'USD' : $payment->currency,
    );

    postManualPaymentWebhook($payload)->assertServerError();

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and($payment->fresh()->billingInvoice->status)->toBe(BillingInvoiceStatus::PaymentPending)
        ->and($payment->fresh()->billingInvoice->billingSubscription->provider_status)->toBe('pending')
        ->and(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['amount', 'currency']);

test('payment failed requires the documented failed resource status before any mutation', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook(
        manualPaymentPayload(
            eventId: 'evt_manual_payment_failed_wrong_status',
            type: 'payment.failed',
            status: 'paid',
            amount: $payment->amount,
            currency: $payment->currency,
        ),
    )->assertUnprocessable();

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('unowned or non Payment Intent PayMongo payments acknowledge without local billing mutation', function (
    ?string $paymentIntentId,
): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook(
        manualPaymentPayload(
            eventId: $paymentIntentId === null
                ? 'evt_payment_without_intent'
                : 'evt_unowned_payment_intent',
            amount: $payment->amount,
            currency: $payment->currency,
            paymentIntentId: $paymentIntentId,
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([null, 'pi_not_owned_by_miseledger']);

test('qrph expired is authenticated and safely acknowledged without inventing an undocumented ownership mapping', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook([
        'data' => [
            'id' => 'evt_qrph_expired',
            'type' => 'event',
            'attributes' => [
                'type' => 'qrph.expired',
                'livemode' => false,
            ],
        ],
    ])->assertOk()->assertExactJson(['received' => true]);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and(BillingWebhookEffect::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a verified live QR Ph payment repairs a legacy test-mode manual subscription before settlement', function (): void {
    Queue::fake();
    Config::set('billing.providers.paymongo.mode', 'live');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_live');
    $payment = manualWebhookPayment();
    $payment->update(['livemode' => true]);
    $payload = manualPaymentPayload(
        eventId: 'evt_live_manual_payment_paid',
        amount: $payment->amount,
        currency: $payment->currency,
        livemode: true,
    );

    postManualPaymentWebhook(
        $payload,
        secret: 'whsk_paymongo_webhook_live',
        signatureMode: 'live',
    )->assertOk()->assertExactJson(['received' => true]);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::Paid)
        ->and($payment->fresh()->billingInvoice->billingSubscription->livemode)->toBeTrue();
});

test('a settled payment sends one detailed receipt to billing administrators', function (): void {
    Queue::fake();
    Notification::fake();
    $payment = manualWebhookPayment();
    $recipient = User::factory()->create();
    OrganizationMembership::factory()
        ->for($payment->organization)
        ->for($recipient)
        ->create(['role' => OrganizationRole::Owner]);

    postManualPaymentWebhook(
        manualPaymentPayload(
            amount: $payment->amount,
            currency: $payment->currency,
        ),
    )->assertOk()->assertExactJson(['received' => true]);

    $job = new SendManualRenewalPaymentReceipt($payment->getKey());
    $job->handle();
    $job->handle();

    Notification::assertSentTo(
        $recipient,
        ManualRenewalPaymentReceiptNotification::class,
        function (ManualRenewalPaymentReceiptNotification $notification) use ($payment): bool {
            return $notification->payment->is($payment)
                && $notification->payment->amount === $payment->amount;
        },
    );

    expect($payment->fresh()->receipt_notification_dispatched_at)->not->toBeNull();
});
