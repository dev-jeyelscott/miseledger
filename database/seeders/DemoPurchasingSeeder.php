<?php

namespace Database\Seeders;

use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\CancelGoodsReceipt;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoPurchasingSeeder extends Seeder
{
    /**
     * Seed representative purchasing and goods-receiving lifecycle states.
     */
    public function run(
        SavePurchaseOrder $savePurchaseOrder,
        ApprovePurchaseOrder $approvePurchaseOrder,
        CancelPurchaseOrder $cancelPurchaseOrder,
        SaveGoodsReceipt $saveGoodsReceipt,
        FinalizeGoodsReceipt $finalizeGoodsReceipt,
        CancelGoodsReceipt $cancelGoodsReceipt,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        $manager = User::query()
            ->where('email', 'manager@miseledger.com')
            ->sole();

        $receiver = User::query()
            ->where('email', 'inventory@miseledger.com')
            ->sole();

        try {
            /*
             * Fully received commissary staples PO.
             */
            Carbon::setTestNow('2026-08-01 09:00:00');

            $po = $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'PSS')->id,
                    'location_id' => $this->location($organization, 'QCC')->id,
                    'number' => 'PO-2026-0061',
                    'order_date' => '2026-08-01',
                    'expected_delivery_date' => '2026-08-02',
                    'tax_total' => '0.00',
                    'discount_total' => '0.00',
                    'notes' => 'Commissary replenishment for core dry-goods inventory.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PSS-RICE-25KG')->id,
                            'ordered_quantity' => '4.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PSS-FLOUR-25KG')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PSS-OIL-16L')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                    ],
                ],
            );

            $po = $approvePurchaseOrder->handle(
                $organization,
                $manager,
                $po,
            );

            Carbon::setTestNow('2026-08-02 08:40:00');

            $receipt = $saveGoodsReceipt->handle(
                $organization,
                $receiver,
                $po,
                [
                    'number' => 'GR-2026-0047',
                    'supplier_reference' => 'PSS-DR-0802-417',
                    'notes' => 'Delivery received complete and in good condition.',
                    'lines' => $this->receiptLines(
                        $po,
                        $this->storage($organization, 'QCC-DRY'),
                        [
                            'PSS-RICE-25KG' => '4.000000',
                            'PSS-FLOUR-25KG' => '2.000000',
                            'PSS-OIL-16L' => '2.000000',
                        ],
                    ),
                ],
            );

            $finalizeGoodsReceipt->handle(
                $organization,
                $receiver,
                $receipt,
            );

            /*
             * Cancelled PO with its draft receipt explicitly cancelled first.
             */
            Carbon::setTestNow('2026-08-02 13:00:00');

            $cancelledPo = $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'MDB')->id,
                    'location_id' => $this->location($organization, 'MKT')->id,
                    'number' => 'PO-2026-0062',
                    'order_date' => '2026-08-02',
                    'expected_delivery_date' => '2026-08-03',
                    'tax_total' => '0.00',
                    'discount_total' => '0.00',
                    'notes' => 'Initial dairy order superseded after branch requirement changed.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'MDB-EGG-TRAY')->id,
                            'ordered_quantity' => '4.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'MDB-MILK-12L')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                    ],
                ],
            );

            $cancelledPo = $approvePurchaseOrder->handle(
                $organization,
                $manager,
                $cancelledPo,
            );

            $cancelledReceipt = $saveGoodsReceipt->handle(
                $organization,
                $receiver,
                $cancelledPo,
                [
                    'number' => 'GR-2026-0048',
                    'supplier_reference' => 'MDB-PREALERT-0802',
                    'notes' => 'Receipt cancelled before finalization when delivery was rescheduled.',
                    'lines' => $this->receiptLines(
                        $cancelledPo,
                        $this->storage($organization, 'MKT-CHILL'),
                        [
                            'MDB-EGG-TRAY' => '1.000000',
                            'MDB-MILK-12L' => '1.000000',
                        ],
                    ),
                ],
            );

            $cancelGoodsReceipt->handle(
                $organization,
                $receiver,
                $cancelledReceipt,
            );

            $cancelPurchaseOrder->handle(
                $organization,
                $manager,
                $cancelledPo,
            );

            /*
             * Fully received beverage order.
             */
            Carbon::setTestNow('2026-08-03 09:15:00');

            $beveragePo = $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'CCB')->id,
                    'location_id' => $this->location($organization, 'BGC')->id,
                    'number' => 'PO-2026-0063',
                    'order_date' => '2026-08-03',
                    'expected_delivery_date' => '2026-08-04',
                    'tax_total' => '0.00',
                    'discount_total' => '120.00',
                    'notes' => 'Weekend beverage and coffee replenishment.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'CCB-ARABICA-1KG')->id,
                            'ordered_quantity' => '5.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'CCB-COLA-24')->id,
                            'ordered_quantity' => '5.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'CCB-WATER-24')->id,
                            'ordered_quantity' => '5.000000',
                        ],
                    ],
                ],
            );

            $beveragePo = $approvePurchaseOrder->handle(
                $organization,
                $manager,
                $beveragePo,
            );

            Carbon::setTestNow('2026-08-04 11:20:00');

            $beverageReceipt = $saveGoodsReceipt->handle(
                $organization,
                $receiver,
                $beveragePo,
                [
                    'number' => 'GR-2026-0049',
                    'supplier_reference' => 'CCB-INV-26381',
                    'notes' => 'Complete beverage delivery received at BGC.',
                    'lines' => $this->receiptLines(
                        $beveragePo,
                        $this->storage($organization, 'BGC-BAR'),
                        [
                            'CCB-ARABICA-1KG' => '5.000000',
                            'CCB-COLA-24' => '5.000000',
                            'CCB-WATER-24' => '5.000000',
                        ],
                    ),
                ],
            );

            $finalizeGoodsReceipt->handle(
                $organization,
                $receiver,
                $beverageReceipt,
            );

            /*
             * Partially received meat PO with rejected/damaged evidence.
             */
            Carbon::setTestNow('2026-08-05 08:30:00');

            $meatPo = $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'MFF')->id,
                    'location_id' => $this->location($organization, 'MKT')->id,
                    'number' => 'PO-2026-0064',
                    'order_date' => '2026-08-05',
                    'expected_delivery_date' => '2026-08-06',
                    'tax_total' => '0.00',
                    'discount_total' => '250.00',
                    'notes' => 'Protein replenishment for Makati weekend service.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'MFF-CHK-10KG')->id,
                            'ordered_quantity' => '3.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'MFF-PORK-10KG')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                    ],
                ],
            );

            $meatPo = $approvePurchaseOrder->handle(
                $organization,
                $manager,
                $meatPo,
            );

            Carbon::setTestNow('2026-08-06 07:45:00');

            $chickenItem = $this->supplierItem(
                $organization,
                'MFF-CHK-10KG',
            );
            $porkItem = $this->supplierItem(
                $organization,
                'MFF-PORK-10KG',
            );

            $meatReceipt = $saveGoodsReceipt->handle(
                $organization,
                $receiver,
                $meatPo,
                [
                    'number' => 'GR-2026-0050',
                    'supplier_reference' => 'MFF-DR-0806-912',
                    'notes' => 'Partial delivery accepted; temperature-damaged cartons retained as receiving evidence.',
                    'lines' => [
                        [
                            'purchase_order_line_id' => $this->purchaseOrderLine($meatPo, $chickenItem)->id,
                            'storage_location_id' => $this->storage($organization, 'MKT-CHILL')->id,
                            'received_quantity' => '2.000000',
                            'received_unit_of_measure_id' => $chickenItem->purchase_unit_of_measure_id,
                            'rejected_quantity' => '0.100000',
                            'rejected_unit_of_measure_id' => $chickenItem->purchase_unit_of_measure_id,
                            'damaged_quantity' => '0.100000',
                            'damaged_unit_of_measure_id' => $chickenItem->purchase_unit_of_measure_id,
                            'notes' => 'Two cases accepted; one partial case showed temperature-abuse evidence.',
                        ],
                        [
                            'purchase_order_line_id' => $this->purchaseOrderLine($meatPo, $porkItem)->id,
                            'storage_location_id' => $this->storage($organization, 'MKT-CHILL')->id,
                            'received_quantity' => '1.000000',
                            'received_unit_of_measure_id' => $porkItem->purchase_unit_of_measure_id,
                            'rejected_quantity' => '0.000000',
                            'damaged_quantity' => '0.100000',
                            'damaged_unit_of_measure_id' => $porkItem->purchase_unit_of_measure_id,
                            'notes' => 'One case accepted; damaged portion documented.',
                        ],
                    ],
                ],
            );

            $finalizeGoodsReceipt->handle(
                $organization,
                $receiver,
                $meatReceipt,
            );

            /*
             * Approved produce PO with a draft receipt awaiting finalization.
             */
            Carbon::setTestNow('2026-08-07 08:30:00');

            $producePo = $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'GHP')->id,
                    'location_id' => $this->location($organization, 'MKT')->id,
                    'number' => 'PO-2026-0065',
                    'order_date' => '2026-08-07',
                    'expected_delivery_date' => '2026-08-08',
                    'tax_total' => '0.00',
                    'discount_total' => '0.00',
                    'notes' => 'Fresh produce replenishment awaiting receiver verification.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'GHP-GARLIC-5KG')->id,
                            'ordered_quantity' => '1.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'GHP-ONION-10KG')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'GHP-TOMATO-10KG')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                    ],
                ],
            );

            $producePo = $approvePurchaseOrder->handle(
                $organization,
                $manager,
                $producePo,
            );

            Carbon::setTestNow('2026-08-08 09:10:00');

            $saveGoodsReceipt->handle(
                $organization,
                $receiver,
                $producePo,
                [
                    'number' => 'GR-2026-0051',
                    'supplier_reference' => 'GHP-DEL-0808',
                    'notes' => 'Draft receiving session pending final quality inspection.',
                    'lines' => $this->receiptLines(
                        $producePo,
                        $this->storage($organization, 'MKT-CHILL'),
                        [
                            'GHP-GARLIC-5KG' => '0.500000',
                            'GHP-TOMATO-10KG' => '1.000000',
                        ],
                    ),
                ],
            );

            /*
             * Draft packaging PO awaiting approval.
             */
            Carbon::setTestNow('2026-08-09 15:20:00');

            $savePurchaseOrder->handle(
                $organization,
                $manager,
                [
                    'supplier_id' => $this->supplier($organization, 'PFP')->id,
                    'location_id' => $this->location($organization, 'BGC')->id,
                    'number' => 'PO-2026-0066',
                    'order_date' => '2026-08-09',
                    'expected_delivery_date' => '2026-08-13',
                    'tax_total' => '0.00',
                    'discount_total' => '100.00',
                    'notes' => 'Packaging replenishment draft pending manager review.',
                    'lines' => [
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PFP-BOWL-500')->id,
                            'ordered_quantity' => '3.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PFP-CUP-1000')->id,
                            'ordered_quantity' => '2.000000',
                        ],
                        [
                            'supplier_item_id' => $this->supplierItem($organization, 'PFP-NAPKIN-500')->id,
                            'ordered_quantity' => '4.000000',
                        ],
                    ],
                ],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Build accepted-only receipt lines from supplier SKU quantities.
     *
     * @param  array<string, string>  $quantities
     * @return list<array<string, mixed>>
     */
    private function receiptLines(
        PurchaseOrder $purchaseOrder,
        StorageLocation $storageLocation,
        array $quantities,
    ): array {
        $lines = [];

        foreach ($quantities as $supplierSku => $quantity) {
            $supplierItem = SupplierItem::query()
                ->where(
                    'organization_id',
                    $purchaseOrder->organization_id,
                )
                ->where('supplier_sku', $supplierSku)
                ->sole();

            $lines[] = [
                'purchase_order_line_id' => $this
                    ->purchaseOrderLine($purchaseOrder, $supplierItem)
                    ->id,
                'storage_location_id' => $storageLocation->id,
                'received_quantity' => $quantity,
                'received_unit_of_measure_id' => $supplierItem
                    ->purchase_unit_of_measure_id,
                'rejected_quantity' => '0.000000',
                'damaged_quantity' => '0.000000',
                'notes' => null,
            ];
        }

        return $lines;
    }

    /**
     * Resolve one PO line from its snapshotted supplier item.
     */
    private function purchaseOrderLine(
        PurchaseOrder $purchaseOrder,
        SupplierItem $supplierItem,
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('supplier_item_id', $supplierItem->id)
            ->sole();
    }

    /**
     * Resolve one tenant supplier.
     */
    private function supplier(
        Organization $organization,
        string $code,
    ): Supplier {
        return Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('code', $code)
            ->sole();
    }

    /**
     * Resolve one active supplier mapping.
     */
    private function supplierItem(
        Organization $organization,
        string $supplierSku,
    ): SupplierItem {
        return SupplierItem::query()
            ->where('organization_id', $organization->id)
            ->where('supplier_sku', $supplierSku)
            ->where('active', true)
            ->sole();
    }

    /**
     * Resolve one active demo branch.
     */
    private function location(
        Organization $organization,
        string $code,
    ): Location {
        return Location::query()
            ->where('organization_id', $organization->id)
            ->where('code', $code)
            ->where('active', true)
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
