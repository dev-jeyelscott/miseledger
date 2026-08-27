<?php

namespace App\Support\Billing;

use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use InvalidArgumentException;

/**
 * Resolves provider-specific external plan identifiers to stable MiseLedger plans.
 */
final readonly class PlanCatalog
{
    private const INTERVALS = ['monthly', 'yearly'];

    /** @var array<string, PlanDefinition> */
    private array $definitions;

    /** @var array<string, array<string, string>> */
    private array $externalPlanIndexes;

    /** @param array<int|string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        [$this->definitions, $this->externalPlanIndexes] = self::build(
            $config ?? (array) config('billing.plans', []),
        );
    }

    /** @return list<PlanDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(PlanCode $code): ?PlanDefinition
    {
        return $this->definitions[$code->value] ?? null;
    }

    public function externalPlanId(
        PlanCode $code,
        BillingProvider|string $provider,
        string $interval,
    ): ?string {
        return $this->get($code)?->externalPlanId(
            $provider,
            $interval,
        );
    }

    public function resolveExternalPlan(
        BillingProvider|string $provider,
        string $externalPlanId,
    ): ?PlanDefinition {
        $provider = self::provider($provider);

        if ($provider === null
            || ! self::isValidExternalPlanId(
                $externalPlanId,
                $provider,
            )) {
            return null;
        }

        $code = $this
            ->externalPlanIndexes[$provider->value][$externalPlanId]
            ?? null;

        return $code !== null
            ? $this->definitions[$code]
            : null;
    }

    public function resolveExternalPlanInterval(
        BillingProvider|string $provider,
        string $externalPlanId,
    ): ?string {
        $provider = self::provider($provider);

        $definition = $provider !== null
            ? $this->resolveExternalPlan(
                $provider,
                $externalPlanId,
            )
            : null;

        if ($definition === null) {
            return null;
        }

        foreach (self::INTERVALS as $interval) {
            if ($definition->externalPlanId(
                $provider,
                $interval,
            ) === $externalPlanId) {
                return $interval;
            }
        }

        return null;
    }

    public function resolveByPriceId(
        string $priceId,
    ): ?PlanDefinition {
        return $this->resolveExternalPlan(
            BillingProvider::Stripe,
            $priceId,
        );
    }

    public function resolveIntervalByPriceId(
        string $priceId,
    ): ?string {
        return $this->resolveExternalPlanInterval(
            BillingProvider::Stripe,
            $priceId,
        );
    }

    /**
     * @param  array<int|string, mixed>  $config
     * @return array{
     *     0: array<string, PlanDefinition>,
     *     1: array<string, array<string, string>>
     * }
     */
    private static function build(array $config): array
    {
        $definitions = [];
        $occurrences = [];

        foreach ($config as $code => $plan) {
            if (! is_string($code)
                || $code === ''
                || ! is_array($plan)) {
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

            $tier = $plan['tier'] ?? null;

            if (! is_int($tier) || $tier < 1) {
                continue;
            }

            $features = array_values(
                array_filter(
                    (array) ($plan['features'] ?? []),
                    'is_string',
                ),
            );

            $limits = array_filter(
                (array) ($plan['limits'] ?? []),
                static fn (mixed $value): bool => $value === null || is_int($value),
            );

            $providers = self::providerPlans($plan);
            $manualAmounts = self::manualAmounts($plan);

            foreach ($providers as $provider => $intervals) {
                foreach ($intervals as $externalPlanId) {
                    if ($externalPlanId !== null) {
                        $occurrences[$provider][$externalPlanId] =
                            (
                                $occurrences[$provider][$externalPlanId]
                                ?? 0
                            ) + 1;
                    }
                }
            }

            $definitions[$code] = new PlanDefinition(
                $planCode,
                $name,
                $tier,
                $features,
                $limits,
                $providers,
                $providers[BillingProvider::Stripe->value],
                $manualAmounts,
            );
        }

        $indexes = [];

        foreach ($definitions as $code => $definition) {
            foreach ($definition->providers as $provider => $intervals) {
                foreach ($intervals as $externalPlanId) {
                    if ($externalPlanId !== null
                        && (
                            $occurrences[$provider][$externalPlanId]
                            ?? 0
                        ) === 1) {
                        $indexes[$provider][$externalPlanId] = $code;
                    }
                }
            }
        }

        return [
            $definitions,
            $indexes,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, array<string, string|null>>
     */
    private static function providerPlans(array $plan): array
    {
        $providers = [];

        foreach (BillingProvider::cases() as $provider) {
            $configured =
                $plan['providers'][$provider->value]
                ?? null;

            if ($provider === BillingProvider::Stripe
                && ! is_array($configured)) {
                $configured = $plan['prices'] ?? [];
            }

            $providers[$provider->value] = [];

            foreach (self::INTERVALS as $interval) {
                $externalPlanId = is_array($configured)
                    ? ($configured[$interval] ?? null)
                    : null;

                $providers[$provider->value][$interval] =
                    is_string($externalPlanId)
                    && self::isValidExternalPlanId(
                        $externalPlanId,
                        $provider,
                    )
                        ? $externalPlanId
                        : null;
            }
        }

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, int|null>
     */
    private static function manualAmounts(
        array $plan,
    ): array {
        $configured = is_array(
            $plan['manual_amounts'] ?? null,
        )
            ? $plan['manual_amounts']
            : [];

        $amounts = [];

        foreach (self::INTERVALS as $interval) {
            $amounts[$interval] = self::manualAmount(
                $configured[$interval] ?? null,
            );
        }

        return $amounts;
    }

    private static function manualAmount(
        mixed $amount,
    ): ?int {
        if (is_int($amount) && $amount > 0) {
            return $amount;
        }

        return is_string($amount)
            && ctype_digit($amount)
            && (int) $amount > 0
                ? (int) $amount
                : null;
    }

    private static function provider(
        BillingProvider|string $provider,
    ): ?BillingProvider {
        return is_string($provider)
            ? BillingIdentity::provider($provider)
            : $provider;
    }

    private static function isValidExternalPlanId(
        string $externalPlanId,
        BillingProvider $provider,
    ): bool {
        if (trim($externalPlanId) === ''
            || preg_match(
                '/^\S+$/',
                $externalPlanId,
            ) !== 1) {
            return false;
        }

        return match ($provider) {
            BillingProvider::Stripe => preg_match(
                '/^price_[A-Za-z0-9_]+$/',
                $externalPlanId,
            ) === 1,

            BillingProvider::PayMongo => preg_match(
                '/^plan_[A-Za-z0-9_]+$/',
                $externalPlanId,
            ) === 1,
        };
    }
}
