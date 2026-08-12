<?php

namespace App\Actions\Inventory;

use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ReplayStockLedger
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 4;

    /**
     * Rebuild one balance deterministically from immutable movements ordered by occurred_at and id.
     *
     * @return array{
     *     quantity_on_hand: string,
     *     average_unit_cost: string,
     *     inventory_value: string,
     *     last_movement_at: CarbonImmutable|null
     * }
     */
    public function handle(
        int $organizationId,
        int $locationId,
        int $storageLocationId,
        int $inventoryItemId,
    ): array {
        $quantityOnHand = BigDecimal::zero()
            ->toScale(self::QUANTITY_SCALE);

        $averageUnitCost = BigDecimal::zero()
            ->toScale(self::MONEY_SCALE);

        $lastMovementAt = null;

        $movements = StockMovement::query()
            ->where('organization_id', $organizationId)
            ->where('location_id', $locationId)
            ->where(
                'storage_location_id',
                $storageLocationId,
            )
            ->where('inventory_item_id', $inventoryItemId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $movementQuantity = $this->decimal(
                $movement->quantity,
                'movement quantity',
            )->toScale(
                self::QUANTITY_SCALE,
                RoundingMode::HalfUp,
            );

            $newQuantity = $quantityOnHand
                ->plus($movementQuantity)
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HalfUp,
                );

            if (
                $movementQuantity->compareTo(
                    BigDecimal::zero(),
                ) > 0
            ) {
                $movementCost = $movement->unit_cost === null
                    ? $averageUnitCost
                    : $this->decimal(
                        $movement->unit_cost,
                        'movement unit cost',
                    )->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

                if (
                    $quantityOnHand->compareTo(
                        BigDecimal::zero(),
                    ) === 0
                ) {
                    $averageUnitCost = $movementCost;
                } elseif (
                    $newQuantity->compareTo(
                        BigDecimal::zero(),
                    ) > 0
                ) {
                    $averageUnitCost = $quantityOnHand
                        ->multipliedBy($averageUnitCost)
                        ->plus(
                            $movementQuantity
                                ->multipliedBy(
                                    $movementCost,
                                ),
                        )
                        ->dividedBy(
                            $newQuantity,
                            self::MONEY_SCALE,
                            RoundingMode::HalfUp,
                        );
                } else {
                    throw new RuntimeException(
                        'Cannot replay an inbound movement to a non-positive resulting quantity.',
                    );
                }
            }

            $quantityOnHand = $newQuantity;

            $lastMovementAt = CarbonImmutable::instance(
                $movement->occurred_at,
            );
        }

        $inventoryValue = $quantityOnHand
            ->multipliedBy($averageUnitCost)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

        return [
            'quantity_on_hand' => (string) $quantityOnHand,
            'average_unit_cost' => (string) $averageUnitCost,
            'inventory_value' => (string) $inventoryValue,
            'last_movement_at' => $lastMovementAt,
        ];
    }

    /**
     * Parse persisted ledger decimals without native floating point.
     */
    private function decimal(
        string $value,
        string $field,
    ): BigDecimal {
        try {
            return BigDecimal::of($value);
        } catch (NumberFormatException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Invalid %s encountered during ledger replay.',
                    $field,
                ),
                previous: $exception,
            );
        }
    }
}
