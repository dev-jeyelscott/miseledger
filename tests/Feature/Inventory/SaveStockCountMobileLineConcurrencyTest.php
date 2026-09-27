<?php

use App\Actions\Inventory\SaveStockCount;
use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockCount;
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

    $this->location = Location::factory()
        ->for($this->organization)
        ->create(['active' => true]);

    $this->storageLocation = new StorageLocation([
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'active' => true,
    ]);
    $this->storageLocation->organization()->associate($this->organization);
    $this->storageLocation->location()->associate($this->location);
    $this->storageLocation->save();

    $this->countUnit = UnitOfMeasure::factory()
        ->for($this->organization)
        ->create(['dimension' => 'count', 'active' => true]);

    $this->itemA = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->countUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->itemB = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->countUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->itemC = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->countUnit->id,
        'type' => InventoryItemType::Ingredient,
        'active' => true,
    ]);

    $this->count = app(SaveStockCount::class)->handle(
        $this->organization,
        $this->owner,
        [
            'number' => 'SC-MOBILE-RACE-001',
            'location_id' => $this->location->id,
            'storage_location_id' => $this->storageLocation->id,
            'lines' => [[
                'inventory_item_id' => $this->itemA->id,
                'counted_quantity' => '1.000000',
                'count_unit_id' => $this->countUnit->id,
            ]],
        ],
    );
});

test('two concurrent mobile scans against the same draft stock count both land instead of one silently overwriting the other', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    $countId = $this->count->id;
    $organization = $this->organization;
    $owner = $this->owner;
    $locationId = $this->location->id;
    $storageLocationId = $this->storageLocation->id;
    $itemBId = $this->itemB->id;
    $itemCId = $this->itemC->id;
    $unitId = $this->countUnit->id;

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-mobile-count-line-race-');

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
                $countId,
                $locationId,
                $storageLocationId,
                $itemBId,
                $unitId,
            ): void {
                // Acquire the same row lock `addMobileLine()` acquires, then
                // hold it briefly to force the parent process's concurrent
                // scan to block on it, widening the race window that the
                // pre-fix unlocked read-then-replace was vulnerable to.
                $count = StockCount::query()
                    ->whereKey($countId)
                    ->lockForUpdate()
                    ->firstOrFail();

                usleep(500000);

                app(SaveStockCount::class)->addMobileLine(
                    $organization,
                    $owner,
                    [
                        'number' => $count->number,
                        'location_id' => $locationId,
                        'storage_location_id' => $storageLocationId,
                    ],
                    [
                        'inventory_item_id' => $itemBId,
                        'counted_quantity' => '2.000000',
                        'count_unit_id' => $unitId,
                        'notes' => null,
                    ],
                    $count,
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

    app(SaveStockCount::class)->addMobileLine(
        $organization,
        $owner,
        [
            'number' => $this->count->number,
            'location_id' => $locationId,
            'storage_location_id' => $storageLocationId,
        ],
        [
            'inventory_item_id' => $itemCId,
            'counted_quantity' => '3.000000',
            'count_unit_id' => $unitId,
            'notes' => null,
        ],
        $this->count,
    );

    $childReaped = false;

    try {
        pcntl_waitpid($childPid, $status);
        $childReaped = true;

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0)
            ->and(file_get_contents($resultPath))->toBe('success');

        $lines = StockCount::query()
            ->whereKey($countId)
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
