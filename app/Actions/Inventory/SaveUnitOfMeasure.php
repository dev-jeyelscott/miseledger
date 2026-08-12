<?php

namespace App\Actions\Inventory;

use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Inventory\StandardUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveUnitOfMeasure
{
    /**
     * Create or update a tenant-scoped UOM while preserving references.
     *
     * @param  array{
     *     name: string,
     *     symbol: string,
     *     dimension: string,
     *     active: bool
     * }  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?UnitOfMeasure $unitOfMeasure = null,
    ): UnitOfMeasure {
        $this->validateStandardDimension($attributes);

        return DB::transaction(function () use (
            $organization,
            $attributes,
            $unitOfMeasure,
        ): UnitOfMeasure {
            if ($unitOfMeasure === null) {
                return $organization->unitsOfMeasure()->create($attributes);
            }

            $lockedUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($unitOfMeasure->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isReferenced = $lockedUnit
                ->baseInventoryItems()
                ->exists()
                || $lockedUnit
                    ->inventoryItemUnits()
                    ->exists();

            if ($isReferenced) {
                $this->preventReferencedSemanticMutation(
                    $lockedUnit,
                    $attributes,
                );

                if ($lockedUnit->active && ! $attributes['active']) {
                    throw ValidationException::withMessages([
                        'active' => __(
                            'This unit cannot be deactivated while it is assigned to an inventory item or conversion.',
                        ),
                    ]);
                }
            }

            $lockedUnit->update($attributes);

            return $lockedUnit;
        });
    }

    /**
     * Require reserved standard symbols to use their approved dimension.
     *
     * @param  array{
     *     name: string,
     *     symbol: string,
     *     dimension: string,
     *     active: bool
     * }  $attributes
     */
    private function validateStandardDimension(array $attributes): void
    {
        $requiredDimension = StandardUnits::dimensionFor(
            $attributes['symbol'],
        );

        if (
            $requiredDimension !== null
            && $requiredDimension !== $attributes['dimension']
        ) {
            throw ValidationException::withMessages([
                'dimension' => __(
                    'The selected dimension does not match this standard unit symbol.',
                ),
            ]);
        }
    }

    /**
     * Prevent referenced UOM IDs from changing their physical meaning.
     *
     * @param  array{
     *     name: string,
     *     symbol: string,
     *     dimension: string,
     *     active: bool
     * }  $attributes
     */
    private function preventReferencedSemanticMutation(
        UnitOfMeasure $unit,
        array $attributes,
    ): void {
        $errors = [];

        if ($unit->symbol !== $attributes['symbol']) {
            $errors['symbol'] = __(
                'The symbol cannot change while this unit is referenced by inventory configuration.',
            );
        }

        if ($unit->dimension !== $attributes['dimension']) {
            $errors['dimension'] = __(
                'The dimension cannot change while this unit is referenced by inventory configuration.',
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
