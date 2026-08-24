<?php

namespace App\Http\Controllers\Billing;

use App\Http\Middleware\ObserveStripeWebhookSignature;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StripeWebhookController extends WebhookController
{
    public function __construct(private readonly BillingObservability $observability)
    {
        if (config('cashier.webhook.secret')) {
            $this->middleware(ObserveStripeWebhookSignature::class);
        }
    }

    public function handleWebhook(Request $request): Response
    {
        try {
            return parent::handleWebhook($request);
        } catch (Throwable $exception) {
            $this->observability->webhookFailure($this->organizationFor($request), $exception);

            throw $exception;
        }
    }

    private function organizationFor(Request $request): ?Organization
    {
        $customerId = data_get($request->json()->all(), 'data.object.customer');

        return is_string($customerId)
            ? Organization::query()->where('stripe_id', $customerId)->first()
            : null;
    }
}
