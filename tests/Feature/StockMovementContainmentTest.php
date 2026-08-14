<?php

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('stock movement cannot reference a location from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();

    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
    ]);

    expect(fn () => DB::table('stock_movements')->insert([
        'organization_id' => $organization->id,
        'location_id' => $otherLocation->id,
        'storage_location_id' => $storageLocation->id,
        'inventory_item_id' => $item->id,
        'type' => 'OPENING_BALANCE',
        'quantity' => '1.000000',
        'base_unit_of_measure_id' => $unit->id,
        'unit_cost' => '1.0000',
        'total_cost' => '1.0000',
        'reference_type' => 'opening_balance',
        'reference_id' => 1,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});
