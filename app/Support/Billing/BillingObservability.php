<?php

namespace App\Support\Billing;

use App\Enums\BillingProvider;
use App\Models\Organization;
use App\Support\Billing\Providers\PayMongoRequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Throwable;

/** Emits safe, structured billing operational signals. */
final class BillingObservability
{
    public function invalidWebhookSignature(BillingProvider $provider): void
    {
        $this->record('warning', 'billing.webhook.invalid_signature', $provider, 'webhook_signature');
    }

    public function webhookFailure(?Organization $organization, BillingProvider $provider, Throwable $exception, ?string $externalEventId = null, ?string $status = null, ?bool $livemode = null): void
    {
        $this->failure('billing.webhook.failure', $organization, $provider, 'webhook', $exception, $externalEventId, $status, $livemode);
    }

    public function checkoutFailure(Organization $organization, BillingProvider $provider, Throwable $exception): void
    {
        $this->failure('billing.checkout.failure', $organization, $provider, 'checkout', $exception);
    }

    public function portalFailure(Organization $organization, BillingProvider $provider, Throwable $exception): void
    {
        $this->failure('billing.portal.failure', $organization, $provider, 'portal', $exception);
    }

    /** @param array<string, bool|string|null> $context */
    public function reconciliationMismatch(Organization $organization, BillingProvider $provider, string $mismatch, array $context = []): void
    {
        $this->record('warning', 'billing.reconciliation.mismatch', $provider, 'reconciliation', $organization, null, $context['subscription_status'] ?? null, $context['livemode'] ?? null, [
            'mismatch' => $mismatch,
            ...Arr::only($context, ['local_status', 'remote_status']),
        ]);
    }

    public function reconciliationProviderFailure(Organization $organization, BillingProvider $provider, Throwable $exception, ?string $status = null, ?bool $livemode = null): void
    {
        $this->failure('billing.reconciliation.provider_failure', $organization, $provider, 'reconciliation', $exception, null, $status, $livemode);
    }

    public function subscriptionStatusCounts(int $pastDue, int $unpaid): void
    {
        $this->record('info', 'billing.subscription_status_counts', null, 'subscription_monitor', null, null, null, null, [
            'past_due_count' => $pastDue,
            'unpaid_count' => $unpaid,
        ]);
    }

    public function duplicateWebhookEvent(Organization $organization, BillingProvider $provider, string $externalEventId, ?string $status = null, ?bool $livemode = null): void
    {
        $this->record('info', 'billing.webhook.duplicate', $provider, 'webhook', $organization, $externalEventId, $status, $livemode);
    }

    public function queueFailure(int $organizationId, BillingProvider $provider, string $externalEventId, Throwable $exception): void
    {
        $this->failure('billing.notification.failure', Organization::find($organizationId), $provider, 'notification', $exception, $externalEventId);
    }

    public function staleNotificationClaim(Organization $organization, string $externalEventId, BillingProvider|string $provider): void
    {
        $this->record('warning', 'billing.notification.stale_claim', $provider instanceof BillingProvider ? $provider : BillingProvider::from($provider), 'notification', $organization, $externalEventId);
    }

    private function failure(string $event, ?Organization $organization, BillingProvider $provider, string $operation, Throwable $exception, ?string $externalEventId = null, ?string $status = null, ?bool $livemode = null): void
    {
        $this->record('error', $event, $provider, $operation, $organization, $externalEventId, $status, $livemode, [
            'failure_source' => $exception instanceof ApiErrorException || $exception instanceof PayMongoRequestException ? 'provider' : 'application',
            'exception' => $exception::class,
            'http_status' => $exception instanceof PayMongoRequestException ? $exception->httpStatus : (method_exists($exception, 'getHttpStatus') ? $exception->getHttpStatus() : null),
            'provider_api_operation' => $exception instanceof PayMongoRequestException ? $exception->operation : null,
        ]);
    }

    /** @param array<string, int|string|null> $context */
    private function record(string $level, string $event, ?BillingProvider $provider, string $operation, ?Organization $organization = null, ?string $externalEventId = null, ?string $status = null, ?bool $livemode = null, array $context = []): void
    {
        Log::channel((string) config('billing.logger'))->{$level}('Billing operational signal emitted.', [
            'event' => $event,
            'billing_provider' => $provider?->value,
            'billing_operation' => $operation,
            'organization_id' => $organization?->getKey(),
            'external_event_id' => $externalEventId,
            'subscription_status' => $status,
            'livemode' => $livemode,
            ...$context,
        ]);
    }
}
