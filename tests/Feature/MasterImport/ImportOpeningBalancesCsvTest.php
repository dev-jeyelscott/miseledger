<?php

use App\Actions\MasterImport\ImportOpeningBalances;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Create an active storage location for opening-balance import tests.
 */
function createOpeningBalanceImportStorage(
    Organization $organization,
    Location $location,
    string $code = 'MAIN',
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'MAIN',
        'active' => true,
    ]);

    $this->storageLocation = createOpeningBalanceImportStorage(
        $this->organization,
        $this->location,
    );

    $this->gram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'sku' => 'FLOUR-001',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);
});

test('every row resolves to the item base unit and records an opening-balance movement', function () {
    $result = app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-1',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost,notes\nMAIN,MAIN,FLOUR-001,2.5,kg,0.0400,Initial count\n",
    );

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(0)
        ->and($result->errors)->toBe([]);

    $movement = StockMovement::query()->sole();

    expect($movement->type)->toBe(StockMovementType::OpeningBalance)
        ->and($movement->quantity)->toBe('2500.000000')
        ->and($movement->base_unit_of_measure_id)->toBe($this->gram->id)
        ->and($movement->reference_type)->toBe('csv_opening_balance_import')
        ->and($movement->idempotency_key)->toBe('opening_balance:import:batch-1:2');

    $balance = StockBalance::query()->sole();

    expect($balance->quantity_on_hand)->toBe('2500.000000')
        ->and($balance->average_unit_cost)->toBe('0.0400');
});

test('retrying the same batch does not duplicate stock movements', function () {
    $csv = "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,2.5,kg,0.0400\n";

    $first = app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-retry',
        $csv,
    );

    $second = app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-retry',
        $csv,
    );

    expect($first->created)->toBe(1)
        ->and($first->skipped)->toBe(0)
        ->and($second->created)->toBe(0)
        ->and($second->skipped)->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1)
        ->and(StockBalance::query()->sole()->quantity_on_hand)->toBe('2500.000000');
});

test('a mismatched retry for the same batch and row is reported as a row error, not a silent overwrite', function () {
    app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-mismatch',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,2.5,kg,0.0400\n",
    );

    $result = app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-mismatch',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,9.0,kg,0.0400\n",
    );

    expect($result->created)->toBe(0)
        ->and($result->skipped)->toBe(0)
        ->and($result->errors)->toHaveCount(1)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('an invalid row is reported without blocking a valid row in the same file', function () {
    $result = app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-mixed',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,UNKNOWN-SKU,1,kg,1.00\nMAIN,MAIN,FLOUR-001,1,kg,1.00\n",
    );

    expect($result->created)->toBe(1)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->row)->toBe(2)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('no direct balance write occurs, only movements created through the opening-balance workflow', function () {
    app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        'batch-balance',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,1,kg,1.00\n",
    );

    expect(StockMovement::query()->count())->toBe(1)
        ->and(StockBalance::query()->count())->toBe(1)
        ->and(StockBalance::query()->sole()->quantity_on_hand)
        ->toBe(
            (string) StockMovement::query()->sole()->quantity,
        );
});

test('importing without permission is rejected before any movement is written', function () {
    $kitchenUser = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $kitchenUser->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    expect(fn () => app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $kitchenUser,
        'batch-unauthorized',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,1,kg,1.00\n",
    ))->toThrow(AuthorizationException::class);

    expect(StockMovement::query()->count())->toBe(0);
});

test('a blank batch identifier is rejected', function () {
    expect(fn () => app(ImportOpeningBalances::class)->handle(
        $this->organization,
        $this->actor,
        '   ',
        "location_code,storage_location_code,item_sku,quantity,unit_symbol,unit_cost\nMAIN,MAIN,FLOUR-001,1,kg,1.00\n",
    ))->toThrow(ValidationException::class);
});
