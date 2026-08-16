<?php

use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Create storage used by direct stock-transfer-line containment tests.
 */
function createTransferLineContainmentStorageForTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storage = new StorageLocation;
    $storage->organization_id = $organization->id;
    $storage->location_id = $location->id;
    $storage->name = "Storage {$code}";
    $storage->code = $code;
    $storage->active = true;
    $storage->save();

    return $storage;
}

/**
 * Create an otherwise-valid transfer header for database containment tests.
 */
function createTransferLineContainmentTransferForTest(
    Organization $organization,
    Location $fromLocation,
    StorageLocation $fromStorage,
    Location $toLocation,
    StorageLocation $toStorage,
): StockTransfer {
    return StockTransfer::query()->create([
        'organization_id' => $organization->id,
        'from_location_id' => $fromLocation->id,
        'from_storage_location_id' => $fromStorage->id,
        'to_location_id' => $toLocation->id,
        'to_storage_location_id' => $toStorage->id,
        'number' => 'TR-CONTAINMENT',
        'status' => StockTransferStatus::Draft,
        'requested_at' => now(),
        'shipped_at' => null,
        'received_at' => null,
        'created_by' => null,
        'shipped_by' => null,
        'received_by' => null,
        'notes' => null,
    ]);
}

/**
 * Build one otherwise-valid stock-transfer-line database row.
 *
 * @return array<string, mixed>
 */
function stockTransferLineContainmentRowForTest(
    Organization $organization,
    StockTransfer $transfer,
    InventoryItem $inventoryItem,
    UnitOfMeasure $unit,
): array {
    return [
        'organization_id' => $organization->id,
        'stock_transfer_id' => $transfer->id,
        'inventory_item_id' => $inventoryItem->id,
        'requested_quantity' => '1.000000',
        'unit_id' => $unit->id,
        'requested_base_quantity' => '1.000000',
        'shipped_base_quantity' => null,
        'received_base_quantity' => null,
        'unit_cost' => null,
        'variance_base_quantity' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->fromLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->toLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->fromStorage =
        createTransferLineContainmentStorageForTest(
            $this->organization,
            $this->fromLocation,
            'FROM',
        );

    $this->toStorage =
        createTransferLineContainmentStorageForTest(
            $this->organization,
            $this->toLocation,
            'TO',
        );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Containment Piece',
        'symbol' => 'containment-piece',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
    ]);

    $this->transfer =
        createTransferLineContainmentTransferForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
        );

    $this->otherOrganization =
        Organization::factory()->create();

    $this->otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'name' => 'Other Containment Piece',
        'symbol' => 'other-containment-piece',
        'active' => true,
    ]);

    $this->otherInventoryItem =
        InventoryItem::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'base_unit_of_measure_id' => $this->otherUnit->id,
        ]);
});

test(
    'database accepts a fully contained stock transfer line',
    function () {
        $row = stockTransferLineContainmentRowForTest(
            $this->organization,
            $this->transfer,
            $this->inventoryItem,
            $this->unit,
        );

        expect(
            DB::table('stock_transfer_lines')->insert($row),
        )->toBeTrue();
    },
);

test(
    'database rejects cross tenant stock transfer line references',
    function (string $column) {
        $row = stockTransferLineContainmentRowForTest(
            $this->organization,
            $this->transfer,
            $this->inventoryItem,
            $this->unit,
        );

        $row[$column] = match ($column) {
            'inventory_item_id' => $this->otherInventoryItem->id,
            'unit_id' => $this->otherUnit->id,
        };

        expect(
            fn () => DB::table('stock_transfer_lines')
                ->insert($row),
        )->toThrow(QueryException::class);
    },
)->with([
    'inventory item' => ['inventory_item_id'],
    'entered unit' => ['unit_id'],
]);

test(
    'database rejects a line tenant that differs from its parent transfer',
    function () {
        $row = stockTransferLineContainmentRowForTest(
            $this->otherOrganization,
            $this->transfer,
            $this->otherInventoryItem,
            $this->otherUnit,
        );

        expect(
            fn () => DB::table('stock_transfer_lines')
                ->insert($row),
        )->toThrow(QueryException::class);
    },
);

test(
    'database preserves one inventory item per stock transfer',
    function () {
        $row = stockTransferLineContainmentRowForTest(
            $this->organization,
            $this->transfer,
            $this->inventoryItem,
            $this->unit,
        );

        DB::table('stock_transfer_lines')->insert($row);

        expect(
            fn () => DB::table('stock_transfer_lines')
                ->insert($row),
        )->toThrow(QueryException::class);
    },
);
