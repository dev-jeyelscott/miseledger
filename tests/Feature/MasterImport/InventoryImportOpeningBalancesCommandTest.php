<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;

test('the import-opening-balances command reads a CSV file and reports row errors', function () {
    $organization = Organization::factory()->create();

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'MAIN',
        'active' => true,
    ]);

    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Storage MAIN';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();

    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'sku' => 'FLOUR-001',
        'active' => true,
    ]);

    $actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'opening-balances-');
    file_put_contents(
        $path,
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,1,kg,1.00\nMAIN,MAIN,UNKNOWN-SKU,1,kg,1.00\n",
    );

    $this->artisan('inventory:import-opening-balances', [
        'organization' => $organization->id,
        'actor' => $actor->id,
        'batch' => 'cli-batch',
        'file' => $path,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('1 created, 0 skipped as already imported, 1 row error')
        ->expectsOutputToContain('Row 3:');

    unlink($path);

    expect(StockMovement::query()->count())->toBe(1);
});

test('the import-opening-balances command fails for an unknown organization', function () {
    $path = tempnam(sys_get_temp_dir(), 'opening-balances-');
    file_put_contents($path, "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\n");

    $this->artisan('inventory:import-opening-balances', [
        'organization' => 999999,
        'actor' => 1,
        'batch' => 'cli-batch',
        'file' => $path,
    ])->assertExitCode(1);

    unlink($path);
});

test('the import-opening-balances command fails for an unknown actor', function () {
    $organization = Organization::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'opening-balances-');
    file_put_contents($path, "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\n");

    $this->artisan('inventory:import-opening-balances', [
        'organization' => $organization->id,
        'actor' => 999999,
        'batch' => 'cli-batch',
        'file' => $path,
    ])->assertExitCode(1);

    unlink($path);
});
