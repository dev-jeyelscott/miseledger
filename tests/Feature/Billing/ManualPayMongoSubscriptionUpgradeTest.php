<?php

use App\Actions\Billing\CreatePayMongoQrPhPayment;
use App\Actions\Billing\CreateUpgradeInvoice;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Enums\PlanCode;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 30_000, 'yearly' => 365_000]);
    Config::set('billing.plans.growth.manual_amounts', ['monthly' => 60_000, 'yearly' => 730_000]);
    Config::set('billing.plans.business.manual_amounts', ['monthly' => 90_000, 'yearly' => null]);
    Config::set('billing.providers.paymongo.manual_qrph', true);
    Config::set('billing.providers.paymongo.mode', 'test');
    Config::set('billing.providers.paymongo.api_base_url', 'https://api.paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_never_leak');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_paymongo_webhook_test');
    Http::preventStrayRequests();
});

function upgradeTestSubscription(array $attributes = []): BillingSubscription
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'livemode' => false,
    ]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create(array_merge([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'livemode' => false,
        'current_period_ends_at' => Carbon::parse('2026-09-10 00:00:00', 'UTC'),
    ], $attributes));
}

function fakeUpgradeCheckout(string $paymentIntentId): void
{
    Http::fake([
        'api.paymongo.test/v1/payment_intents' => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'amount' => 10_000, 'currency' => 'PHP', 'livemode' => false,
            ]],
        ]),
        'api.paymongo.test/v1/payment_methods' => Http::response([
            'data' => ['id' => 'pm_qrph_test', 'type' => 'payment_method', 'attributes' => []],
        ]),
        "api.paymongo.test/v1/payment_intents/{$paymentIntentId}/attach" => Http::response([
            'data' => ['id' => $paymentIntentId, 'type' => 'payment_intent', 'attributes' => [
                'next_action' => ['code' => [
                    'image_url' => "https://paymongo.test/{$paymentIntentId}.png",
                    'expires_at' => now()->addMinutes(30)->timestamp,
                ]],
            ]],
        ]),
    ]);
}

function postUpgradeWebhook(array $payload, string $secret = 'whsk_paymongo_webhook_test'): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return test()->call('POST', route('billing.webhooks.paymongo'), [], [], [], [
        'HTTP_PAYMONGO-SIGNATURE' => "t={$timestamp},te={$signature},li=",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

function upgradePaymentPaidPayload(string $eventId, string $paymentIntentId, int $amount): array
{
    return ['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => [
        'type' => 'payment.paid',
        'livemode' => false,
        'data' => ['id' => 'pay_'.$paymentIntentId, 'type' => 'payment', 'attributes' => [
            'payment_intent_id' => $paymentIntentId,
            'amount' => $amount,
            'currency' => 'PHP',
            'livemode' => false,
            'status' => 'paid',
            'paid_at' => now()->timestamp,
        ]],
    ]]];
}

test('a Starter to Growth upgrade prices the remaining period at the daily-rate difference and preserves the paid-through boundary', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    $originalPeriodEnd = $subscription->current_period_ends_at;

    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));

    // starter=30_000/30d=1000/day, growth=60_000/30d=2000/day, diff=1000/day, remaining=10 days.
    expect($invoice->amount)->toBe(10_000)
        ->and($invoice->currency)->toBe('PHP')
        ->and($invoice->invoice_type)->toBe(BillingInvoiceType::Upgrade)
        ->and($invoice->plan_code)->toBe('starter')
        ->and($invoice->target_plan_code)->toBe('growth')
        ->and($invoice->status)->toBe(BillingInvoiceStatus::Pending)
        ->and($invoice->period_ends_at->equalTo($originalPeriodEnd))->toBeTrue()
        ->and($subscription->fresh()->plan_code)->toBe('starter');

    Carbon::setTestNow();
});

test('a Starter to Business upgrade and a Growth to Business upgrade both price correctly', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));

    $starter = upgradeTestSubscription();
    $starterToBusiness = app(CreateUpgradeInvoice::class)->handle($starter, PlanCode::from('business'));
    // starter=1000/day, business=3000/day, diff=2000/day * 10 days = 20_000.
    expect($starterToBusiness->amount)->toBe(20_000)
        ->and($starterToBusiness->target_plan_code)->toBe('business');

    $growth = upgradeTestSubscription(['plan_code' => 'growth']);
    $growthToBusiness = app(CreateUpgradeInvoice::class)->handle($growth, PlanCode::from('business'));
    // growth=2000/day, business=3000/day, diff=1000/day * 10 days = 10_000.
    expect($growthToBusiness->amount)->toBe(10_000)
        ->and($growthToBusiness->target_plan_code)->toBe('business');

    Carbon::setTestNow();
});

test('a same-plan request is rejected before any invoice is created', function (): void {
    $subscription = upgradeTestSubscription();

    expect(fn () => app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('starter')))
        ->toThrow(RuntimeException::class, 'This plan change is not a supported upgrade.');
    expect(BillingInvoice::query()->count())->toBe(0);
});

test('a downgrade request is rejected before any invoice is created', function (): void {
    $subscription = upgradeTestSubscription(['plan_code' => 'business']);

    expect(fn () => app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('starter')))
        ->toThrow(RuntimeException::class, 'This plan change is not a supported upgrade.');
    expect(BillingInvoice::query()->count())->toBe(0);
});

