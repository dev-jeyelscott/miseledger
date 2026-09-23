<?php

namespace App\Actions\Onboarding;

use App\Actions\Inventory\SaveUnitOfMeasure;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Inventory\StandardUnits;
use Illuminate\Support\Facades\DB;

final class CreateSelectedStandardUnits
{
    public function __construct(
        private readonly SaveUnitOfMeasure $saveUnitOfMeasure,
    ) {}

    /**
     * Create only the standard catalog units the owner explicitly selected.
     * Symbols the organization already has are left untouched, so repeated
     * submissions never duplicate or rewrite existing units.
     *
     * @param  list<string>  $symbols  Validated standard catalog symbols.
     * @return int The number of units created by this call.
     */
    public function handle(Organization $organization, array $symbols): int
    {
        $definitions = [];

        foreach (StandardUnits::definitions() as $definition) {
            $definitions[$definition['symbol']] = $definition;
        }

        return DB::transaction(function () use (
            $organization,
            $symbols,
            $definitions,
        ): int {
            Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $created = 0;

            foreach (array_unique($symbols) as $symbol) {
                $definition = $definitions[$symbol] ?? null;

                if ($definition === null) {
                    continue;
                }

                $exists = UnitOfMeasure::query()
                    ->where('organization_id', $organization->getKey())
                    ->where(function ($query) use ($definition): void {
                        $query
                            ->where('symbol', $definition['symbol'])
                            ->orWhere('name', $definition['name']);
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                $this->saveUnitOfMeasure->handle($organization, [
                    'name' => $definition['name'],
                    'symbol' => $definition['symbol'],
                    'dimension' => $definition['dimension'],
                    'active' => true,
                ]);

                $created++;
            }

            return $created;
        });
    }
}
