<?php

use App\Enums\BillingProvider;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;

test('billing signals are categorized, tenant-safe, and redacted', function () {
    $organization = Organization::factory()->create();
    $observability = app(BillingObservability::class);
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $observability->checkoutFailure($organization, BillingProvider::Stripe, new ApiConnectionException('sk_test_secret whsec_secret token payload'));
    $observability->portalFailure($organization, BillingProvider::Stripe, new RuntimeException('token payload'));
    $observability->webhookFailure($organization, BillingProvider::PayMongo, new RuntimeException('raw provider payload token'), 'evt_safe', 'active', false);
    $observability->invalidWebhookSignature(BillingProvider::PayMongo);
    $observability->reconciliationMismatch($organization, BillingProvider::Stripe, 'subscription_mismatch', ['subscription_status' => 'past_due', 'livemode' => false]);
    $observability->subscriptionStatusCounts(2, 1);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Billing operational signal emitted.'
        && $context['event'] === 'billing.checkout.failure'
        && $context['billing_provider'] === 'stripe'
        && $context['billing_operation'] === 'checkout'
        && $context['failure_source'] === 'provider'
        && $context['organization_id'] === $organization->id
        && ! array_key_exists('message', $context)
        && ! str_contains(json_encode($context), 'sk_test_secret')
        && ! str_contains(json_encode($context), 'whsec_secret')
        && ! str_contains(json_encode($context), 'token payload'),
    )->once();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.portal.failure'
        && $context['billing_provider'] === 'stripe'
        && $context['failure_source'] === 'application'
        && $context['organization_id'] === $organization->id,
    )->once();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.webhook.failure'
        && $context['billing_provider'] === 'paymongo'
        && $context['external_event_id'] === 'evt_safe'
        && $context['subscription_status'] === 'active'
        && $context['livemode'] === false
        && $context['failure_source'] === 'application'
        && $context['organization_id'] === $organization->id
        && ! str_contains(json_encode($context), 'raw provider payload'),
    )->once();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.webhook.invalid_signature'
        && $context['billing_provider'] === 'paymongo'
        && $context['failure_source'] === 'provider'
        && $context['organization_id'] === null,
    )->once();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.reconciliation.mismatch'
        && $context['mismatch'] === 'subscription_mismatch'
        && $context['billing_provider'] === 'stripe'
        && $context['subscription_status'] === 'past_due'
        && $context['organization_id'] === $organization->id
        && ! array_key_exists('stripe_subscription_id', $context),
    )->once();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.subscription_status_counts'
        && $context['past_due_count'] === 2
        && $context['unpaid_count'] === 1,
    )->once();
});
