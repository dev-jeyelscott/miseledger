<?php

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

function postCoexistenceWebhook(array $payload, string $secret = 'whsec_billing_coexistence'): TestResponse
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

function coexistenceSubscriptionUpdatedPayload(string $eventId, string $customerId, string $subscriptionId, array $overrides = []): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.updated',
        'livemode' => false,
        'data' => [
            'object' => array_merge([
                'id' => $subscriptionId,
                'customer' => $customerId,
                'status' => 'active',
                'livemode' => false,
                'items' => ['data' => [[
                    'id' => 'si_sync_coexistence',
                    'quantity' => 1,
                    'price' => ['id' => 'price_coexistence', 'product' => 'prod_coexistence'],
                ]]],
                'trial_end' => null,
                'current_period_end' => now()->addDays(30)->timestamp,
                'cancel_at_period_end' => false,
                'cancel_at' => null,
                'canceled_at' => null,
            ], $overrides),
        ],
    ];
}

test('Cashier subscriptions/subscription_items and the durable projection coexist for the same organization', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_coexistence']);

    $cashierSubscription = $organization->subscriptions()->create([
        'type' => (string) config('billing.subscription_type'),
        'stripe_id' => 'sub_cashier_coexistence',
        'stripe_status' => 'active',
        'stripe_price' => 'price_coexistence',
        'quantity' => 1,
    ]);
    $cashierSubscription->items()->create([
        'stripe_id' => 'si_coexistence',
        'stripe_product' => 'prod_coexistence',
        'stripe_price' => 'price_coexistence',
        'quantity' => 1,
    ]);

    $billingCustomer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::Stripe,
        'external_customer_id' => 'cus_coexistence',
    ]);
    BillingSubscription::factory()->for($billingCustomer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::Stripe,
        'external_subscription_id' => 'sub_projection_coexistence',
    ]);

    expect($organization->subscriptions()->count())->toBe(1)
        ->and($organization->billingSubscriptions()->count())->toBe(1)
        ->and($organization->fresh()->stripe_id)->toBe('cus_coexistence');
});

test('a synchronized Stripe webhook populates the durable projection without mutating Cashier state destructively', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_sync_coexistence']);

    $organization->subscriptions()->create([
        'type' => (string) config('billing.subscription_type'),
        'stripe_id' => 'sub_sync_coexistence',
        'stripe_status' => 'active',
        'stripe_price' => 'price_coexistence',
        'quantity' => 1,
    ]);

    $payload = coexistenceSubscriptionUpdatedPayload('evt_sync_coexistence', 'cus_sync_coexistence', 'sub_sync_coexistence');

    postCoexistenceWebhook($payload)->assertOk();

    $billingCustomer = BillingCustomer::query()
        ->where('organization_id', $organization->getKey())
        ->where('provider', BillingProvider::Stripe)
        ->sole();

    $billingSubscription = BillingSubscription::query()
        ->where('provider', BillingProvider::Stripe)
        ->where('external_subscription_id', 'sub_sync_coexistence')
        ->sole();

    expect($billingCustomer->external_customer_id)->toBe('cus_sync_coexistence')
        ->and($billingSubscription->provider_status)->toBe('active')
        ->and($billingSubscription->external_plan_id)->toBe('price_coexistence')
        ->and($organization->subscriptions()->sole()->stripe_id)->toBe('sub_sync_coexistence')
        ->and($organization->subscriptions()->sole()->stripe_status)->toBe('active');
});
