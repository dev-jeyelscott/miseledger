<?php

use App\Actions\Billing\CreatePayMongoQrPhPayment;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Config::set('billing.providers.paymongo.api_base_url', 'https://api.paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_never_leak');
});

function manualQrPhInvoice(): BillingInvoice
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'livemode' => false,
    ]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'external_subscription_id' => null,
        'collection_method' => BillingCollectionMethod::Manual,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
        'livemode' => false,
    ]);

    return BillingInvoice::factory()->for($subscription, 'billingSubscription')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'status' => BillingInvoiceStatus::Pending,
    ]);
}

function fakeQrPhCheckout(string $paymentIntentId, ?string $qrCodeUrl = null): void
{
    Http::fake([
        'api.paymongo.test/v1/payment_intents' => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'amount' => 49_900,
                'currency' => 'PHP',
                'livemode' => false,
            ]],
        ]),
        'api.paymongo.test/v1/payment_methods' => Http::response([
            'data' => ['id' => 'pm_qrph_test', 'type' => 'payment_method', 'attributes' => []],
        ]),
        "api.paymongo.test/v1/payment_intents/{$paymentIntentId}/attach" => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'next_action' => ['code' => [
                    'image_url' => $qrCodeUrl ?? "https://paymongo.test/{$paymentIntentId}.png",
                    'expires_at' => now()->addMinutes(30)->timestamp,
                ]],
            ]],
        ]),
    ]);
}

test('it creates and reuses a safe awaiting QR Ph checkout attempt', function (): void {
    $invoice = manualQrPhInvoice();
    fakeQrPhCheckout('pi_qrph_first');

    $first = app(CreatePayMongoQrPhPayment::class)->handle($invoice);
    $second = app(CreatePayMongoQrPhPayment::class)->handle($invoice->fresh());

    expect($first->payment->status)->toBe(BillingPaymentStatus::AwaitingPayment)
        ->and($first->payment->qr_code_url)->toBe('https://paymongo.test/pi_qrph_first.png')
        ->and($second->payment->is($first->payment))->toBeTrue()
        ->and($invoice->payments()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(BillingInvoiceStatus::PaymentPending);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.paymongo.test/v1/payment_intents'
        && data_get($request->data(), 'data.attributes.metadata.organization_id') === (string) $invoice->organization_id
        && data_get($request->data(), 'data.attributes.metadata.billing_invoice_id') === (string) $invoice->getKey());
    Http::assertSentCount(3);
});

test('it accepts PayMongo QR image data URIs returned by the test API', function (): void {
    $invoice = manualQrPhInvoice();
    $qrCodeUrl = 'data:image/png;base64,'.base64_encode('paymongo-qr');
    fakeQrPhCheckout('pi_qrph_data_uri', $qrCodeUrl);

    $checkout = app(CreatePayMongoQrPhPayment::class)->handle($invoice);

    expect($checkout->payment->qr_code_url)->toBe($qrCodeUrl);
});

test('an expired QR Ph attempt remains historical and a retry uses a new payment intent', function (): void {
    $invoice = manualQrPhInvoice();
    $paymentIntentCount = 0;

    Http::fake(function (Request $request) use (&$paymentIntentCount) {
        if ($request->url() === 'https://api.paymongo.test/v1/payment_intents') {
            $paymentIntentCount++;
            $paymentIntentId = $paymentIntentCount === 1 ? 'pi_qrph_first' : 'pi_qrph_second';

            return Http::response(['data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'amount' => 49_900,
                'currency' => 'PHP',
                'livemode' => false,
            ]]]);
        }

        if ($request->url() === 'https://api.paymongo.test/v1/payment_methods') {
            return Http::response(['data' => ['id' => 'pm_qrph_test', 'type' => 'payment_method', 'attributes' => []]]);
        }

        $paymentIntentId = str_contains($request->url(), 'pi_qrph_second') ? 'pi_qrph_second' : 'pi_qrph_first';

        return Http::response(['data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
            'next_action' => ['code' => [
                'image_url' => "https://paymongo.test/{$paymentIntentId}.png",
                'expires_at' => now()->addMinutes(30)->timestamp,
            ]],
        ]]]);
    });

    $first = app(CreatePayMongoQrPhPayment::class)->handle($invoice);
    $first->payment->update(['expires_at' => now()->subMinute()]);

    $second = app(CreatePayMongoQrPhPayment::class)->handle($invoice->fresh());

    expect($first->payment->fresh()->status)->toBe(BillingPaymentStatus::Expired)
        ->and($second->payment->external_payment_intent_id)->toBe('pi_qrph_second')
        ->and($second->payment->isNot($first->payment))->toBeTrue()
        ->and($invoice->payments()->count())->toBe(2);
});

test('manual QR Ph endpoints are unavailable when the capability is disabled', function (): void {
    Config::set('billing.providers.paymongo.manual_qrph', false);
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->post(route('organizations.billing.renew', $organization), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertNotFound();
});

test('a billing administrator cannot create a QR checkout for another organization invoice', function (): void {
    Config::set('billing.providers.paymongo.manual_qrph', true);
    $invoice = manualQrPhInvoice();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($invoice->organization)->for($user)->create(['role' => OrganizationRole::Owner]);
    $otherOrganization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.billing.invoices.payments.store', [$otherOrganization, $invoice]))
        ->assertForbidden();
});
