<?php

namespace App\Http\Middleware;

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
        try {
            WebhookSignature::verifyHeader($request->getContent(), $request->header('Stripe-Signature'), config('cashier.webhook.secret'), config('cashier.webhook.tolerance'));
        } catch (SignatureVerificationException $exception) {
            $this->observability->invalidWebhookSignature();

            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return $next($request);
    }
}
