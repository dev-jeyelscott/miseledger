<?php

namespace App\Http\Middleware;

use App\Enums\BillingProvider;
use App\Support\Billing\BillingObservability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VerifyPayMongoWebhookSignature
{
    public function __construct(private readonly BillingObservability $observability) {}

    /** @param Closure(Request): mixed $next */
    public function handle(Request $request, Closure $next): mixed
    {
        $mode = config('billing.providers.paymongo.mode');
        $secret = config('billing.providers.paymongo.webhook_secret');

        if (! in_array($mode, ['test', 'live'], true) || ! is_string($secret) || $secret === '') {
            $this->reject();
        }

        $parts = $this->signatureParts($request->header('Paymongo-Signature'));
        $signature = $parts[$mode === 'live' ? 'li' : 'te'] ?? null;

        if ($parts === null || ! is_string($signature) || preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1) {
            $this->reject();
        }

        $expected = hash_hmac('sha256', $parts['t'].'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            $this->reject();
        }

        return $next($request);
    }

    /** @return array{t: string, te: string, li: string}|null */
    private function signatureParts(?string $header): ?array
    {
        if (! is_string($header)) {
            return null;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (! in_array($key, ['t', 'te', 'li'], true) || ! is_string($value) || array_key_exists($key, $parts)) {
                return null;
            }

            $parts[$key] = $value;
        }

        if (array_keys($parts) !== ['t', 'te', 'li'] || preg_match('/^[1-9][0-9]*$/D', $parts['t']) !== 1) {
            return null;
        }

        return $parts;
    }

    private function reject(): never
    {
        $this->observability->invalidWebhookSignature(BillingProvider::PayMongo);

        throw new AccessDeniedHttpException('Invalid PayMongo webhook signature.');
    }
}
