<?php

use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\RecordWaste;
use App\Actions\Inventory\ReplayStockLedger;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Actions\Inventory\SubmitStockCount;
use App\Actions\Inventory\SyncInventoryItemOptionValues;
use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Suppliers\RecordSupplierItemPrice;
use App\Actions\Suppliers\SaveSupplierItem;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function createVariantWorkflowStorage(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation([
        'name' => "Storage {$code}",
        'code' => $code,
        'active' => true,
    ]);

    $storageLocation->organization()->associate($organization);
    $storageLocation->location()->associate($location);
    $storageLocation->save();

    return $storageLocation;
}

function createVariantWorkflowItem(
    Organization $organization,
    InventoryProduct $product,
    UnitOfMeasure $unit,
    string $name,
    string $sku,
): InventoryItem {
    return InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'inventory_product_id' => $product->id,
        'base_unit_of_measure_id' => $unit->id,
        'name' => $name,
        'sku' => $sku,
        'active' => true,
    ]);
}

test('variant inventory items retain independent ledger-backed stock across existing item workflows', function () {
    $organization = Organization::factory()->create(['currency' => 'PHP']);
    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $destinationLocation = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $mainStorage = createVariantWorkflowStorage($organization, $location, 'MAIN');
    $destinationStorage = createVariantWorkflowStorage(
        $organization,
        $destinationLocation,
        'DEST',
    );
    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Each',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);
    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $small = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Small',
    ]);
    $large = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Large',
    ]);
    $smallItem = createVariantWorkflowItem(
        $organization,
        $product,
        $unit,
        'Variant Small',
        'VAR-SMALL',
    );
    $largeItem = createVariantWorkflowItem(
        $organization,
        $product,
        $unit,
        'Variant Large',
        'VAR-LARGE',
    );
    app(SyncInventoryItemOptionValues::class)->handle($organization, $smallItem, [$small->id]);
    app(SyncInventoryItemOptionValues::class)->handle($organization, $largeItem, [$large->id]);

    $actor = User::factory()->create();
    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
        'role' => OrganizationRole::Manager,
    ]);
    $supplier = Supplier::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $supplierItems = collect([$smallItem, $largeItem])->map(
        fn (InventoryItem $inventoryItem) => app(SaveSupplierItem::class)->handle(
            $organization,
            $supplier,
            [
                'inventory_item_id' => $inventoryItem->id,
                'supplier_sku' => "SUP-{$inventoryItem->sku}",
                'description' => null,
                'purchase_unit_of_measure_id' => $unit->id,
                'base_quantity' => '1.000000',
                'active' => true,
            ],
        ),
    )->values();
    $supplierItems->each(
        fn ($supplierItem) => app(RecordSupplierItemPrice::class)->handle(
            $organization,
            $supplierItem,
            '10.0000',
        ),
    );

    $purchaseOrder = app(SavePurchaseOrder::class)->handle(
        $organization,
        $actor,
        [
            'number' => 'PO-VARIANTS-1',
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'order_date' => now()->toDateString(),
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'lines' => [
                ['supplier_item_id' => $supplierItems[0]->id, 'ordered_quantity' => '20'],
                ['supplier_item_id' => $supplierItems[1]->id, 'ordered_quantity' => '10'],
            ],
        ],
    );
    $purchaseOrder = app(ApprovePurchaseOrder::class)->handle(
        $organization,
        $actor,
        $purchaseOrder,
    );
    $receipt = app(SaveGoodsReceipt::class)->handle(
        $organization,
        $actor,
        $purchaseOrder,
        [
            'number' => 'GR-VARIANTS-1',
            'lines' => $purchaseOrder->lines->map(fn ($line) => [
                'purchase_order_line_id' => $line->id,
                'storage_location_id' => $mainStorage->id,
                'received_quantity' => $line->ordered_quantity,
                'received_unit_of_measure_id' => $unit->id,
            ])->all(),
        ],
    );
    app(FinalizeGoodsReceipt::class)->handle($organization, $actor, $receipt);

    $stockCount = app(SaveStockCount::class)->handle($organization, $actor, [
        'number' => 'SC-VARIANTS-1',
        'location_id' => $location->id,
        'storage_location_id' => $mainStorage->id,
        'lines' => [
            ['inventory_item_id' => $smallItem->id, 'counted_quantity' => '18', 'count_unit_id' => $unit->id],
            ['inventory_item_id' => $largeItem->id, 'counted_quantity' => '10', 'count_unit_id' => $unit->id],
        ],
    ]);
    $stockCount = app(SubmitStockCount::class)->handle($organization, $actor, $stockCount);
    app(FinalizeStockCount::class)->handle($organization, $actor, $stockCount);

    $transfer = app(SaveStockTransfer::class)->handle($organization, $actor, [
        'number' => 'ST-VARIANTS-1',
        'from_location_id' => $location->id,
        'from_storage_location_id' => $mainStorage->id,
        'to_location_id' => $destinationLocation->id,
        'to_storage_location_id' => $destinationStorage->id,
        'lines' => [
            ['inventory_item_id' => $largeItem->id, 'requested_quantity' => '3', 'unit_id' => $unit->id],
        ],
    ]);
    $transfer = app(ShipStockTransfer::class)->handle($organization, $actor, $transfer);
    app(ReceiveStockTransfer::class)->handle($organization, $actor, $transfer, [
        'lines' => $transfer->lines->map(fn ($line) => [
            'id' => $line->id,
            'received_base_quantity' => $line->shipped_base_quantity,
        ])->all(),
    ]);

    $wasteReason = WasteReason::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Damaged',
        'active' => true,
    ]);
    app(RecordWaste::class)->handle($organization, $actor, [
        'operation_id' => (string) Str::uuid(),
        'location_id' => $location->id,
        'storage_location_id' => $mainStorage->id,
        'inventory_item_id' => $smallItem->id,
        'waste_reason_id' => $wasteReason->id,
        'quantity' => '1',
        'unit_id' => $unit->id,
        'occurred_at' => now()->toIso8601String(),
    ]);

    $smallBalance = StockBalance::query()
        ->where('storage_location_id', $mainStorage->id)
        ->where('inventory_item_id', $smallItem->id)
        ->sole();
    $largeMainBalance = StockBalance::query()
        ->where('storage_location_id', $mainStorage->id)
        ->where('inventory_item_id', $largeItem->id)
        ->sole();
    $largeDestinationBalance = StockBalance::query()
        ->where('storage_location_id', $destinationStorage->id)
        ->where('inventory_item_id', $largeItem->id)
        ->sole();

    expect($supplierItems->pluck('inventory_item_id')->all())
        ->toBe([$smallItem->id, $largeItem->id])
        ->and(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt->value)->pluck('inventory_item_id')->sort()->values()->all())
        ->toBe([$smallItem->id, $largeItem->id])
        ->and($smallBalance->quantity_on_hand)->toBe('17.000000')
        ->and($largeMainBalance->quantity_on_hand)->toBe('7.000000')
        ->and($largeDestinationBalance->quantity_on_hand)->toBe('3.000000')
        ->and(app(ReplayStockLedger::class)->handle($organization->id, $location->id, $mainStorage->id, $smallItem->id)['quantity_on_hand'])
        ->toBe($smallBalance->quantity_on_hand)
        ->and(app(ReplayStockLedger::class)->handle($organization->id, $location->id, $mainStorage->id, $largeItem->id)['quantity_on_hand'])
        ->toBe($largeMainBalance->quantity_on_hand);

    $this->actingAs($actor)
        ->withSession(['active_organization_id' => $organization->id])
        ->get(route('inventory.stock-on-hand.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 3)
            ->where('rows.0.itemSku', 'VAR-SMALL')
            ->where('rows.1.itemSku', 'VAR-LARGE')
            ->where('rows.2.itemSku', 'VAR-LARGE'));
});

test('variant workflow actions reject a variant from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $supplier = Supplier::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);
    $otherProduct = InventoryProduct::factory()->for($otherOrganization)->create();
    $otherVariant = createVariantWorkflowItem(
        $otherOrganization,
        $otherProduct,
        $otherUnit,
        'Other Variant',
        'OTHER-VAR',
    );

    expect(fn () => app(SaveSupplierItem::class)->handle($organization, $supplier, [
        'inventory_item_id' => $otherVariant->id,
        'supplier_sku' => 'OTHER-VAR',
        'description' => null,
        'purchase_unit_of_measure_id' => $otherUnit->id,
        'base_quantity' => '1.000000',
        'active' => true,
    ]))->toThrow(ValidationException::class);
});
