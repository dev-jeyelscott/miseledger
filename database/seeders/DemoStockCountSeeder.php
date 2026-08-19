<?php

namespace Database\Seeders;

use App\Actions\Inventory\CancelStockCount;
use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStockCountSeeder extends Seeder
{
    /**
     * Seed representative count states and meaningful finalized variances.
     */
    public function run(
        SaveStockCount $saveStockCount,
        SubmitStockCount $submitStockCount,
        FinalizeStockCount $finalizeStockCount,
        CancelStockCount $cancelStockCount,
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
            $makatiChill = $this->storage(
                $organization,
                'MKT-CHILL',
            );

            $chicken = $this->item($organization, 'CHK-THIGH');
            $garlic = $this->item($organization, 'GARLIC');
            $greenChili = $this->item($organization, 'GREEN-CHILI');
            $tomato = $this->item($organization, 'TOMATO-ROMA');

            Carbon::setTestNow('2026-08-18 07:00:00');

            $count = $saveStockCount->handle(
                $organization,
                $actor,
                [
                    'location_id' => $makatiChill->location_id,
                    'storage_location_id' => $makatiChill->id,
                    'number' => 'SC-MKT-20260818',
                    'lines' => [
                        [
                            'inventory_item_id' => $chicken->id,
                            'counted_quantity' => $this->adjustedQuantity(
                                $this->currentQuantity(
                                    $organization,
                                    $makatiChill,
                                    $chicken,
                                ),
                                '-0.500000',
                            ),
                            'count_unit_id' => $chicken->base_unit_of_measure_id,
                            'notes' => 'Physical count found one half-kilo below system quantity.',
                        ],
                        [
                            'inventory_item_id' => $garlic->id,
                            'counted_quantity' => $this->adjustedQuantity(
                                $this->currentQuantity(
                                    $organization,
                                    $makatiChill,
                                    $garlic,
                                ),
                                '0.250000',
                            ),
                            'count_unit_id' => $garlic->base_unit_of_measure_id,
                            'notes' => 'Quarter-kilo overage found in prep chiller.',
                        ],
                        [
                            'inventory_item_id' => $greenChili->id,
                            'counted_quantity' => '0.000000',
                            'count_unit_id' => $greenChili->base_unit_of_measure_id,
                            'notes' => 'No usable green chili remained on hand.',
                        ],
                        [
                            'inventory_item_id' => $tomato->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $makatiChill,
                                $tomato,
                            ),
                            'count_unit_id' => $tomato->base_unit_of_measure_id,
                            'notes' => 'Physical quantity matched MiseLedger exactly.',
                        ],
                    ],
                ],
            );

            $count = $submitStockCount->handle(
                $organization,
                $actor,
                $count,
            );

            $finalizeStockCount->handle(
                $organization,
                $actor,
                $count,
            );

            Carbon::setTestNow('2026-08-18 09:30:00');

            $bgcBar = $this->storage(
                $organization,
                'BGC-BAR',
            );

            $cola = $this->item($organization, 'COLA-CAN');
            $water = $this->item($organization, 'WATER-500');

            $submitted = $saveStockCount->handle(
                $organization,
                $actor,
                [
                    'location_id' => $bgcBar->location_id,
                    'storage_location_id' => $bgcBar->id,
                    'number' => 'SC-BGC-20260818',
                    'lines' => [
                        [
                            'inventory_item_id' => $cola->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $bgcBar,
                                $cola,
                            ),
                            'count_unit_id' => $cola->base_unit_of_measure_id,
                            'notes' => 'Bar beverage count awaiting manager finalization.',
                        ],
                        [
                            'inventory_item_id' => $water->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $bgcBar,
                                $water,
                            ),
                            'count_unit_id' => $water->base_unit_of_measure_id,
                            'notes' => 'Bar beverage count awaiting manager finalization.',
                        ],
                    ],
                ],
            );

            $submitStockCount->handle(
                $organization,
                $actor,
                $submitted,
            );

            Carbon::setTestNow('2026-08-18 10:30:00');

            $qccDry = $this->storage(
                $organization,
                'QCC-DRY',
            );

            $rice = $this->item($organization, 'JASMINE-RICE');
            $flour = $this->item($organization, 'AP-FLOUR');

            $saveStockCount->handle(
                $organization,
                $actor,
                [
                    'location_id' => $qccDry->location_id,
                    'storage_location_id' => $qccDry->id,
                    'number' => 'SC-QCC-20260818',
                    'lines' => [
                        [
                            'inventory_item_id' => $rice->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $qccDry,
                                $rice,
                            ),
                            'count_unit_id' => $rice->base_unit_of_measure_id,
                            'notes' => 'Draft commissary cycle count.',
                        ],
                        [
                            'inventory_item_id' => $flour->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $qccDry,
                                $flour,
                            ),
                            'count_unit_id' => $flour->base_unit_of_measure_id,
                            'notes' => 'Draft commissary cycle count.',
                        ],
                    ],
                ],
            );

            Carbon::setTestNow('2026-08-18 11:30:00');

            $bgcChill = $this->storage(
                $organization,
                'BGC-CHILL',
            );
            $pork = $this->item($organization, 'PORK-BELLY');

            $cancelled = $saveStockCount->handle(
                $organization,
                $actor,
                [
                    'location_id' => $bgcChill->location_id,
                    'storage_location_id' => $bgcChill->id,
                    'number' => 'SC-BGC-CANCELLED-20260818',
                    'lines' => [
                        [
                            'inventory_item_id' => $pork->id,
                            'counted_quantity' => $this->currentQuantity(
                                $organization,
                                $bgcChill,
                                $pork,
                            ),
                            'count_unit_id' => $pork->base_unit_of_measure_id,
                            'notes' => 'Count cancelled after wrong storage zone was selected.',
                        ],
                    ],
                ],
            );

            $cancelStockCount->handle(
                $organization,
                $actor,
                $cancelled,
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Return a decimal-safe quantity adjusted by the requested variance.
     */
    private function adjustedQuantity(
        string $quantity,
        string $adjustment,
    ): string {
        return BigDecimal::of($quantity)
            ->plus($adjustment)
            ->toScale(6, RoundingMode::HalfUp)
            ->__toString();
    }

    /**
     * Resolve the current projected quantity for one balance key.
     */
    private function currentQuantity(
        Organization $organization,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
    ): string {
        return StockBalance::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $storageLocation->location_id)
            ->where('storage_location_id', $storageLocation->id)
            ->where('inventory_item_id', $inventoryItem->id)
            ->sole()
            ->quantity_on_hand;
    }

    /**
     * Resolve one demo inventory item.
     */
    private function item(
        Organization $organization,
        string $sku,
    ): InventoryItem {
        return InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('sku', $sku)
            ->sole();
    }

    /**
     * Resolve one active demo storage location.
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
