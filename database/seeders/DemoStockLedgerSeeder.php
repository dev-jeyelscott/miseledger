<?php

namespace Database\Seeders;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStockLedgerSeeder extends Seeder
{
    /**
     * Establish historical stock through auditable ledger workflows only.
     */
    public function run(
        RecordOpeningBalance $recordOpeningBalance,
        AdjustInventory $adjustInventory,
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

        /** @var list<array{storage: string, sku: string, quantity: string, cost: string}> $openings */
        $openings = [
            ['storage' => 'MKT-CHILL', 'sku' => 'CHK-THIGH', 'quantity' => '25.000000', 'cost' => '168.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'PORK-BELLY', 'quantity' => '12.000000', 'cost' => '286.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'GARLIC', 'quantity' => '8.000000', 'cost' => '146.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'RED-ONION', 'quantity' => '15.000000', 'cost' => '86.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'TOMATO-ROMA', 'quantity' => '12.000000', 'cost' => '106.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'GREEN-CHILI', 'quantity' => '3.000000', 'cost' => '212.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'EGG-LARGE', 'quantity' => '120.000000', 'cost' => '9.2000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'FRESH-MILK', 'quantity' => '24.000000', 'cost' => '95.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'BUTTER', 'quantity' => '5.000000', 'cost' => '378.0000'],
            ['storage' => 'MKT-CHILL', 'sku' => 'AP-CREAM', 'quantity' => '8.000000', 'cost' => '134.0000'],

            ['storage' => 'MKT-DRY', 'sku' => 'JASMINE-RICE', 'quantity' => '60.000000', 'cost' => '58.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'AP-FLOUR', 'quantity' => '25.000000', 'cost' => '46.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'BROWN-SUGAR', 'quantity' => '15.000000', 'cost' => '72.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'SOY-SAUCE', 'quantity' => '12.000000', 'cost' => '62.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'CANE-VINEGAR', 'quantity' => '10.000000', 'cost' => '45.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'COOKING-OIL', 'quantity' => '16.000000', 'cost' => '94.0000'],
            ['storage' => 'MKT-DRY', 'sku' => 'COFFEE-BEAN', 'quantity' => '12.000000', 'cost' => '655.0000'],

            ['storage' => 'MKT-PACK', 'sku' => 'BOWL-750', 'quantity' => '500.000000', 'cost' => '4.3000'],
            ['storage' => 'MKT-PACK', 'sku' => 'CUP-12OZ', 'quantity' => '1000.000000', 'cost' => '3.1000'],
            ['storage' => 'MKT-PACK', 'sku' => 'PAPER-BAG-M', 'quantity' => '300.000000', 'cost' => '2.6000'],
            ['storage' => 'MKT-PACK', 'sku' => 'NAPKIN', 'quantity' => '1000.000000', 'cost' => '0.8500'],
            ['storage' => 'MKT-PACK', 'sku' => 'WATER-500', 'quantity' => '96.000000', 'cost' => '15.0000'],

            ['storage' => 'BGC-CHILL', 'sku' => 'CHK-THIGH', 'quantity' => '20.000000', 'cost' => '170.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'PORK-BELLY', 'quantity' => '10.000000', 'cost' => '290.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'GARLIC', 'quantity' => '6.000000', 'cost' => '148.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'RED-ONION', 'quantity' => '12.000000', 'cost' => '88.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'TOMATO-ROMA', 'quantity' => '10.000000', 'cost' => '108.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'EGG-LARGE', 'quantity' => '90.000000', 'cost' => '9.4000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'FRESH-MILK', 'quantity' => '18.000000', 'cost' => '96.0000'],
            ['storage' => 'BGC-CHILL', 'sku' => 'AP-CREAM', 'quantity' => '12.000000', 'cost' => '136.0000'],

            ['storage' => 'BGC-DRY', 'sku' => 'JASMINE-RICE', 'quantity' => '50.000000', 'cost' => '59.0000'],
            ['storage' => 'BGC-DRY', 'sku' => 'AP-FLOUR', 'quantity' => '20.000000', 'cost' => '47.0000'],
            ['storage' => 'BGC-DRY', 'sku' => 'BROWN-SUGAR', 'quantity' => '12.000000', 'cost' => '73.0000'],
            ['storage' => 'BGC-DRY', 'sku' => 'COOKING-OIL', 'quantity' => '16.000000', 'cost' => '95.0000'],

            ['storage' => 'BGC-BAR', 'sku' => 'COFFEE-BEAN', 'quantity' => '10.000000', 'cost' => '660.0000'],
            ['storage' => 'BGC-BAR', 'sku' => 'COLA-CAN', 'quantity' => '120.000000', 'cost' => '34.0000'],
            ['storage' => 'BGC-BAR', 'sku' => 'WATER-500', 'quantity' => '120.000000', 'cost' => '15.5000'],

            ['storage' => 'QCC-DRY', 'sku' => 'JASMINE-RICE', 'quantity' => '300.000000', 'cost' => '56.0000'],
            ['storage' => 'QCC-DRY', 'sku' => 'AP-FLOUR', 'quantity' => '150.000000', 'cost' => '44.0000'],
            ['storage' => 'QCC-DRY', 'sku' => 'BROWN-SUGAR', 'quantity' => '100.000000', 'cost' => '70.0000'],
            ['storage' => 'QCC-DRY', 'sku' => 'COOKING-OIL', 'quantity' => '80.000000', 'cost' => '91.0000'],
            ['storage' => 'QCC-DRY', 'sku' => 'SOY-SAUCE', 'quantity' => '60.000000', 'cost' => '59.0000'],
            ['storage' => 'QCC-DRY', 'sku' => 'CANE-VINEGAR', 'quantity' => '50.000000', 'cost' => '43.0000'],

            ['storage' => 'QCC-CHILL', 'sku' => 'CHK-THIGH', 'quantity' => '80.000000', 'cost' => '163.0000'],
            ['storage' => 'QCC-CHILL', 'sku' => 'PORK-BELLY', 'quantity' => '50.000000', 'cost' => '278.0000'],
            ['storage' => 'QCC-CHILL', 'sku' => 'FRESH-MILK', 'quantity' => '48.000000', 'cost' => '92.0000'],
            ['storage' => 'QCC-CHILL', 'sku' => 'EGG-LARGE', 'quantity' => '240.000000', 'cost' => '8.9000'],

            ['storage' => 'QCC-PACK', 'sku' => 'BOWL-750', 'quantity' => '2000.000000', 'cost' => '4.1000'],
            ['storage' => 'QCC-PACK', 'sku' => 'CUP-12OZ', 'quantity' => '3000.000000', 'cost' => '2.9500'],
            ['storage' => 'QCC-PACK', 'sku' => 'PAPER-BAG-M', 'quantity' => '1500.000000', 'cost' => '2.4000'],
            ['storage' => 'QCC-PACK', 'sku' => 'NAPKIN', 'quantity' => '5000.000000', 'cost' => '0.8000'],
        ];

        try {
            Carbon::setTestNow('2026-06-01 06:00:00');

            foreach ($openings as $opening) {
                $storage = $this->storage(
                    $organization,
                    $opening['storage'],
                );

                $item = $this->item(
                    $organization,
                    $opening['sku'],
                );

                $recordOpeningBalance->handle(
                    organization: $organization,
                    location: $storage->location,
                    storageLocation: $storage,
                    inventoryItem: $item,
                    quantity: $opening['quantity'],
                    unit: $item->baseUnitOfMeasure,
                    baseUnitCost: $opening['cost'],
                    referenceType: 'manual_opening_balance',
                    referenceId: $item->id,
                    occurredAt: CarbonImmutable::parse(
                        '2026-06-01 06:00:00',
                        $organization->timezone,
                    )->utc(),
                    idempotencyKey: sprintf(
                        'opening_balance:demo:2026-06-01:%s:%s',
                        $opening['storage'],
                        $opening['sku'],
                    ),
                    actor: $actor,
                    notes: 'Opening stock imported from the signed June 2026 physical count.',
                );
            }

            Carbon::setTestNow('2026-06-05 14:30:00');

            $makatiDry = $this->storage(
                $organization,
                'MKT-DRY',
            );
            $rice = $this->item(
                $organization,
                'JASMINE-RICE',
            );

            $adjustInventory->handle(
                organization: $organization,
                location: $makatiDry->location,
                storageLocation: $makatiDry,
                inventoryItem: $rice,
                quantity: '2.000000',
                unit: $rice->baseUnitOfMeasure,
                reason: 'Reconciled opening count against the signed receiving count sheet.',
                referenceType: 'manual_inventory_adjustment',
                referenceId: $rice->id,
                occurredAt: CarbonImmutable::parse(
                    '2026-06-05 14:30:00',
                    $organization->timezone,
                )->utc(),
                actor: $actor,
                idempotencyKey: 'inventory_adjustment:demo:mkt-rice-opening-correction',
            );

            Carbon::setTestNow('2026-06-08 10:15:00');

            $bgcBar = $this->storage(
                $organization,
                'BGC-BAR',
            );
            $cola = $this->item(
                $organization,
                'COLA-CAN',
            );

            $adjustInventory->handle(
                organization: $organization,
                location: $bgcBar->location,
                storageLocation: $bgcBar,
                inventoryItem: $cola,
                quantity: '-6.000000',
                unit: $cola->baseUnitOfMeasure,
                reason: 'Corrected duplicated opening cartons found during migration review.',
                referenceType: 'manual_inventory_adjustment',
                referenceId: $cola->id,
                occurredAt: CarbonImmutable::parse(
                    '2026-06-08 10:15:00',
                    $organization->timezone,
                )->utc(),
                actor: $actor,
                idempotencyKey: 'inventory_adjustment:demo:bgc-cola-opening-correction',
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Resolve one demo inventory item.
     */
    private function item(
        Organization $organization,
        string $sku,
    ): InventoryItem {
        return InventoryItem::query()
            ->with('baseUnitOfMeasure')
            ->where('organization_id', $organization->id)
            ->where('sku', $sku)
            ->sole();
    }

    /**
     * Resolve one demo storage location with its parent branch.
     */
    private function storage(
        Organization $organization,
        string $code,
    ): StorageLocation {
        return StorageLocation::query()
            ->with('location')
            ->where('organization_id', $organization->id)
            ->where('code', $code)
            ->sole();
    }
}
