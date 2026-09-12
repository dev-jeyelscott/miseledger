<?php

namespace App\Http\Middleware;

use App\Enums\BillingProvider;
use App\Support\Billing\BillingObservability;
use Closure;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ObserveStripeWebhookSignature
{
    public function __construct(private readonly BillingObservability $observability) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = config('cashier.webhook.secret');

        if (! is_string($secret) || preg_match('/^whsec_[^\s]+$/D', $secret) !== 1) {
            $this->reject();
        }

        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $secret,
                config('cashier.webhook.tolerance'),
            );
        } catch (SignatureVerificationException $exception) {
            $this->reject($exception);
        }

        return $next($request);
    }

    private function reject(?SignatureVerificationException $exception = null): never
    {
        $this->observability->invalidWebhookSignature(BillingProvider::Stripe);

        throw new AccessDeniedHttpException(
            'Invalid Stripe webhook signature.',
            $exception,
        );
    }
}
