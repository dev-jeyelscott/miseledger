<?php

namespace App\Console\Commands;

use App\Actions\Inventory\ReplayStockLedger;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class InventoryRebuildBalances extends Command
{
    protected $signature = 'inventory:rebuild-balances';

    protected $description =
        'Rebuild stock balance projections from the authoritative ledger.';

    /**
     * Repair projection rows one identity at a time without changing stock movements.
     */
    public function handle(
        ReplayStockLedger $replayStockLedger,
    ): int {
        $rebuilt = 0;

        foreach ($this->identities() as $identity) {
            DB::transaction(function () use (
                $identity,
                $replayStockLedger,
                &$rebuilt,
            ): void {
                $balance = $this->lockBalance(
                    $identity,
                );

                $expected = $replayStockLedger->handle(
                    (int) $identity->organization_id,
                    (int) $identity->location_id,
                    (int) $identity->storage_location_id,
                    (int) $identity->inventory_item_id,
                );

                $balance->update($expected);

                $rebuilt++;
            }, 3);
        }

        $this->info(sprintf(
            'Rebuilt %d stock balance projection row%s.',
            $rebuilt,
            $rebuilt === 1 ? '' : 's',
        ));

        return self::SUCCESS;
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
     * Ensure a projection row exists and lock it before replaying its ledger.
     */
    private function lockBalance(
        stdClass $identity,
    ): StockBalance {
        $attributes = [
            'organization_id' => (int) $identity->organization_id,
            'location_id' => (int) $identity->location_id,
            'storage_location_id' => (int) $identity->storage_location_id,
            'inventory_item_id' => (int) $identity->inventory_item_id,
        ];

        StockBalance::query()->createOrFirst(
            $attributes,
            [
                'quantity_on_hand' => '0.000000',
                'average_unit_cost' => '0.0000',
                'inventory_value' => '0.0000',
                'last_movement_at' => null,
            ],
        );

        return StockBalance::query()
            ->where($attributes)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
