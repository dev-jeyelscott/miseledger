<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
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

function manualWebhookPayment(): BillingPayment
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo, 'livemode' => false]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'external_subscription_id' => null,
        'collection_method' => BillingCollectionMethod::Manual,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'pending',
        'livemode' => false,
    ]);
    $invoice = BillingInvoice::factory()->for($subscription, 'billingSubscription')->create([
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

function manualPaymentPaidPayload(string $eventId = 'evt_manual_payment_paid', int $amount = 49_900): array
{
    return ['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => [
        'type' => 'payment.paid',
        'livemode' => false,
        'data' => ['id' => 'pay_manual_webhook', 'type' => 'payment', 'attributes' => [
            'payment_intent_id' => 'pi_manual_webhook',
            'amount' => $amount,
            'currency' => 'PHP',
            'livemode' => false,
            'status' => 'paid',
            'paid_at' => now()->timestamp,
        ]],
    ]]];
}

function postManualPaymentWebhook(array $payload): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_paymongo_webhook_test');

    return test()->call('POST', route('billing.webhooks.paymongo'), [], [], [], [
        'HTTP_PAYMONGO-SIGNATURE' => "t={$timestamp},te={$signature},li=",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

test('payment.paid settles exactly one matching QR Ph payment and its invoice', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook(manualPaymentPaidPayload())->assertNoContent();
    postManualPaymentWebhook(manualPaymentPaidPayload())->assertNoContent();

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::Paid)
        ->and($payment->fresh()->billingInvoice->status)->toBe(BillingInvoiceStatus::Paid)
        ->and($payment->fresh()->billingInvoice->billingSubscription->provider_status)->toBe('active')
        ->and(BillingWebhookEffect::query()->count())->toBe(1);
});

test('a mismatched payment amount fails closed without granting entitlement', function (): void {
    Queue::fake();
    $payment = manualWebhookPayment();

    postManualPaymentWebhook(manualPaymentPaidPayload(amount: 1))->assertServerError();

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and($payment->fresh()->billingInvoice->status)->toBe(BillingInvoiceStatus::PaymentPending)
        ->and(BillingWebhookEffect::query()->count())->toBe(0);
});
