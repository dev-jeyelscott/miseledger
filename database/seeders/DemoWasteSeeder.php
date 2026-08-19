<?php

namespace Database\Seeders;

use App\Actions\Inventory\RecordWaste;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoWasteSeeder extends Seeder
{
    /**
     * Seed varied waste history across reasons, employees, and locations.
     */
    public function run(RecordWaste $recordWaste): void
    {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        /** @var list<array<string, string>> $records */
        $records = [
            [
                'operation_id' => '00000000-0000-4000-8000-000000000101',
                'occurred_at' => '2026-08-10 19:15:00.000000',
                'storage' => 'MKT-CHILL',
                'sku' => 'CHK-THIGH',
                'reason' => 'Spoilage',
                'quantity' => '0.750000',
                'unit' => 'kg',
                'actor' => 'kitchen@miseledger.com',
                'notes' => 'Trim batch showed off-odor during evening prep and was discarded.',
            ],
            [
                'operation_id' => '00000000-0000-4000-8000-000000000102',
                'occurred_at' => '2026-08-11 10:40:00.000000',
                'storage' => 'MKT-CHILL',
                'sku' => 'TOMATO-ROMA',
                'reason' => 'Damaged',
                'quantity' => '1.200000',
                'unit' => 'kg',
                'actor' => 'inventory@miseledger.com',
                'notes' => 'Crushed tomatoes identified during morning storage inspection.',
            ],
            [
                'operation_id' => '00000000-0000-4000-8000-000000000103',
                'occurred_at' => '2026-08-12 16:25:00.000000',
                'storage' => 'BGC-CHILL',
                'sku' => 'FRESH-MILK',
                'reason' => 'Expired',
                'quantity' => '1.500000',
                'unit' => 'l',
                'actor' => 'kitchen@miseledger.com',
                'notes' => 'Opened milk exceeded the branch discard window.',
            ],
            [
                'operation_id' => '00000000-0000-4000-8000-000000000104',
                'occurred_at' => '2026-08-13 14:10:00.000000',
                'storage' => 'BGC-DRY',
                'sku' => 'JASMINE-RICE',
                'reason' => 'Preparation Error',
                'quantity' => '0.800000',
                'unit' => 'kg',
                'actor' => 'manager@miseledger.com',
                'notes' => 'Batch was overcooked during training and could not be served.',
            ],
            [
                'operation_id' => '00000000-0000-4000-8000-000000000105',
                'occurred_at' => '2026-08-14 20:05:00.000000',
                'storage' => 'BGC-CHILL',
                'sku' => 'AP-CREAM',
                'reason' => 'Overproduction',
                'quantity' => '0.600000',
                'unit' => 'l',
                'actor' => 'kitchen@miseledger.com',
                'notes' => 'Excess cream mixture remained after closing service.',
            ],
            [
                'operation_id' => '00000000-0000-4000-8000-000000000106',
                'occurred_at' => '2026-08-14 21:10:00.000000',
                'storage' => 'BGC-BAR',
                'sku' => 'COLA-CAN',
                'reason' => 'Damaged',
                'quantity' => '4.000000',
                'unit' => 'can',
                'actor' => 'inventory@miseledger.com',
                'notes' => 'Four dented cans failed receiving-quality standards during shelf rotation.',
            ],
        ];

        try {
            foreach ($records as $record) {
                Carbon::setTestNow(
                    substr($record['occurred_at'], 0, 19),
                );

                $storage = StorageLocation::query()
                    ->with('location')
                    ->where('organization_id', $organization->id)
                    ->where('code', $record['storage'])
                    ->sole();

                $item = InventoryItem::query()
                    ->where('organization_id', $organization->id)
                    ->where('sku', $record['sku'])
                    ->sole();

                $reason = WasteReason::query()
                    ->where('organization_id', $organization->id)
                    ->where('name', $record['reason'])
                    ->sole();

                $unit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('symbol', $record['unit'])
                    ->sole();

                $actor = User::query()
                    ->where('email', $record['actor'])
                    ->sole();

                $recordWaste->handle(
                    $organization,
                    $actor,
                    [
                        'operation_id' => $record['operation_id'],
                        'location_id' => $storage->location_id,
                        'storage_location_id' => $storage->id,
                        'inventory_item_id' => $item->id,
                        'waste_reason_id' => $reason->id,
                        'quantity' => $record['quantity'],
                        'unit_id' => $unit->id,
                        'occurred_at' => $record['occurred_at'],
                        'notes' => $record['notes'],
                    ],
                );
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
