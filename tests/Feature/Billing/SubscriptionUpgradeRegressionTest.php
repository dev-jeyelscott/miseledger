<?php

use App\Actions\Billing\CreateOrganizationCheckoutSession;
use App\Actions\Billing\CreateRenewalInvoice;
use App\Actions\Billing\EnsureManualPayMongoSubscription;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 49_900, 'yearly' => 499_000]);
});

test('renewal invoices still default to invoice_type renewal with no target plan', function (): void {
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'current_period_ends_at' => Carbon::parse('2026-09-26 00:00:00', 'UTC'),
    ]);

    $invoice = app(CreateRenewalInvoice::class)->handle($subscription, Carbon::parse('2026-09-20 00:00:00', 'UTC'));

    expect($invoice->fresh()->invoice_type)->toBe(BillingInvoiceType::Renewal)
        ->and($invoice->target_plan_code)->toBeNull()
        ->and($invoice->status)->toBe(BillingInvoiceStatus::Pending);
});

test('the Stripe/general acquisition guard still rejects checkout for an already-subscribed organization', function (): void {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_existing']);
    $organization->subscriptions()->create([
        'type' => (string) config('billing.subscription_type'),
        'stripe_id' => 'sub_existing',
        'stripe_status' => 'active',
        'stripe_price' => 'price_existing',
        'quantity' => 1,
    ]);
    $user = User::factory()->create();

    expect(fn () => app(CreateOrganizationCheckoutSession::class)->handle(
        $organization,
        $user,
        PlanCode::from('starter'),
        'monthly',
    ))->toThrow(ValidationException::class);
});

test('EnsureManualPayMongoSubscription still returns an existing subscription unmodified regardless of a different plan argument', function (): void {
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
    ]);
    $user = User::factory()->create();

    $returned = app(EnsureManualPayMongoSubscription::class)->handle(
        $organization,
        $user,
        PlanCode::from('growth'),
        'yearly',
    );

    expect($returned->is($subscription))->toBeTrue()
        ->and($returned->plan_code)->toBe('starter')
        ->and($returned->interval)->toBe('monthly');
});
