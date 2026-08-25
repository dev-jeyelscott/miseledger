<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

final class BillingReconciliationStripeClient implements ClientInterface
{
    /** @var array<string, array{id: string, status: string, price: string}> */
    public array $subscriptionsByCustomer = [];

    /** @var list<string> */
    public array $requestedCustomers = [];

    /** @var array<string, int> */
    public array $customerStatuses = [];

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<int, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if (str_contains($absUrl, '/v1/customers/')) {
            $customerId = basename($absUrl);
            $this->requestedCustomers[] = $customerId;
            $status = $this->customerStatuses[$customerId] ?? 200;

            if ($status !== 200) {
                return [json_encode(['error' => ['type' => 'api_error']]), $status, []];
            }

            return [json_encode(['id' => $customerId, 'object' => 'customer']), 200, []];
        }

        if (str_contains($absUrl, '/v1/subscriptions')) {
            if (preg_match('#/v1/subscriptions/(sub_[^/?]+)#', $absUrl, $matches) === 1) {
                foreach ($this->subscriptionsByCustomer as $customerId => $subscription) {
                    if ($subscription['id'] !== $matches[1]) {
                        continue;
                    }

                    return [json_encode([
                        'id' => $subscription['id'],
                        'object' => 'subscription',
                        'customer' => $customerId,
                        'status' => $subscription['status'],
                        'livemode' => false,
                        'trial_end' => null,
                        'current_period_end' => null,
                        'cancel_at' => null,
                        'cancel_at_period_end' => false,
                        'canceled_at' => null,
                        'items' => [
                            'data' => [[
                                'id' => 'si_'.$subscription['id'],
                                'object' => 'subscription_item',
                                'price' => ['id' => $subscription['price']],
                            ]],
                        ],
                    ]), 200, []];
                }

                return [json_encode(['error' => ['type' => 'invalid_request_error']]), 404, []];
            }
            $subscription = $this->subscriptionsByCustomer[$params['customer']] ?? null;

            return [json_encode([
                'object' => 'list',
                'data' => $subscription === null ? [] : [[
                    'id' => $subscription['id'], 'object' => 'subscription', 'status' => $subscription['status'],
                    'items' => ['data' => [[
                        'id' => 'si_'.$subscription['id'], 'object' => 'subscription_item',
                        'price' => ['id' => $subscription['price']],
                    ]]],
                ]],
                'has_more' => false, 'url' => '/v1/subscriptions',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request: {$method} {$absUrl}");
    }
}

afterEach(function (): void {
    ApiRequestor::setHttpClient(null);
});

function billingReconciliationSubscription(Organization $organization, array $attributes = []): void
{
    $cashierSubscription = $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'), 'stripe_id' => 'sub_'.$organization->id,
        'stripe_status' => 'active', 'stripe_price' => 'price_standard', 'quantity' => 1,
    ], $attributes));

    $customer = BillingCustomer::query()->firstOrCreate([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::Stripe,
    ], [
        'external_customer_id' => $organization->stripe_id,
        'livemode' => false,
    ]);

    BillingSubscription::query()->create([
        'organization_id' => $organization->getKey(),
        'billing_customer_id' => $customer->getKey(),
        'provider' => BillingProvider::Stripe,
        'type' => $cashierSubscription->type,
        'external_subscription_id' => $cashierSubscription->stripe_id,
        'external_plan_id' => $cashierSubscription->stripe_price,
        'provider_status' => $cashierSubscription->stripe_status,
        'livemode' => false,
    ]);
}

function billingReconciliationClient(): BillingReconciliationStripeClient
{
    Config::set('cashier.secret', 'sk_test_billing_reconciliation');
    $client = new BillingReconciliationStripeClient;
    ApiRequestor::setHttpClient($client);

    return $client;
}

test('a clean reconciliation is idempotent and leaves inventory data unchanged', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_clean']);
    billingReconciliationSubscription($organization, ['stripe_id' => 'sub_clean']);
    $client = billingReconciliationClient();
    $client->subscriptionsByCustomer['cus_clean'] = ['id' => 'sub_clean', 'status' => 'active', 'price' => 'price_standard'];

    $this->artisan('billing:reconcile')
        ->expectsOutputToContain('1 subscription inspected, 0 discrepancies, 0 provider failures')
        ->assertExitCode(0);
    $this->artisan('billing:reconcile')
        ->expectsOutputToContain('Billing reconciliation completed: 1 subscription inspected, 0 discrepancies, 0 provider failures')
        ->assertExitCode(0);

    expect(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0)
        ->and($organization->fresh()->subscriptions()->sole()->only(['stripe_id', 'stripe_status', 'stripe_price']))
        ->toBe(['stripe_id' => 'sub_clean', 'stripe_status' => 'active', 'stripe_price' => 'price_standard']);
});

