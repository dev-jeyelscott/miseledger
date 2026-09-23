<?php

namespace App\Actions\Onboarding;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordOnboardingOpeningStock
{
    public const REFERENCE_TYPE = 'onboarding_opening_stock';

    public function __construct(
        private readonly RecordOpeningBalance $recordOpeningBalance,
    ) {}

    /**
     * Record one item's starting stock during first-time setup through the
     * established opening-balance ledger path.
     *
     * Each item can receive exactly one setup opening movement. Repeating the
     * same submission returns the recorded movement; a different quantity,
     * cost, or location for an item that already has stock history is
     * rejected so setup can never duplicate or silently overwrite stock.
     * Quantity is entered in the item's base unit and cost per base unit.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        Location $location,
        string $quantity,
        string $baseUnitCost,
        User $actor,
    ): StockMovement {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $location,
            $quantity,
            $baseUnitCost,
            $actor,
        ): StockMovement {
            $lockedItem = InventoryItem::query()
                ->with('baseUnitOfMeasure')
                ->where('organization_id', $organization->getKey())
                ->where('active', true)
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $storageLocation = StorageLocation::query()
                ->where('organization_id', $organization->getKey())
                ->where('location_id', $location->getKey())
                ->where('active', true)
                ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [StorageLocation::DEFAULT_CODE])
                ->orderBy('id')
                ->first();

            if ($storageLocation === null) {
                throw ValidationException::withMessages([
                    'location_id' => __('The selected location has no active storage area.'),
                ]);
            }

            $idempotencyKey = self::idempotencyKey($lockedItem);

            $existing = StockMovement::query()
                ->where('organization_id', $organization->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($this->matches($existing, $location, $quantity, $baseUnitCost)) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'quantity' => __('Opening stock for :item was already recorded with different values. Use a stock adjustment after setup to correct it.', [
                        'item' => $lockedItem->name,
                    ]),
                ]);
            }

            if ($lockedItem->stockMovements()->exists()) {
                throw ValidationException::withMessages([
                    'quantity' => __(':item already has recorded stock history.', [
                        'item' => $lockedItem->name,
                    ]),
                ]);
            }

            $movement = $this->recordOpeningBalance->handle(
                organization: $organization,
                location: $location,
                storageLocation: $storageLocation,
                inventoryItem: $lockedItem,
                quantity: $quantity,
                unit: $lockedItem->baseUnitOfMeasure,
                baseUnitCost: $baseUnitCost,
                referenceType: self::REFERENCE_TYPE,
                referenceId: $lockedItem->id,
                occurredAt: CarbonImmutable::now()->utc(),
                idempotencyKey: $idempotencyKey,
                actor: $actor,
            );

            if ($lockedItem->opening_stock_waived_at !== null) {
                $lockedItem->forceFill([
                    'opening_stock_waived_at' => null,
                    'opening_stock_waived_by' => null,
                ])->save();
            }

            return $movement;
        });
    }

    /**
     * The single setup opening-stock identity for an inventory item.
     */
    public static function idempotencyKey(InventoryItem $inventoryItem): string
    {
        return 'opening_balance:onboarding:'.$inventoryItem->getKey();
    }

    /**
     * Whether a retried submission describes the already recorded movement.
     */
    private function matches(
        StockMovement $existing,
        Location $location,
        string $quantity,
        string $baseUnitCost,
    ): bool {
        try {
            return $existing->location_id === $location->getKey()
                && BigDecimal::of($existing->quantity)->isEqualTo(trim($quantity))
                && BigDecimal::of((string) $existing->unit_cost)->isEqualTo(trim($baseUnitCost));
        } catch (MathException) {
            return false;
        }
    }
}
