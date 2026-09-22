<?php

namespace App\Console\Commands;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingPlanVersion;
use App\Support\Billing\PlanCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BillingBootstrapPlanVersions extends Command
{
    protected $signature = 'billing:bootstrap-plan-versions
        {--apply : Persist Version 1 drafts instead of dry-running}';

    protected $description =
        'Create idempotent Version 1 plan drafts from verified legacy catalog terms without inferring unknown recurring prices.';

    /**
     * Bootstrap existing commercial terms as Version 1 drafts.
     */
    public function handle(): int
    {
        $plans = (array) config('billing.plans', []);
        $catalog = new PlanCatalog($plans);
        $apply = (bool) $this->option('apply');

        $currency = PlanCatalog::billingCurrency();

        $this->info(
            $apply
                ? 'Applying Version 1 draft bootstrap.'
                : 'Dry run only. No database changes will be written.',
        );

        foreach (array_keys($plans) as $rawPlanCode) {
            if (! is_string($rawPlanCode)) {
                continue;
            }

            try {
                $planCode = PlanCode::from($rawPlanCode);
            } catch (InvalidArgumentException) {
                $this->error(
                    "Skipping invalid plan code [{$rawPlanCode}].",
                );

                continue;
            }

            $definition = $catalog->legacyGet($planCode);

            if ($definition === null) {
                $this->error(
                    "Skipping unresolved plan [{$rawPlanCode}].",
                );

                continue;
            }

            $existingVersion = BillingPlanVersion::query()
                ->where('plan_code', $planCode->value)
                ->where('version', 1)
                ->with('prices')
                ->first();

            if ($existingVersion !== null) {
                $this->line(
                    "{$planCode->value} v1 already exists. No change.",
                );

                foreach (
                    $catalog->missingPriceRequirements(
                        $planCode,
                        $existingVersion->prices,
                    ) as $requirement
                ) {
                    $this->warn(
                        self::missingPriceMessage($requirement),
                    );
                }

                continue;
            }

            $this->line(
                "Would create {$planCode->value} v1 draft.",
            );

            foreach ($catalog->priceRequirements($planCode) as $requirement) {
                if (
                    $requirement['collectionMethod']
                    === BillingCollectionMethod::Manual
                    && $requirement['currency'] !== null
                ) {
                    $amount = $definition->manualAmount(
                        $requirement['interval'],
                    );

                    $this->line(
                        sprintf(
                            '  Would record %s manual %s: %d %s',
                            $requirement['provider']->value,
                            $requirement['interval'],
                            $amount,
                            $requirement['currency'],
                        ),
                    );

                    continue;
                }

                $this->warn(self::missingPriceMessage($requirement));
            }

            if (! $apply) {
                continue;
            }

            DB::transaction(
                function () use (
                    $definition,
                    $planCode,
                    $currency,
                ): void {
                    $version = BillingPlanVersion::query()->create([
                        'plan_code' => $planCode->value,
                        'version' => 1,
                        'name' => $definition->name,
                        'tier' => $definition->tier(),
                        'feature_codes' => $definition->features,
                        'limits' => $definition->limits,
                    ]);

                    if ($currency === null) {
                        return;
                    }

                    foreach (
                        ['monthly', 'yearly'] as $interval
                    ) {
                        $amount = $definition
                            ->manualAmount($interval);

                        if ($amount === null) {
                            continue;
                        }

                        $version->prices()->create([
                            'provider' => BillingProvider::PayMongo,
                            'collection_method' => BillingCollectionMethod::Manual,
                            'interval' => $interval,
                            'currency' => $currency,
                            'amount_minor' => $amount,
                        ]);
                    }
                },
                attempts: 3,
            );
        }

        return self::SUCCESS;
    }

    /**
     * Describe one unsatisfied price requirement without exposing any
     * provider secret or external identifier.
     *
     * @param  array{
     *     provider: BillingProvider,
     *     collectionMethod: BillingCollectionMethod,
     *     interval: string,
     *     currency: string|null
     * }  $requirement
     */
    private static function missingPriceMessage(array $requirement): string
    {
        if ($requirement['currency'] === null) {
            return sprintf(
                '  Missing authoritative billing currency for %s %s %s price. Configure SUBSCRIPTION_BILLING_CURRENCY before publication.',
                $requirement['provider']->value,
                $requirement['collectionMethod']->value,
                $requirement['interval'],
            );
        }

        return sprintf(
            '  Missing authoritative numeric %s %s %s price in %s. Populate before publication.',
            $requirement['provider']->value,
            $requirement['collectionMethod']->value,
            $requirement['interval'],
            $requirement['currency'],
        );
    }
}
