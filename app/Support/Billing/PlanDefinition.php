<?php

namespace App\Support\Billing;

use App\Enums\PlanCode;
use OutOfBoundsException;

/**
 * One resolved plan from the billing configuration catalog: a stable plan
 * code, its display name, granted feature codes, quantitative limits, and
 * the Stripe Price ID configured per billing interval.
 */
final readonly class PlanDefinition
{
    /**
     * @param  list<string>  $features
     * @param  array<string, int|null>  $limits  Null means explicitly unlimited.
     * @param  array<string, string|null>  $prices  Keyed by interval ("monthly", "yearly").
     */
    public function __construct(
        public PlanCode $code,
        public string $name,
        public array $features,
        public array $limits,
        public array $prices,
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
        return $this->prices[$interval] ?? null;
    }
}