test('an upgrade target unavailable at the subscription interval is rejected', function (): void {
    $subscription = upgradeTestSubscription(['interval' => 'yearly']);

    expect(fn () => app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('business')))
        ->toThrow(RuntimeException::class);
    expect(BillingInvoice::query()->count())->toBe(0);
});

test('duplicate upgrade requests for the same target reuse the pending invoice', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();

    $first = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));
    $second = app(CreateUpgradeInvoice::class)->handle($subscription->fresh(), PlanCode::from('growth'));

    expect($second->is($first))->toBeTrue()
        ->and($subscription->invoices()->count())->toBe(1);

    Carbon::setTestNow();
});

test('a Stripe-owned subscription is rejected with a not-yet-available error', function (): void {
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);
    BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::Stripe,
        'type' => config('billing.subscription_type'),
        'collection_method' => BillingCollectionMethod::Automatic,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
        'current_period_ends_at' => now()->addDays(10),
    ]);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)
        ->postJson(route('organizations.billing.upgrade', $organization), ['plan' => 'growth'])
        ->assertStatus(422);

    expect(BillingInvoice::query()->count())->toBe(0);
});

test('successful settlement flips the plan exactly once and preserves the paid-through date, recording an audit entry', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    $originalPeriodEnd = $subscription->current_period_ends_at;
    fakeUpgradeCheckout('pi_upgrade_settle');
    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));
    app(CreatePayMongoQrPhPayment::class)->handle($invoice);
    $payment = $invoice->fresh()->payments()->sole();
    $payment->update(['external_payment_intent_id' => 'pi_upgrade_settle']);

    $payload = upgradePaymentPaidPayload('evt_upgrade_settle', 'pi_upgrade_settle', 10_000);
    postUpgradeWebhook($payload)->assertNoContent();
    postUpgradeWebhook($payload)->assertNoContent();

    expect($subscription->fresh()->plan_code)->toBe('growth')
        ->and($subscription->fresh()->current_period_ends_at->equalTo($originalPeriodEnd))->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(BillingInvoiceStatus::Paid)
        ->and(BillingWebhookEffect::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'billing.subscription.upgraded')->count())->toBe(1);

    $audit = AuditLog::query()->where('action', 'billing.subscription.upgraded')->sole();
    expect($audit->before_data)->toBe(['plan' => 'starter', 'interval' => 'monthly'])
        ->and($audit->after_data)->toBe(['plan' => 'growth', 'interval' => 'monthly', 'provider' => 'paymongo']);

    Carbon::setTestNow();
});

test('an unpaid, expired, or failed QR attempt leaves the plan unchanged', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    fakeUpgradeCheckout('pi_upgrade_unpaid');
    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));
    app(CreatePayMongoQrPhPayment::class)->handle($invoice);

    $payment = $invoice->fresh()->payments()->sole();
    $payment->update(['status' => BillingPaymentStatus::Expired, 'external_payment_intent_id' => 'pi_upgrade_unpaid']);

    expect($subscription->fresh()->plan_code)->toBe('starter')
        ->and(BillingPayment::query()->where('status', BillingPaymentStatus::Paid)->count())->toBe(0);

    Carbon::setTestNow();
});

test('a mismatched settlement amount is rejected and leaves the plan untouched', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    fakeUpgradeCheckout('pi_upgrade_forged');
    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));
    app(CreatePayMongoQrPhPayment::class)->handle($invoice);
    $invoice->fresh()->payments()->sole()->update(['external_payment_intent_id' => 'pi_upgrade_forged']);

    $payload = upgradePaymentPaidPayload('evt_upgrade_forged', 'pi_upgrade_forged', 1);
    postUpgradeWebhook($payload)->assertServerError();

    expect($subscription->fresh()->plan_code)->toBe('starter')
        ->and($invoice->fresh()->status)->toBe(BillingInvoiceStatus::PaymentPending)
        ->and(BillingWebhookEffect::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('the manual upgrade flow never mutates the stock ledger', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    fakeUpgradeCheckout('pi_upgrade_ledger');
    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));
    app(CreatePayMongoQrPhPayment::class)->handle($invoice);
    $invoice->fresh()->payments()->sole()->update(['external_payment_intent_id' => 'pi_upgrade_ledger']);

    postUpgradeWebhook(upgradePaymentPaidPayload('evt_upgrade_ledger', 'pi_upgrade_ledger', 10_000))
        ->assertNoContent();

    expect(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('the upgrade endpoint returns a QR checkout carrying the requested target plan', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));
    $subscription = upgradeTestSubscription();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($subscription->organization)->for($user)->create(['role' => OrganizationRole::Owner]);
    fakeUpgradeCheckout('pi_upgrade_endpoint');

    $this->actingAs($user)
        ->postJson(route('organizations.billing.upgrade', $subscription->organization), ['plan' => 'growth'])
        ->assertOk()
        ->assertJson([
            'kind' => 'upgrade',
            'target_plan' => 'growth',
            'amount' => 10_000,
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paymongo.test/v1/payment_intents');

    Carbon::setTestNow();
});
