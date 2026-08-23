<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * A stable internal plan identifier. Plan codes are configuration-owned
 * (see `config/subscription.php`): they are never Stripe product/price
 * names, and no fixed set of codes is hardcoded here because which plans
 * are sold is an unresolved business decision left to configuration.
 */
final readonly class PlanCode
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("Invalid plan code [{$value}].");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
