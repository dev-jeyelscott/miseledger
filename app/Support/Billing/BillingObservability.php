<?php

namespace App\Support\Billing;

use App\Models\Organization;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Throwable;

/** Emits safe, structured billing operational signals. */
final class BillingObservability
{
    public function invalidWebhookSignature(): void
    {
        $this->record('warning', 'billing.webhook.invalid_signature', 'provider');
    }

    public function webhookFailure(?Organization $organization, Throwable $exception): void
    {
        $this->failure('billing.webhook.failure', $organization, $exception);
    }

    public function checkoutFailure(Organization $organization, Throwable $exception): void
    {
        $this->failure('billing.checkout.failure', $organization, $exception);
    }

    public function portalFailure(Organization $organization, Throwable $exception): void
    {
        $this->failure('billing.portal.failure', $organization, $exception);
    }

    /** @param array<string, string> $context */
    public function reconciliationMismatch(Organization $organization, string $mismatch, array $context = []): void
    {
        $this->record('warning', 'billing.reconciliation.mismatch', 'application', $organization, [
            'mismatch' => $mismatch,
            ...Arr::only($context, ['local_status', 'remote_status']),
        ]);
    }

    public function reconciliationProviderFailure(Organization $organization, Throwable $exception): void
    {
        $this->failure('billing.reconciliation.provider_failure', $organization, $exception, 'provider');
    }

    public function subscriptionStatusCounts(int $pastDue, int $unpaid): void
    {
        $this->record('info', 'billing.subscription_status_counts', 'application', null, [
            'past_due_count' => $pastDue,
            'unpaid_count' => $unpaid,
        ]);
    }

    private function failure(string $event, ?Organization $organization, Throwable $exception, ?string $failureSource = null): void
    {
        $this->record('error', $event, $failureSource ?? ($exception instanceof ApiErrorException ? 'provider' : 'application'), $organization, [
            'exception' => $exception::class,
            'http_status' => method_exists($exception, 'getHttpStatus') ? $exception->getHttpStatus() : null,
        ]);
    }

    /** @param array<string, int|string|null> $context */
    private function record(string $level, string $event, string $failureSource, ?Organization $organization = null, array $context = []): void
    {
        Log::channel((string) config('billing.logger'))->{$level}('Billing operational signal emitted.', [
            'event' => $event,
            'failure_source' => $failureSource,
            'organization_id' => $organization?->getKey(),
            ...$context,
        ]);
    }
}
