<?php

namespace Database\Seeders;

use App\Actions\Inventory\CancelStockTransfer;
use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStockTransferSeeder extends Seeder
{
    /**
     * Seed received, discrepant, shipped, draft, and cancelled transfers.
     */
    public function run(
        SaveStockTransfer $saveStockTransfer,
        ShipStockTransfer $shipStockTransfer,
        ReceiveStockTransfer $receiveStockTransfer,
        CancelStockTransfer $cancelStockTransfer,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        $actor = User::query()
            ->where('email', 'inventory@miseledger.com')
            ->sole();

        try {
            Carbon::setTestNow('2026-08-15 08:00:00');

            $exactTransfer = $this->save(
                $saveStockTransfer,
                $organization,
                $actor,
                'ST-2026-0018',
                'QCC-DRY',
                'MKT-DRY',
                'Commissary replenishment for Makati dry store.',
                [
                    ['sku' => 'JASMINE-RICE', 'quantity' => '1.000000', 'unit' => 'sack'],
                    ['sku' => 'COOKING-OIL', 'quantity' => '0.500000', 'unit' => 'case'],
                ],
            );

            Carbon::setTestNow('2026-08-15 09:00:00');

            $exactTransfer = $shipStockTransfer->handle(
                $organization,
                $actor,
                $exactTransfer,
            );

            Carbon::setTestNow('2026-08-15 14:15:00');

            $receiveStockTransfer->handle(
                $organization,
                $actor,
                $exactTransfer,
                [
                    'lines' => $exactTransfer
                        ->lines()
                        ->orderBy('id')
                        ->get()
                        ->map(static fn ($line): array => [
                            'id' => $line->id,
                            'received_base_quantity' => $line
                                ->shipped_base_quantity,
                        ])
                        ->all(),
                ],
            );

            Carbon::setTestNow('2026-08-16 07:30:00');

            $varianceTransfer = $this->save(
                $saveStockTransfer,
                $organization,
                $actor,
                'ST-2026-0019',
                'QCC-CHILL',
                'BGC-CHILL',
                'Protein allocation from commissary to BGC.',
                [
                    ['sku' => 'CHK-THIGH', 'quantity' => '1.000000', 'unit' => 'case'],
                ],
            );

            Carbon::setTestNow('2026-08-16 08:10:00');

            $varianceTransfer = $shipStockTransfer->handle(
                $organization,
                $actor,
                $varianceTransfer,
            );

            $varianceLine = $varianceTransfer
                ->lines()
                ->sole();

            Carbon::setTestNow('2026-08-16 12:40:00');

            $receiveStockTransfer->handle(
                $organization,
                $actor,
                $varianceTransfer,
                [
                    'lines' => [
                        [
                            'id' => $varianceLine->id,
                            'received_base_quantity' => '9.500000',
                        ],
                    ],
                ],
            );

            Carbon::setTestNow('2026-08-17 07:45:00');

            $shippedTransfer = $this->save(
                $saveStockTransfer,
                $organization,
                $actor,
                'ST-2026-0020',
                'QCC-PACK',
                'MKT-PACK',
                'Packaging replenishment dispatched to Makati.',
                [
                    ['sku' => 'BOWL-750', 'quantity' => '2.000000', 'unit' => 'case'],
                    ['sku' => 'CUP-12OZ', 'quantity' => '1.000000', 'unit' => 'case'],
                ],
            );

            Carbon::setTestNow('2026-08-17 08:30:00');

            $shipStockTransfer->handle(
                $organization,
                $actor,
                $shippedTransfer,
            );

            Carbon::setTestNow('2026-08-17 11:00:00');

            $this->save(
                $saveStockTransfer,
                $organization,
                $actor,
                'ST-2026-0021',
                'BGC-BAR',
                'MKT-DRY',
                'Coffee allocation requested for Makati.',
                [
                    ['sku' => 'COFFEE-BEAN', 'quantity' => '2.000000', 'unit' => 'kg'],
                ],
            );

            Carbon::setTestNow('2026-08-17 13:00:00');

            $cancelledTransfer = $this->save(
                $saveStockTransfer,
                $organization,
                $actor,
                'ST-2026-0022',
                'BGC-BAR',
                'MKT-PACK',
                'Temporary beverage transfer request later withdrawn.',
                [
                    ['sku' => 'COLA-CAN', 'quantity' => '1.000000', 'unit' => 'case'],
                ],
            );

            $cancelStockTransfer->handle(
                $organization,
                $actor,
                $cancelledTransfer,
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Create one stock transfer using stable demo codes.
     *
     * @param  list<array{sku: string, quantity: string, unit: string}>  $lines
     */
    private function save(
        SaveStockTransfer $saveStockTransfer,
        Organization $organization,
        User $actor,
        string $number,
        string $fromStorageCode,
        string $toStorageCode,
        string $notes,
        array $lines,
    ): StockTransfer {
        $fromStorage = $this->storage(
            $organization,
            $fromStorageCode,
        );

        $toStorage = $this->storage(
            $organization,
            $toStorageCode,
        );

        $lineAttributes = [];

        foreach ($lines as $line) {
            $item = InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('sku', $line['sku'])
                ->sole();

            $unit = UnitOfMeasure::query()
                ->where('organization_id', $organization->id)
                ->where('symbol', $line['unit'])
                ->sole();

            $lineAttributes[] = [
                'inventory_item_id' => $item->id,
                'requested_quantity' => $line['quantity'],
                'unit_id' => $unit->id,
            ];
        }

        return $saveStockTransfer->handle(
            $organization,
            $actor,
            [
                'from_location_id' => $fromStorage->location_id,
                'from_storage_location_id' => $fromStorage->id,
                'to_location_id' => $toStorage->location_id,
                'to_storage_location_id' => $toStorage->id,
                'number' => $number,
                'notes' => $notes,
                'lines' => $lineAttributes,
            ],
        );
    }

    /**
     * Resolve one active storage location.
     */
    private function storage(
        Organization $organization,
        string $code,
    ): StorageLocation {
        return StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('code', $code)
            ->where('active', true)
            ->sole();
    }
}
