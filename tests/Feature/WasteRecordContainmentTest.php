<?php

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\WasteReason;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create a storage location without depending on application validation helpers.
 */
function createWasteContainmentStorageForTest(
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
 * Build one otherwise-valid waste row for direct database containment tests.
 *
 * @return array<string, mixed>
 */
function wasteContainmentRowForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $inventoryItem,
    WasteReason $wasteReason,
    UnitOfMeasure $unit,
): array {
    return [
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storageLocation->id,
        'inventory_item_id' => $inventoryItem->id,
        'waste_reason_id' => $wasteReason->id,
        'operation_id' => (string) Str::uuid(),
        'quantity' => '1.000000',
        'unit_id' => $unit->id,
        'base_quantity' => '1.000000',
        'unit_cost' => '1.0000',
        'total_cost' => '1.0000',
        'occurred_at' => now(),
        'notes' => 'Database containment regression test.',
    ];
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->storageLocation = createWasteContainmentStorageForTest(
        $this->organization,
        $this->location,
        'MAIN',
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
    ]);

    $this->wasteReason = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $this->otherOrganization = Organization::factory()->create();

    $this->otherLocation = Location::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $this->otherStorageLocation = createWasteContainmentStorageForTest(
        $this->otherOrganization,
        $this->otherLocation,
        'OTHER',
    );

    $this->otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $this->otherInventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'base_unit_of_measure_id' => $this->otherUnit->id,
    ]);

    $this->otherWasteReason = WasteReason::query()->create([
        'organization_id' => $this->otherOrganization->id,
        'name' => 'Other Tenant Spoilage',
        'active' => true,
    ]);

    $this->secondLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->secondStorageLocation = createWasteContainmentStorageForTest(
        $this->organization,
        $this->secondLocation,
        'SECOND',
    );
});

test('database accepts a fully contained waste record', function () {
    $row = wasteContainmentRowForTest(
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->inventoryItem,
        $this->wasteReason,
        $this->unit,
    );

    expect(
        DB::table('waste_records')->insert($row),
    )->toBeTrue();
});

test(
    'database rejects cross tenant waste relationships',
    function (string $column) {
        $row = wasteContainmentRowForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->wasteReason,
            $this->unit,
        );

        $row[$column] = match ($column) {
            'location_id' => $this->otherLocation->id,
            'storage_location_id' => $this->otherStorageLocation->id,
            'inventory_item_id' => $this->otherInventoryItem->id,
            'waste_reason_id' => $this->otherWasteReason->id,
            'unit_id' => $this->otherUnit->id,
        };

        expect(
            fn () => DB::table('waste_records')->insert($row),
        )->toThrow(QueryException::class);
    },
)->with([
    'location' => ['location_id'],
    'storage location' => ['storage_location_id'],
    'inventory item' => ['inventory_item_id'],
    'waste reason' => ['waste_reason_id'],
    'entered unit' => ['unit_id'],
]);

test(
    'database rejects storage belonging to another location in the same organization',
    function () {
        $row = wasteContainmentRowForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->wasteReason,
            $this->unit,
        );

        $row['storage_location_id'] =
            $this->secondStorageLocation->id;

        expect(
            fn () => DB::table('waste_records')->insert($row),
        )->toThrow(QueryException::class);
    },
);
