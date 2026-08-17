<?php

namespace Database\Seeders;

use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\RecordWaste;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\SaveStorageLocation;
use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Suppliers\RecordSupplierItemPrice;
use App\Actions\Suppliers\SaveSupplierItem;
use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed one coherent local-only dataset through existing domain workflows.
     */
    public function run(
        CreateOrganization $createOrganization,
        AddOrganizationMember $addOrganizationMember,
        SaveStorageLocation $saveStorageLocation,
        SaveSupplierItem $saveSupplierItem,
        RecordSupplierItemPrice $recordSupplierItemPrice,
        SavePurchaseOrder $savePurchaseOrder,
        ApprovePurchaseOrder $approvePurchaseOrder,
        SaveGoodsReceipt $saveGoodsReceipt,
        FinalizeGoodsReceipt $finalizeGoodsReceipt,
        RecordWaste $recordWaste,
        SaveStockCount $saveStockCount,
        SubmitStockCount $submitStockCount,
        FinalizeStockCount $finalizeStockCount,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        DB::transaction(function () use (
            $createOrganization,
            $addOrganizationMember,
            $saveStorageLocation,
            $saveSupplierItem,
            $recordSupplierItemPrice,
            $savePurchaseOrder,
            $approvePurchaseOrder,
            $saveGoodsReceipt,
            $finalizeGoodsReceipt,
            $recordWaste,
            $saveStockCount,
            $submitStockCount,
            $finalizeStockCount,
        ): void {
            User::factory()->create([
                'name' => 'MiseLedger Super Admin',
                'email' => 'superadmin@miseledger.com',
            ]);

            $owner = User::factory()->create([
                'name' => 'MiseLedger Owner',
                'email' => 'owner@miseledger.com',
            ]);

            $organization = $createOrganization->handle(
                $owner,
                'MiseLedger Demo Restaurant',
            );

            /** @var list<array{name: string, email: string, role: OrganizationRole}> $roleAccounts */
            $roleAccounts = [
                [
                    'name' => 'Organization Manager',
                    'email' => 'manager@miseledger.com',
                    'role' => OrganizationRole::Manager,
                ],
                [
                    'name' => 'Inventory Staff',
                    'email' => 'inventory@miseledger.com',
                    'role' => OrganizationRole::InventoryStaff,
                ],
                [
                    'name' => 'Kitchen Staff',
                    'email' => 'kitchen@miseledger.com',
                    'role' => OrganizationRole::KitchenStaff,
                ],
                [
                    'name' => 'Auditor Staff',
                    'email' => 'auditor@miseledger.com',
                    'role' => OrganizationRole::Auditor,
                ],
            ];

            foreach ($roleAccounts as $account) {
                $user = User::factory()->create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                ]);

                $addOrganizationMember->handle(
                    $organization,
                    $owner,
                    $user,
                    $account['role'],
                );
            }

            /** @var list<array{name: string, code: string, storage_name: string, storage_code: string}> $locationDefinitions */
            $locationDefinitions = [
                [
                    'name' => 'Makati Branch',
                    'code' => 'MKT',
                    'storage_name' => 'Makati Main Storage',
                    'storage_code' => 'MKT-MAIN',
                ],
                [
                    'name' => 'BGC Branch',
                    'code' => 'BGC',
                    'storage_name' => 'BGC Main Storage',
                    'storage_code' => 'BGC-MAIN',
                ],
            ];

            $locations = [];
            $storageLocations = [];

            foreach ($locationDefinitions as $definition) {
                $location = Location::factory()->create([
                    'organization_id' => $organization->id,
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'active' => true,
                ]);

                $locations[] = $location;

                $storageLocations[$location->id] = $saveStorageLocation->handle(
                    $organization,
                    $location,
                    [
                        'name' => $definition['storage_name'],
                        'code' => $definition['storage_code'],
                        'active' => true,
                    ],
                );
            }

            $baseUnits = [
                UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('symbol', 'kg')
                    ->where('active', true)
                    ->firstOrFail(),

                UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('symbol', 'l')
                    ->where('active', true)
                    ->firstOrFail(),

                UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('symbol', 'piece')
                    ->where('active', true)
                    ->firstOrFail(),
            ];

            $inventoryItems = [];

            for ($index = 1; $index <= 50; $index++) {
                $baseUnit = $baseUnits[
                    ($index - 1) % count($baseUnits)
                ];

                $inventoryItems[] = InventoryItem::factory()->create([
                    'organization_id' => $organization->id,
                    'base_unit_of_measure_id' => $baseUnit->id,
                    'name' => sprintf(
                        'Demo Inventory Item %02d',
                        $index,
                    ),
                    'sku' => sprintf('DEMO-%03d', $index),
                    'active' => true,
                ]);
            }

            $wasteReason = WasteReason::query()
                ->where('organization_id', $organization->id)
                ->where('name', 'Spoilage')
                ->sole();

            for ($index = 0; $index < 5; $index++) {
                $number = $index + 1;
                $inventoryItem = $inventoryItems[$index];
                $location = $locations[
                    $index % count($locations)
                ];
                $storageLocation = $storageLocations[
                    $location->id
                ];

                $supplier = Supplier::factory()->create([
                    'organization_id' => $organization->id,
                    'name' => sprintf(
                        'Demo Supplier %d',
                        $number,
                    ),
                    'code' => sprintf(
                        'SUP-%03d',
                        $number,
                    ),
                    'active' => true,
                ]);

                $supplierItem = $saveSupplierItem->handle(
                    $organization,
                    $supplier,
                    [
                        'inventory_item_id' => $inventoryItem->id,
                        'supplier_sku' => sprintf(
                            'SUP-%03d-ITEM',
                            $number,
                        ),
                        'description' => sprintf(
                            'Demo purchase mapping for %s',
                            $inventoryItem->name,
                        ),
                        'purchase_unit_of_measure_id' => $inventoryItem
                            ->base_unit_of_measure_id,
                        'base_quantity' => '1.000000',
                        'active' => true,
                    ],
                );

                $recordSupplierItemPrice->handle(
                    $organization,
                    $supplierItem,
                    sprintf(
                        '%d.0000',
                        100 + ($number * 10),
                    ),
                );

                $orderDate = now()->subDays(
                    10 - $index,
                );

                $purchaseOrder = $savePurchaseOrder->handle(
                    $organization,
                    $owner,
                    [
                        'supplier_id' => $supplier->id,
                        'location_id' => $location->id,
                        'number' => sprintf(
                            'PO-DEMO-%03d',
                            $number,
                        ),
                        'order_date' => $orderDate
                            ->toDateString(),
                        'expected_delivery_date' => $orderDate
                            ->copy()
                            ->addDays(3)
                            ->toDateString(),
                        'tax_total' => '0.00',
                        'discount_total' => '0.00',
                        'notes' => 'Seeded demo purchase order.',
                        'lines' => [
                            [
                                'supplier_item_id' => $supplierItem->id,
                                'ordered_quantity' => '100.000000',
                            ],
                        ],
                    ],
                );

                $purchaseOrder = $approvePurchaseOrder->handle(
                    $organization,
                    $owner,
                    $purchaseOrder,
                );

                $purchaseOrderLine = $purchaseOrder
                    ->lines()
                    ->firstOrFail();

                $goodsReceipt = $saveGoodsReceipt->handle(
                    $organization,
                    $owner,
                    $purchaseOrder,
                    [
                        'number' => sprintf(
                            'GR-DEMO-%03d',
                            $number,
                        ),
                        'supplier_reference' => sprintf(
                            'SUP-REF-%03d',
                            $number,
                        ),
                        'notes' => 'Seeded demo goods receipt.',
                        'lines' => [
                            [
                                'purchase_order_line_id' => $purchaseOrderLine->id,
                                'storage_location_id' => $storageLocation->id,
                                'received_quantity' => '100.000000',
                                'received_unit_of_measure_id' => $inventoryItem
                                    ->base_unit_of_measure_id,
                                'rejected_quantity' => '0.000000',
                                'damaged_quantity' => '0.000000',
                                'notes' => null,
                            ],
                        ],
                    ],
                );

                $finalizeGoodsReceipt->handle(
                    $organization,
                    $owner,
                    $goodsReceipt,
                );

                $recordWaste->handle(
                    $organization,
                    $owner,
                    [
                        'operation_id' => (string) Str::uuid(),
                        'location_id' => $location->id,
                        'storage_location_id' => $storageLocation->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'waste_reason_id' => $wasteReason->id,
                        'quantity' => '2.000000',
                        'unit_id' => $inventoryItem
                            ->base_unit_of_measure_id,
                        'occurred_at' => now()
                            ->setTimezone(
                                $organization->timezone,
                            )
                            ->format('Y-m-d H:i:s.u'),
                        'notes' => 'Seeded demo waste.',
                    ],
                );

                $stockCount = $saveStockCount->handle(
                    $organization,
                    $owner,
                    [
                        'location_id' => $location->id,
                        'storage_location_id' => $storageLocation->id,
                        'number' => sprintf(
                            'SC-DEMO-%03d',
                            $number,
                        ),
                        'lines' => [
                            [
                                'inventory_item_id' => $inventoryItem->id,
                                'counted_quantity' => '98.000000',
                                'count_unit_id' => $inventoryItem
                                    ->base_unit_of_measure_id,
                                'notes' => 'Seeded demo physical count.',
                            ],
                        ],
                    ],
                );

                $stockCount = $submitStockCount->handle(
                    $organization,
                    $owner,
                    $stockCount,
                );

                $finalizeStockCount->handle(
                    $organization,
                    $owner,
                    $stockCount,
                );
            }
        });
    }
}
