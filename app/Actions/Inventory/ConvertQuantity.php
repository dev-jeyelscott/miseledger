<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Inventory\StandardUnits;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class ConvertQuantity
{
    private const SCALE = 6;

    private const MAX_QUANTITY = '999999999.999999';

    private const MIN_QUANTITY = '-999999999.999999';

    /**
     * Deterministically convert a quantity between units.
     *
     * Resolution order:
     * same unit
     * → standard same-dimension conversion
     * → direct item-specific conversion
     * → inverse item-specific conversion
     * → validation failure
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        string $quantity,
        UnitOfMeasure $fromUnit,
        UnitOfMeasure $toUnit,
    ): string {
        $this->validateOwnership(
            $organization,
            $inventoryItem,
            $fromUnit,
            $toUnit,
        );

        $decimalQuantity = $this->parseDecimal(
            $quantity,
            'quantity',
        );

        if ($fromUnit->getKey() === $toUnit->getKey()) {
            return $this->finalize($decimalQuantity);
        }

        $standard = $this->standardConversion(
            $decimalQuantity,
            $fromUnit,
            $toUnit,
        );

        if ($standard !== null) {
            return $standard;
        }

        if ($fromUnit->dimension !== $toUnit->dimension) {
            throw ValidationException::withMessages([
                'unit' => __(
                    'Units with different dimensions cannot be converted.',
                ),
            ]);
        }

        $direct = $this->directItemConversion(
            $inventoryItem,
            $decimalQuantity,
            $fromUnit,
            $toUnit,
        );

        if ($direct !== null) {
            return $direct;
        }

        $inverse = $this->inverseItemConversion(
            $inventoryItem,
            $decimalQuantity,
            $fromUnit,
            $toUnit,
        );

        if ($inverse !== null) {
            return $inverse;
        }

        throw ValidationException::withMessages([
            'unit' => __(
                'No supported unit conversion exists for this inventory item.',
            ),
        ]);
    }

    /**
     * Validate that all conversion inputs belong to one organization.
     */
    private function validateOwnership(
        Organization $organization,
        InventoryItem $inventoryItem,
        UnitOfMeasure $fromUnit,
        UnitOfMeasure $toUnit,
    ): void {
        if ($inventoryItem->organization_id !== $organization->getKey()) {
            throw ValidationException::withMessages([
                'inventory_item' => __(
                    'The inventory item does not belong to the active organization.',
                ),
            ]);
        }

        if ($fromUnit->organization_id !== $organization->getKey()) {
            throw ValidationException::withMessages([
                'from_unit' => __(
                    'The source unit does not belong to the active organization.',
                ),
            ]);
        }

        if ($toUnit->organization_id !== $organization->getKey()) {
            throw ValidationException::withMessages([
                'to_unit' => __(
                    'The target unit does not belong to the active organization.',
                ),
            ]);
        }
    }

    /**
     * Resolve a deterministic standard weight or volume conversion.
     */
    private function standardConversion(
        BigDecimal $quantity,
        UnitOfMeasure $fromUnit,
        UnitOfMeasure $toUnit,
    ): ?string {
        if ($fromUnit->dimension !== $toUnit->dimension) {
            return null;
        }

        $fromFactor = StandardUnits::canonicalFactor(
            $fromUnit->symbol,
            $fromUnit->dimension,
        );

        $toFactor = StandardUnits::canonicalFactor(
            $toUnit->symbol,
            $toUnit->dimension,
        );

        if ($fromFactor === null || $toFactor === null) {
            return null;
        }

        $converted = $quantity
            ->multipliedBy(BigDecimal::of($fromFactor))
            ->dividedBy(
                BigDecimal::of($toFactor),
                self::SCALE,
                RoundingMode::HalfUp,
            );

        return $this->finalize($converted);
    }

    /**
     * Convert an item-specific alternate unit directly into its base unit.
     */
    private function directItemConversion(
        InventoryItem $inventoryItem,
        BigDecimal $quantity,
        UnitOfMeasure $fromUnit,
        UnitOfMeasure $toUnit,
    ): ?string {
        if (
            $toUnit->getKey()
            !== $inventoryItem->base_unit_of_measure_id
        ) {
            return null;
        }

        $conversion = $this->activeItemConversion(
            $inventoryItem,
            $fromUnit,
        );

        if ($conversion === null) {
            return null;
        }

        $factor = $this->positiveFactor($conversion);

        return $this->finalize(
            $quantity->multipliedBy($factor),
        );
    }

    /**
     * Convert from the item's base unit using the inverse of one
     * authoritative alternate-to-base conversion.
     */
    private function inverseItemConversion(
        InventoryItem $inventoryItem,
        BigDecimal $quantity,
        UnitOfMeasure $fromUnit,
        UnitOfMeasure $toUnit,
    ): ?string {
        if (
            $fromUnit->getKey()
            !== $inventoryItem->base_unit_of_measure_id
        ) {
            return null;
        }

        $conversion = $this->activeItemConversion(
            $inventoryItem,
            $toUnit,
        );

        if ($conversion === null) {
            return null;
        }

        $factor = $this->positiveFactor($conversion);

        return $this->finalize(
            $quantity->dividedBy(
                $factor,
                self::SCALE,
                RoundingMode::HalfUp,
            ),
        );
    }

    /**
     * Resolve exactly one active conversion belonging to the target item.
     */
    private function activeItemConversion(
        InventoryItem $inventoryItem,
        UnitOfMeasure $unit,
    ): ?InventoryItemUnit {
        return $inventoryItem
            ->unitConversions()
            ->where('unit_of_measure_id', $unit->getKey())
            ->where('active', true)
            ->whereHas(
                'unitOfMeasure',
                fn ($query) => $query->where(
                    'organization_id',
                    $inventoryItem->organization_id,
                ),
            )
            ->first();
    }

    /**
     * Parse an item conversion factor and defensively require it to be > 0.
     */
    private function positiveFactor(
        InventoryItemUnit $conversion,
    ): BigDecimal {
        $factor = $this->parseDecimal(
            $conversion->quantity_in_base_unit,
            'conversion',
        );

        if ($factor->compareTo(BigDecimal::zero()) <= 0) {
            throw ValidationException::withMessages([
                'conversion' => __(
                    'The item-specific conversion factor must be greater than zero.',
                ),
            ]);
        }

        return $factor;
    }

    /**
     * Parse authoritative decimals without accepting PHP floating point.
     */
    private function parseDecimal(
        string $value,
        string $field,
    ): BigDecimal {
        try {
            return BigDecimal::of(trim($value));
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __('A valid decimal quantity is required.'),
            ]);
        }
    }

    /**
     * Round once to persisted inventory precision and enforce the future
     * numeric(15,6) ledger boundary.
     */
    private function finalize(BigDecimal $quantity): string
    {
        $scaled = $quantity->toScale(
            self::SCALE,
            RoundingMode::HalfUp,
        );

        if (
            $scaled->isGreaterThan(BigDecimal::of(self::MAX_QUANTITY))
            || $scaled->isLessThan(BigDecimal::of(self::MIN_QUANTITY))
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'The converted quantity exceeds supported inventory precision.',
                ),
            ]);
        }

        return (string) $scaled;
    }
}
