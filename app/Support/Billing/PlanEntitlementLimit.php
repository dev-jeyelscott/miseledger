<?php

namespace App\Support\Billing;

/**
 * The resolved value of a plan's quantitative limit for one dimension.
 * Three states are distinguished so callers never confuse "unlimited"
 * with "we don't know": an explicit finite ceiling, an explicit unlimited
 * grant, and unavailable (no plan, unknown plan, or an undeclared limit
 * key), which paid-only callers must treat as denying the capability.
 */
final readonly class PlanEntitlementLimit
{
    private function __construct(
        public bool $isFinite,
        public bool $isUnlimited,
        public bool $isUnavailable,
        public ?int $value,
    ) {}

    public static function finite(int $value): self
    {
        return new self(isFinite: true, isUnlimited: false, isUnavailable: false, value: $value);
    }

    public static function unlimited(): self
    {
        return new self(isFinite: false, isUnlimited: true, isUnavailable: false, value: null);
    }

    public static function unavailable(): self
    {
        return new self(isFinite: false, isUnlimited: false, isUnavailable: true, value: null);
    }
}
