<?php

use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiConnectionException;

test('billing signals are categorized, tenant-safe, and redacted', function () {
    $organization = Organization::factory()->create();
    $observability = app(BillingObservability::class);
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $observability->checkoutFailure($organization, new ApiConnectionException('sk_test_secret whsec_secret token payload'));
    $observability->portalFailure($organization, new RuntimeException('token payload'));
    $observability->webhookFailure($organization, new RuntimeException('raw provider payload token'));
    $observability->invalidWebhookSignature();
    $observability->reconciliationMismatch($organization, 'subscription_mismatch', ['stripe_subscription_id' => 'sub_123']);
    $observability->subscriptionStatusCounts(2, 1);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Billing operational signal emitted.'
        && $context['event'] === 'billing.checkout.failure'
        && $context['failure_source'] === 'provider'
        && $context['organization_id'] === $organization->id
        && ! array_key_exists('message', $context)
        && ! str_contains(json_encode($context), 'sk_test_secret')
        && ! str_contains(json_encode($context), 'whsec_secret')
        && ! str_contains(json_encode($context), 'token payload'),
    )->once();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.portal.failure'
        && $context['failure_source'] === 'application'
        && $context['organization_id'] === $organization->id,
    )->once();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.webhook.failure'
        && $context['failure_source'] === 'application'
        && $context['organization_id'] === $organization->id
        && ! str_contains(json_encode($context), 'raw provider payload'),
    )->once();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.webhook.invalid_signature'
        && $context['failure_source'] === 'provider'
        && $context['organization_id'] === null,
    )->once();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.reconciliation.mismatch'
        && $context['mismatch'] === 'subscription_mismatch'
        && $context['organization_id'] === $organization->id
        && ! array_key_exists('stripe_subscription_id', $context),
    )->once();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $context['event'] === 'billing.subscription_status_counts'
        && $context['past_due_count'] === 2
        && $context['unpaid_count'] === 1,
    )->once();
});