test('it logs missing customers, missing local subscriptions, unexpected local active state, and mismatches', function () {
    $missingCustomer = Organization::factory()->create(['stripe_id' => 'cus_missing']);
    $missingLocal = Organization::factory()->create(['stripe_id' => 'cus_remote_only']);
    $unexpectedLocal = Organization::factory()->create(['stripe_id' => 'cus_local_only']);
    $mismatched = Organization::factory()->create(['stripe_id' => 'cus_mismatch']);
    billingReconciliationSubscription($unexpectedLocal, ['stripe_id' => 'sub_local_only']);
    billingReconciliationSubscription($mismatched, ['stripe_id' => 'sub_mismatch']);

    $client = billingReconciliationClient();
    $client->customerStatuses['cus_missing'] = 404;
    $client->subscriptionsByCustomer['cus_remote_only'] = ['id' => 'sub_remote_only', 'status' => 'active', 'price' => 'price_standard'];
    $client->subscriptionsByCustomer['cus_mismatch'] = ['id' => 'sub_mismatch', 'status' => 'past_due', 'price' => 'price_different'];
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $this->artisan('billing:reconcile')
        ->expectsOutputToContain('2 subscriptions inspected, 4 discrepancies, 0 provider failures')
        ->assertExitCode(1);

    foreach ([
        [$missingCustomer->id, 'missing_stripe_customer'], [$missingLocal->id, 'missing_local_subscription'],
        [$unexpectedLocal->id, 'missing_remote_subscription'], [$mismatched->id, 'subscription_mismatch'],
    ] as [$organizationId, $discrepancy]) {
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Billing operational signal emitted.'
            && $context['organization_id'] === $organizationId
            && $context['event'] === 'billing.reconciliation.mismatch'
            && $context['mismatch'] === $discrepancy,
        )->once();
    }
});

test('it logs provider failures without leaking provider messages', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_provider_failure']);
    $client = billingReconciliationClient();
    $client->customerStatuses['cus_provider_failure'] = 500;
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $this->artisan('billing:reconcile')
        ->expectsOutputToContain('0 subscriptions inspected, 0 discrepancies, 1 provider failure')
        ->assertExitCode(1);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Billing operational signal emitted.'
        && $context['organization_id'] === $organization->id
        && $context['event'] === 'billing.reconciliation.provider_failure'
        && $context['failure_source'] === 'provider'
        && $context['http_status'] === 500
        && ! array_key_exists('message', $context),
    )->once();
});

test('it processes every organization across bounded batches', function () {
    $organizations = Organization::factory()->count(3)->sequence(
        ['stripe_id' => 'cus_batch_one'], ['stripe_id' => 'cus_batch_two'], ['stripe_id' => 'cus_batch_three'],
    )->create();
    $client = billingReconciliationClient();

    $this->artisan('billing:reconcile --chunk=1')
        ->expectsOutputToContain('0 subscriptions inspected, 0 discrepancies, 0 provider failures')
        ->assertExitCode(0);

    expect($client->requestedCustomers)->toEqualCanonicalizing($organizations->pluck('stripe_id')->all());
});

test('PayMongo reconciliation recovers projection drift idempotently without inventory changes', function () {
    Config::set('billing.providers.paymongo.api_base_url', 'https://paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_paymongo');
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_reconcile_paymongo',
        'livemode' => false,
    ]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => 'subs_reconcile_paymongo',
        'external_plan_id' => 'plan_starter_monthly',
        'provider_status' => 'active',
        'livemode' => false,
    ]);
    Http::fake([
        'paymongo.test/*' => Http::response([
            'data' => [
                'id' => 'subs_reconcile_paymongo',
                'type' => 'subscription',
                'attributes' => [
                    'customer_id' => 'cus_reconcile_paymongo',
                    'plan' => ['id' => 'plan_pro_monthly'],
                    'status' => 'past_due',
                    'livemode' => false,
                    'next_billing_schedule' => '2026-09-01',
                    'cancelled_at' => null,
                ],
            ],
        ]),
    ]);

    $this->artisan('billing:reconcile')->assertExitCode(1);

    expect($subscription->fresh()->only(['external_plan_id', 'provider_status']))->toBe([
        'external_plan_id' => 'plan_pro_monthly', 'provider_status' => 'past_due',
    ])->and(AuditLog::query()->where('action', 'billing.subscription.reconciled')->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);

    $this->artisan('billing:reconcile')
        ->expectsOutputToContain('Billing reconciliation completed: 1 subscription inspected, 0 discrepancies, 0 provider failures')
        ->assertExitCode(0);
    expect(AuditLog::query()->where('action', 'billing.subscription.reconciled')->count())->toBe(1);
});

test('PayMongo reconciliation settles a paid manual QR Ph intent through the guarded settlement action', function (): void {
    Config::set('billing.providers.paymongo.api_base_url', 'https://paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_paymongo');
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'livemode' => false,
    ]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
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
    $payment = BillingPayment::factory()->for($invoice, 'billingInvoice')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'payment_method' => BillingPaymentMethod::QrPh,
        'external_payment_intent_id' => 'pi_lost_webhook',
        'amount' => $invoice->amount,
        'currency' => $invoice->currency,
        'status' => BillingPaymentStatus::AwaitingPayment,
        'livemode' => false,
    ]);
    Http::fake([
        'paymongo.test/*' => Http::response([
            'data' => [
                'id' => 'pi_lost_webhook',
                'type' => 'payment_intent',
                'attributes' => [
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'livemode' => false,
                    'status' => 'succeeded',
                    'payments' => [[
                        'id' => 'pay_lost_webhook',
                        'attributes' => ['paid_at' => now()->timestamp],
                    ]],
                ],
            ],
        ]),
    ]);

    $this->artisan('billing:reconcile')->assertExitCode(1);

    expect($payment->fresh()->status)->toBe(BillingPaymentStatus::Paid)
        ->and($invoice->fresh()->status)->toBe(BillingInvoiceStatus::Paid)
        ->and($subscription->fresh()->provider_status)->toBe('active')
        ->and(AuditLog::query()->where('action', 'billing.payment.reconciled')->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);

    $this->artisan('billing:reconcile')->assertExitCode(0);
    expect(AuditLog::query()->where('action', 'billing.payment.reconciled')->count())->toBe(1);
});
