<?php

namespace App\Support\Billing;

use Illuminate\Support\Arr;
use RuntimeException;

final class BillingConfigurationValidator
{
    /**
     * Reject incomplete or non-live billing configuration before production
     * requests can issue Stripe API calls or accept webhook traffic.
     *
     * @param  array<string, mixed>  $configuration
     */
    public static function validateProduction(array $configuration): void
    {
        /** @var list<string> $requiredKeys */
        $requiredKeys = Arr::get($configuration, 'required_in_production', []);
        $missing = collect($requiredKeys)
            ->reject(fn (string $key): bool => filled(Arr::get($configuration, $key)))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing required billing configuration: '.$missing->implode(', '));
        }

        $key = Arr::get($configuration, 'stripe.key');
        $secret = Arr::get($configuration, 'stripe.secret');
        $webhookSecret = Arr::get($configuration, 'stripe.webhook_secret');

        if (! is_string($key) || preg_match('/^pk_live_[^\\s]+$/', $key) !== 1
            || ! is_string($secret) || preg_match('/^(?:sk|rk)_live_[^\\s]+$/', $secret) !== 1
            || Arr::get($configuration, 'stripe.mode') !== 'live') {
            throw new RuntimeException('Production billing configuration requires matching live Stripe API keys.');
        }

        if (! is_string($webhookSecret) || preg_match('/^whsec_[^\\s]+$/', $webhookSecret) !== 1) {
            throw new RuntimeException('Production billing configuration requires a live Stripe webhook secret.');
        }
    }
}
