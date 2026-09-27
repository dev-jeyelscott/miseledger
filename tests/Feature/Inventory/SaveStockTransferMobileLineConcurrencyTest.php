<?php

use App\Actions\Inventory\SaveStockTransfer;
use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->owner)
        ->create(['role' => OrganizationRole::Owner]);

    $this->sourceLocation = Location::factory()
        ->for($this->organization)
        ->create(['active' => true]);

    $this->destinationLocation = Location::factory()
        ->for($this->organization)
        ->create(['active' => true]);

    $this->sourceStorage = new StorageLocation([
        'name' => 'Source Storage',
        'code' => 'SOURCE',
        'active' => true,
    ]);
    $this->sourceStorage->organization()->associate($this->organization);
    $this->sourceStorage->location()->associate($this->sourceLocation);
    $this->sourceStorage->save();

    $this->destinationStorage = new StorageLocation([
        'name' => 'Destination Storage',
        'code' => 'DEST',
        'active' => true,
    ]);
    $this->destinationStorage->organization()->associate($this->organization);
    $this->destinationStorage->location()->associate($this->destinationLocation);
    $this->destinationStorage->save();

    $this->baseUnit = UnitOfMeasure::factory()
        ->for($this->organization)
        ->create(['dimension' => 'count', 'active' => true]);

    $this->itemA = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->itemB = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->itemC = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->transfer = app(SaveStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        [
            'number' => 'TR-MOBILE-RACE-001',
            'from_location_id' => $this->sourceLocation->id,
            'from_storage_location_id' => $this->sourceStorage->id,
            'to_location_id' => $this->destinationLocation->id,
            'to_storage_location_id' => $this->destinationStorage->id,
            'notes' => null,
            'lines' => [[
                'inventory_item_id' => $this->itemA->id,
                'requested_quantity' => '1.000000',
                'unit_id' => $this->baseUnit->id,
            ]],
        ],
    );
});

test('two concurrent mobile scans against the same draft transfer both land instead of one silently overwriting the other', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    $transferId = $this->transfer->id;
    $organization = $this->organization;
    $owner = $this->owner;
    $sourceLocationId = $this->sourceLocation->id;
    $sourceStorageId = $this->sourceStorage->id;
    $destinationLocationId = $this->destinationLocation->id;
    $destinationStorageId = $this->destinationStorage->id;
    $itemBId = $this->itemB->id;
    $itemCId = $this->itemC->id;
    $unitId = $this->baseUnit->id;

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-mobile-transfer-line-race-');

    if ($resultPath === false) {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to create the concurrency result file.');
    }

    $childPid = pcntl_fork();

    if ($childPid === -1) {
        unlink($resultPath);
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to fork the concurrency test process.');
    }

    if ($childPid === 0) {
        try {
            DB::transaction(function () use (
                $organization,
                $owner,
                $transferId,
                $sourceLocationId,
                $sourceStorageId,
                $destinationLocationId,
                $destinationStorageId,
                $itemBId,
                $unitId,
            ): void {
                // Acquire the same row lock `addMobileLine()` acquires, then
                // hold it briefly to force the parent process's concurrent
                // scan to block on it, widening the race window that the
                // pre-fix unlocked read-then-replace was vulnerable to.
                $transfer = StockTransfer::query()
                    ->whereKey($transferId)
                    ->lockForUpdate()
                    ->firstOrFail();

                usleep(500000);

                app(SaveStockTransfer::class)->addMobileLine(
                    $organization,
                    $owner,
                    [
                        'number' => $transfer->number,
                        'from_location_id' => $sourceLocationId,
                        'from_storage_location_id' => $sourceStorageId,
                        'to_location_id' => $destinationLocationId,
                        'to_storage_location_id' => $destinationStorageId,
                        'notes' => null,
                    ],
                    [
                        'inventory_item_id' => $itemBId,
                        'requested_quantity' => '2.000000',
                        'unit_id' => $unitId,
                    ],
                    $transfer,
                );
            });

            file_put_contents($resultPath, 'success');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultPath, get_class($exception).': '.$exception->getMessage());
            exit(1);
        }
    }

    // Give the child a head start so it acquires the draft's row lock before
    // this process attempts its own concurrent scan.
    usleep(100000);

    app(SaveStockTransfer::class)->addMobileLine(
        $organization,
        $owner,
        [
            'number' => $this->transfer->number,
            'from_location_id' => $sourceLocationId,
            'from_storage_location_id' => $sourceStorageId,
            'to_location_id' => $destinationLocationId,
            'to_storage_location_id' => $destinationStorageId,
            'notes' => null,
        ],
        [
            'inventory_item_id' => $itemCId,
            'requested_quantity' => '3.000000',
            'unit_id' => $unitId,
        ],
        $this->transfer,
    );

    $childReaped = false;

    try {
        pcntl_waitpid($childPid, $status);
        $childReaped = true;

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0)
            ->and(file_get_contents($resultPath))->toBe('success');

        $lines = StockTransfer::query()
            ->whereKey($transferId)
            ->firstOrFail()
            ->lines()
            ->pluck('inventory_item_id')
            ->all();

        expect($lines)->toEqualCanonicalizing([$this->itemA->id, $itemBId, $itemCId]);
    } finally {
        if (! $childReaped) {
            pcntl_waitpid($childPid, $status);
        }

        if (file_exists($resultPath)) {
            unlink($resultPath);
        }

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
    }
});
