<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

/**
 * Signs a Stripe event payload the same way the Stripe CLI/API would, using
 * Cashier's supported `Stripe-Signature` verification mechanism, and posts
 * it to Cashier's auto-registered webhook route.
 */
function postSignedStripeWebhook(array $payload, string $secret = 'whsec_test_secret'): TestResponse
{
    Config::set('cashier.webhook.secret', $secret);

    return signAndPostStripeWebhook($payload, $secret);
}

function signAndPostStripeWebhook(array $payload, string $secret): TestResponse
{
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

function subscriptionUpdatedPayload(string $customerId, string $subscriptionId, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'evt_'.str()->random(16),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => $subscriptionId,
                'customer' => $customerId,
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_'.str()->random(14),
                            'price' => [
                                'id' => 'price_starter_monthly',
                                'product' => 'prod_starter',
                            ],
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

test('a validly signed subscription event synchronizes state for the matched organization', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_matched']);
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_other']);

    $payload = subscriptionUpdatedPayload('cus_matched', 'sub_valid_123');

    $response = postSignedStripeWebhook($payload);

    $response->assertOk();

    $subscription = $organization->fresh()->subscriptions()->where('stripe_id', 'sub_valid_123')->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->stripe_status)->toBe('active');
    expect($subscription->stripe_price)->toBe('price_starter_monthly');

    expect($otherOrganization->fresh()->subscriptions()->count())->toBe(0);
});

test('an invalidly signed event is rejected without changing subscription state', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_matched']);

    Config::set('cashier.webhook.secret', 'whsec_test_secret');

    $payload = subscriptionUpdatedPayload('cus_matched', 'sub_invalid_123');

    $response = signAndPostStripeWebhook($payload, 'whsec_wrong_secret');

    $response->assertForbidden();

    expect($organization->fresh()->subscriptions()->count())->toBe(0);
});

test('a duplicate delivery of the same standard webhook event remains safe', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_matched']);

    $payload = subscriptionUpdatedPayload('cus_matched', 'sub_duplicate_123');

    postSignedStripeWebhook($payload)->assertOk();
    postSignedStripeWebhook($payload)->assertOk();

    $subscriptions = $organization->fresh()->subscriptions()->where('stripe_id', 'sub_duplicate_123')->get();

    expect($subscriptions)->toHaveCount(1);
    expect($subscriptions->first()->stripe_status)->toBe('active');
});

test('an event for one organization cannot update another organization subscription state', function () {
    $targetOrganization = Organization::factory()->create(['stripe_id' => 'cus_target']);
    $bystanderOrganization = Organization::factory()->create(['stripe_id' => 'cus_bystander']);

    // Any request-carried identity data is ignored; only the Stripe customer
    // id in the signed payload determines the tenant.
    $payload = subscriptionUpdatedPayload('cus_target', 'sub_isolation_123', [
        'data' => [
            'object' => [
                'metadata' => [
                    'organization_id' => (string) $bystanderOrganization->id,
                ],
            ],
        ],
    ]);

    postSignedStripeWebhook($payload)->assertOk();

    expect($targetOrganization->fresh()->subscriptions()->where('stripe_id', 'sub_isolation_123')->exists())->toBeTrue();
    expect($bystanderOrganization->fresh()->subscriptions()->count())->toBe(0);
});
