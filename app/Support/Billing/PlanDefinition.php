<?php

namespace App\Support\Billing;

use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use OutOfBoundsException;

/**
 * One resolved plan from the billing configuration catalog: a stable plan
 * code, its display name, granted feature codes, quantitative limits, and
 * provider-specific external plan IDs configured per billing interval.
 */
final readonly class PlanDefinition
{
    /**
     * @param  list<string>  $features
     * @param  array<string, int|null>  $limits  Null means explicitly unlimited.
     * @param  array<string, array<string, string|null>>  $providers  Keyed by provider then interval.
     * @param  array<string, string|null>  $prices  Transitional Stripe compatibility view.
     * @param  array<string, int|null>  $manualAmounts  Minor-unit collection amounts keyed by interval.
     */
    public function __construct(
        public PlanCode $code,
        public string $name,
        public array $features,
        public array $limits,
        public array $providers,
        public array $prices,
        public array $manualAmounts,
    ) {}

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * Null means the limit is explicitly configured as unlimited. Throws
     * when the limit was never declared for this plan, so "unlimited" is
     * never inferred from an omission.
     */
    public function limit(string $key): ?int
    {
        if (! array_key_exists($key, $this->limits)) {
            throw new OutOfBoundsException("Undefined limit [{$key}] for plan [{$this->code}].");
        }

        return $this->limits[$key];
    }

    public function priceId(string $interval): ?string
    {
        return $this->externalPlanId(BillingProvider::Stripe, $interval);
    }

    public function externalPlanId(BillingProvider|string $provider, string $interval): ?string
    {
        $provider = is_string($provider) ? BillingIdentity::provider($provider) : $provider;

        if ($provider === null || ! in_array($interval, ['monthly', 'yearly'], true)) {
            return null;
        }

        return $this->providers[$provider->value][$interval] ?? null;
    }

    public function manualAmount(string $interval): ?int
    {
        return $this->manualAmounts[$interval] ?? null;
    }
}
