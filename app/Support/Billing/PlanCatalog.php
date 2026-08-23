<?php

namespace App\Support\Billing;

use App\Enums\PlanCode;
use InvalidArgumentException;

/**
 * Resolves trusted, configured Stripe Price IDs into plan definitions.
 * Built entirely from `config('billing.plans')` (see
 * `config/subscription.php`): no plan, feature, or Price ID is hardcoded
 * here, and nothing outside billing infrastructure should read Stripe
 * Price IDs directly.
 *
 * Unknown, malformed, or duplicate Price IDs are excluded from resolution
 * rather than raising an error, so misconfiguration fails closed instead
 * of granting paid functionality.
 */
final readonly class PlanCatalog
{
    private const INTERVALS = ['monthly', 'yearly'];

    /** @var array<string, PlanDefinition> */
    private array $definitions;

    /** @var array<string, string> Stripe Price ID => plan code value. */
    private array $priceIndex;

    /**
     * @param  array<string, mixed>|null  $config  Defaults to config('billing.plans').
     */
    public function __construct(?array $config = null)
    {
        [$this->definitions, $this->priceIndex] = self::build($config ?? (array) config('billing.plans', []));
    }

    /**
     * @return PlanDefinition[]
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(PlanCode $code): ?PlanDefinition
    {
        return $this->definitions[$code->value] ?? null;
    }

    public function resolveByPriceId(string $priceId): ?PlanDefinition
    {
        $code = $this->priceIndex[$priceId] ?? null;

        return $code !== null ? $this->definitions[$code] : null;
    }

    public function resolveIntervalByPriceId(string $priceId): ?string
    {
        $definition = $this->resolveByPriceId($priceId);

        if ($definition === null) {
            return null;
        }

        foreach ($definition->prices as $interval => $configuredPriceId) {
            if ($configuredPriceId === $priceId) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $config
     * @return array{0: array<string, PlanDefinition>, 1: array<string, string>}
     */
    private static function build(array $config): array
    {
        $definitions = [];
        $occurrences = [];

        foreach ($config as $code => $plan) {
            if (! is_string($code) || $code === '' || ! is_array($plan)) {
                continue;
            }

            try {
                $planCode = PlanCode::from($code);
            } catch (InvalidArgumentException) {
                continue;
            }

            $name = $plan['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $features = array_values(array_filter((array) ($plan['features'] ?? []), 'is_string'));
            $limits = array_filter(
                (array) ($plan['limits'] ?? []),
                static fn (mixed $value): bool => $value === null || is_int($value),
            );

            $prices = [];

            foreach (self::INTERVALS as $interval) {
                $priceId = $plan['prices'][$interval] ?? null;
                $prices[$interval] = (is_string($priceId) && $priceId !== '') ? $priceId : null;

                if ($prices[$interval] !== null) {
                    $occurrences[$prices[$interval]] = ($occurrences[$prices[$interval]] ?? 0) + 1;
                }
            }

            $definitions[$code] = new PlanDefinition($planCode, $name, $features, $limits, $prices);
        }

        $priceIndex = [];

        foreach ($definitions as $code => $definition) {
            foreach ($definition->prices as $priceId) {
                if ($priceId !== null && ($occurrences[$priceId] ?? 0) === 1) {
                    $priceIndex[$priceId] = $code;
                }
            }
        }

        return [$definitions, $priceIndex];
    }
}
