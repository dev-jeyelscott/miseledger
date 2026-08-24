<?php

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use LogicException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

final class BillingAuditTrailStripeClient implements ClientInterface
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<int, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if (str_contains($absUrl, '/v1/subscription_items/')) {
            return [json_encode([
                'id' => 'si_billing_audit',
                'object' => 'subscription_item',
                'current_period_end' => now()->addMonth()->timestamp,
            ]), 200, []];
        }

        if (str_contains($absUrl, '/v1/customers/')) {
            return [json_encode(['id' => 'cus_billing_audit', 'object' => 'customer']), 200, []];
        }

        if (str_contains($absUrl, '/v1/checkout/sessions')) {
            return [json_encode([
                'id' => 'cs_billing_audit',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/cs_billing_audit',
            ]), 200, []];
        }

        throw new RuntimeException("Unexpected Stripe request: {$method} {$absUrl}");
    }
}

function postBillingAuditWebhook(array $payload): TestResponse
{
    $secret = 'whsec_billing_audit';
    Config::set('cashier.webhook.secret', $secret);

    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('cashier.webhook'), [], [], [], [
        'HTTP_STRIPE-SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function billingAuditSubscriptionPayload(string $eventId, string $type, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $eventId,
        'type' => $type,
        'data' => ['object' => [
            'id' => 'sub_billing_audit',
            'customer' => 'cus_billing_audit',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => now()->addMonth()->timestamp,
            'items' => ['data' => [[
                'id' => 'si_billing_audit',
                'price' => ['id' => 'price_billing_audit', 'product' => 'prod_billing_audit'],
                'quantity' => 1,
            ]]],
        ]],
    ], $overrides);
}

afterEach(function (): void {
    ApiRequestor::setHttpClient(null);
});

test('checkout start records its initiating actor and preserves immutable audit history', function () {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'price_billing_audit', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);
    Config::set('cashier.secret', 'sk_test_billing_audit');
    ApiRequestor::setHttpClient(new BillingAuditTrailStripeClient);

    $organization = Organization::factory()->create(['stripe_id' => 'cus_billing_audit']);
    $actor = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($actor)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($actor)->post(route('organizations.billing.checkout', $organization), [
        'plan' => 'starter',
        'interval' => 'monthly',
    ])->assertRedirect('https://checkout.stripe.com/c/pay/cs_billing_audit');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->organization_id)->toBe($organization->id)
        ->and($auditLog->actor_id)->toBe($actor->id)
        ->and($auditLog->action)->toBe('billing.checkout.started')
        ->and($auditLog->after_data)->toBe(['plan' => 'starter', 'interval' => 'monthly'])
        ->and(fn () => $auditLog->update(['action' => 'altered']))->toThrow(LogicException::class)
        ->and(fn () => $auditLog->delete())->toThrow(LogicException::class);
});

test('provider lifecycle audits are tenant-scoped, deduplicated, and exclude sensitive payment data', function () {
    Notification::fake();
    Config::set('cashier.secret', 'sk_test_billing_audit');
    ApiRequestor::setHttpClient(new BillingAuditTrailStripeClient);

    $organization = Organization::factory()->create(['stripe_id' => 'cus_billing_audit']);
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_billing_audit_other']);

    $payloads = [
        billingAuditSubscriptionPayload('evt_audit_started', 'customer.subscription.created'),
        billingAuditSubscriptionPayload('evt_audit_plan_changed', 'customer.subscription.updated', [
            'data' => ['previous_attributes' => ['items' => ['data' => [['price' => ['id' => 'price_previous']]]]]],
        ]),
        billingAuditSubscriptionPayload('evt_audit_scheduled', 'customer.subscription.updated', [
            'data' => ['object' => ['cancel_at_period_end' => true]],
        ]),
        billingAuditSubscriptionPayload('evt_audit_resumed', 'customer.subscription.updated', [
            'data' => ['previous_attributes' => ['cancel_at_period_end' => true]],
        ]),
        billingAuditSubscriptionPayload('evt_audit_past_due', 'customer.subscription.updated', [
            'data' => ['object' => [
                'status' => 'past_due',
                'payment_intent' => 'pi_secret_value',
                'default_payment_method' => ['card' => ['last4' => '4242']],
            ]],
        ]),
        billingAuditSubscriptionPayload('evt_audit_recovered', 'customer.subscription.updated', [
            'data' => ['previous_attributes' => ['status' => 'past_due']],
        ]),
        billingAuditSubscriptionPayload('evt_audit_ended', 'customer.subscription.deleted'),
    ];

    foreach ($payloads as $payload) {
        postBillingAuditWebhook($payload)->assertOk();
    }

    postBillingAuditWebhook($payloads[4])->assertOk();

    $auditLogs = AuditLog::query()->whereBelongsTo($organization)->get();

    expect($auditLogs)->toHaveCount(7)
        ->and($auditLogs->pluck('action')->sort()->values()->all())->toBe([
            'billing.payment.recovered',
            'billing.subscription.cancellation_scheduled',
            'billing.subscription.ended',
            'billing.subscription.past_due',
            'billing.subscription.plan_changed',
            'billing.subscription.resumed',
            'billing.subscription.started',
        ])
        ->and($auditLogs->every(fn (AuditLog $auditLog): bool => $auditLog->actor_id === null
            && $auditLog->after_data === ['origin' => 'stripe_webhook', 'lifecycle_event' => match ($auditLog->action) {
                'billing.subscription.started' => 'subscription_started',
                'billing.subscription.plan_changed' => 'plan_changed',
                'billing.subscription.cancellation_scheduled' => 'scheduled_cancellation',
                'billing.subscription.resumed' => 'subscription_resumed',
                'billing.subscription.ended' => 'subscription_ended',
                'billing.subscription.past_due' => 'payment_failed',
                'billing.payment.recovered' => 'recovered',
            }]))->toBeTrue()
        ->and(AuditLog::query()->whereBelongsTo($otherOrganization)->count())->toBe(0)
        ->and($auditLogs->toJson())->not->toContain('pi_secret_value')
        ->and($auditLogs->toJson())->not->toContain('4242');
});
