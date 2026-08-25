<?php

use App\Actions\Billing\CreateRenewalInvoice;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

$manualRenewalInvoiceBillingConfig = null;

beforeEach(function () use (&$manualRenewalInvoiceBillingConfig): void {
    $manualRenewalInvoiceBillingConfig = config('billing');

    Config::set('billing.currency', 'PHP');
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 49_900, 'yearly' => 499_000]);
});

afterEach(function () use (&$manualRenewalInvoiceBillingConfig): void {
    Config::set('billing', $manualRenewalInvoiceBillingConfig);
});

function manualRenewalSubscription(array $attributes = []): BillingSubscription
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create(array_merge([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'current_period_ends_at' => Carbon::parse('2026-09-26 00:00:00', 'UTC'),
    ], $attributes));
}

test('it creates one invoice for the next entitlement period using the catalog amount', function (): void {
    $subscription = manualRenewalSubscription();

    $invoice = app(CreateRenewalInvoice::class)->handle(
        $subscription,
        Carbon::parse('2026-09-20 00:00:00', 'UTC'),
    );

    expect($invoice->amount)->toBe(49_900)
        ->and($invoice->currency)->toBe('PHP')
        ->and($invoice->status)->toBe(BillingInvoiceStatus::Pending)
        ->and($invoice->period_starts_at->toISOString())->toBe('2026-09-26T00:00:00.000000Z')
        ->and($invoice->period_ends_at->toISOString())->toBe('2026-10-26T00:00:00.000000Z');
});

test('it normalizes a lowercase configured currency to ISO uppercase', function (): void {
    Config::set('billing.currency', 'php');
    $subscription = manualRenewalSubscription();

    $invoice = app(CreateRenewalInvoice::class)->handle($subscription);

    expect($invoice->currency)->toBe('PHP');
});

test('repeated renewal requests reuse the invoice for the same period', function (): void {
    $subscription = manualRenewalSubscription();
    $activationPoint = Carbon::parse('2026-09-20 00:00:00', 'UTC');

    $first = app(CreateRenewalInvoice::class)->handle($subscription, $activationPoint);
    $second = app(CreateRenewalInvoice::class)->handle($subscription, $activationPoint);

    expect($second->is($first))->toBeTrue()
        ->and($subscription->invoices()->count())->toBe(1);
});

test('automatic subscriptions cannot create manual renewal invoices', function (): void {
    $subscription = manualRenewalSubscription(['collection_method' => BillingCollectionMethod::Automatic]);

    expect(fn () => app(CreateRenewalInvoice::class)->handle($subscription))
        ->toThrow(RuntimeException::class, 'This subscription cannot be renewed manually.');
});
