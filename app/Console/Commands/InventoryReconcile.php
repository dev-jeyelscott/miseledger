<?php

namespace App\Console\Commands;

use App\Actions\Inventory\ReplayStockLedger;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class InventoryReconcile extends Command
{
    protected $signature = 'inventory:reconcile';

    protected $description =
        'Report discrepancies between the stock ledger and balance projection.';

    /**
     * Compare every ledger identity with its current projection without mutating data.
     */
    public function handle(
        ReplayStockLedger $replayStockLedger,
    ): int {
        $discrepancies = [];

        foreach ($this->identities() as $identity) {
            $expected = $replayStockLedger->handle(
                (int) $identity->organization_id,
                (int) $identity->location_id,
                (int) $identity->storage_location_id,
                (int) $identity->inventory_item_id,
            );

            $actual = StockBalance::query()
                ->where(
                    'organization_id',
                    $identity->organization_id,
                )
                ->where(
                    'location_id',
                    $identity->location_id,
                )
                ->where(
                    'storage_location_id',
                    $identity->storage_location_id,
                )
                ->where(
                    'inventory_item_id',
                    $identity->inventory_item_id,
                )
                ->first();

            if (
                $actual !== null
                && $this->matches(
                    $actual,
                    $expected,
                )
            ) {
                continue;
            }

            $discrepancies[] = [
                $identity->organization_id,
                $identity->location_id,
                $identity->storage_location_id,
                $identity->inventory_item_id,
                $expected['quantity_on_hand'],
                $actual->quantity_on_hand ?? 'MISSING',
                $expected['average_unit_cost'],
                $actual->average_unit_cost ?? 'MISSING',
                $expected['inventory_value'],
                $actual->inventory_value ?? 'MISSING',
            ];
        }

        if ($discrepancies === []) {
            $this->info(
                'Stock balances reconcile with the authoritative ledger.',
            );

            return self::SUCCESS;
        }

        $this->error(
            'Stock balance discrepancies detected.',
        );

        $this->table([
            'Organization',
            'Location',
            'Storage',
            'Item',
            'Expected Qty',
            'Actual Qty',
            'Expected Avg Cost',
            'Actual Avg Cost',
            'Expected Value',
            'Actual Value',
        ], $discrepancies);

        return self::FAILURE;
    }

    /**
     * Return every unique identity present in either ledger or projection.
     *
     * @return Collection<int, stdClass>
     */
    private function identities(): Collection
    {
        $columns = [
            'organization_id',
            'location_id',
            'storage_location_id',
            'inventory_item_id',
        ];

        $balances = DB::table(
            (new StockBalance)->getTable(),
        )
            ->select($columns)
            ->distinct();

        return DB::table(
            (new StockMovement)->getTable(),
        )
            ->select($columns)
            ->distinct()
            ->union($balances)
            ->orderBy('organization_id')
            ->orderBy('location_id')
            ->orderBy('storage_location_id')
            ->orderBy('inventory_item_id')
            ->get();
    }

    /**
     * Determine whether all rebuildable projection fields match exactly.
     *
     * @param  array{
     *     quantity_on_hand: string,
     *     average_unit_cost: string,
     *     inventory_value: string,
     *     last_movement_at: CarbonImmutable|null
     * }  $expected
     */
    private function matches(
        StockBalance $actual,
        array $expected,
    ): bool {
        $actualLastMovement =
            $actual->last_movement_at?->toISOString();

        $expectedLastMovement =
            $expected['last_movement_at']?->toISOString();

        return $actual->quantity_on_hand
                === $expected['quantity_on_hand']
            && $actual->average_unit_cost
                === $expected['average_unit_cost']
            && $actual->inventory_value
                === $expected['inventory_value']
            && $actualLastMovement
                === $expectedLastMovement;
    }
}
