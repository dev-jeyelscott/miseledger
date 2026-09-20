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
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->sourceLocation = Location::factory()
        ->for($this->organization)
        ->create([
            'name' => 'Source Restaurant',
            'code' => 'SOURCE',
            'active' => true,
        ]);

    $this->destinationLocation = Location::factory()
        ->for($this->organization)
        ->create([
            'name' => 'Destination Restaurant',
            'code' => 'DEST',
            'active' => true,
        ]);

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
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

    $this->inventoryItem = InventoryItem::factory()
        ->for($this->organization)
        ->create([
            'base_unit_of_measure_id' => $this->baseUnit->id,
            'name' => 'Transfer Ingredient',
            'sku' => 'CONCURRENCY-ITEM',
            'type' => InventoryItemType::Ingredient,
            'yield_percentage' => '100.00',
            'active' => true,
        ]);
});

test('draft transfer creation is serialized against a concurrent destination-location deactivation', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-location-deactivation-');

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
            DB::transaction(function (): void {
                $lockedLocation = Location::query()
                    ->whereKey($this->destinationLocation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Hold the row lock briefly to widen the race window so the
                // parent process's transfer creation must wait on it.
                usleep(500000);

                $lockedLocation->update(['active' => false]);
            });

            file_put_contents($resultPath, 'success');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultPath, get_class($exception));
            exit(1);
        }
    }

    // Give the child a head start so it acquires the location row lock
    // before this process attempts to create the draft transfer.
    usleep(100000);

    $thrown = null;

    try {
        app(SaveStockTransfer::class)->handle(
            $this->organization,
            $this->owner,
            [
                'number' => 'TR-CONCURRENCY-001',
                'from_location_id' => $this->sourceLocation->id,
                'from_storage_location_id' => $this->sourceStorage->id,
                'to_location_id' => $this->destinationLocation->id,
                'to_storage_location_id' => $this->destinationStorage->id,
                'notes' => null,
                'lines' => [
                    [
                        'inventory_item_id' => $this->inventoryItem->id,
                        'requested_quantity' => '4.000000',
                        'unit_id' => $this->baseUnit->id,
                    ],
                ],
            ],
        );
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    $childReaped = false;

    try {
        pcntl_waitpid($childPid, $status);
        $childReaped = true;

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0)
            ->and(file_get_contents($resultPath))->toBe('success')
            ->and($thrown)->not->toBeNull()
            ->and($thrown->errors())->toHaveKey('to_location_id')
            ->and($this->destinationLocation->refresh()->active)->toBeFalse()
            ->and(
                StockTransfer::query()
                    ->where('organization_id', $this->organization->id)
                    ->where('to_location_id', $this->destinationLocation->id)
                    ->exists(),
            )->toBeFalse();
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
