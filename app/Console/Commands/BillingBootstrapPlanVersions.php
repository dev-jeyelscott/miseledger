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

        $currency = config('billing.currency');
        $currency = is_string($currency)
            ? mb_strtoupper(trim($currency))
            : null;

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

            if (
                BillingPlanVersion::query()
                    ->where('plan_code', $planCode->value)
                    ->where('version', 1)
                    ->exists()
            ) {
                $this->line(
                    "{$planCode->value} v1 already exists. No change.",
                );

                continue;
            }

            $this->line(
                "Would create {$planCode->value} v1 draft.",
            );

            foreach (['monthly', 'yearly'] as $interval) {
                $amount = $definition->manualAmount($interval);

                if ($amount !== null) {
                    $this->line(
                        "  PayMongo manual {$interval}: {$amount} {$currency}",
                    );
                }
            }

            foreach (
                $catalog->priceRequirements($planCode) as $requirement
            ) {
                if (
                    $requirement['collectionMethod']
                    === BillingCollectionMethod::Automatic
                ) {
                    $this->warn(
                        sprintf(
                            '  Missing authoritative numeric %s automatic %s price. Populate before publication.',
                            $requirement['provider']->value,
                            $requirement['interval'],
                        ),
                    );
                }
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

                    if (
                        $currency === null
                        || preg_match(
                            '/^[A-Z]{3}$/',
                            $currency,
                        ) !== 1
                    ) {
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
}
