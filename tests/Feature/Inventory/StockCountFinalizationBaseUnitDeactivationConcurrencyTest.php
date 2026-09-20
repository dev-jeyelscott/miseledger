<?php

use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation = new StorageLocation;
    $this->storageLocation->organization_id = $this->organization->id;
    $this->storageLocation->location_id = $this->location->id;
    $this->storageLocation->name = 'Storage COUNT';
    $this->storageLocation->code = 'COUNT';
    $this->storageLocation->active = true;
    $this->storageLocation->save();

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Concurrency Count Item',
        'sku' => 'COUNT-CONCURRENCY',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->inventoryItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '1000',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'opening_balance',
        referenceId: $this->inventoryItem->id,
        occurredAt: now()->subSecond(),
        idempotencyKey: "stock-count-concurrency-test:opening:{$this->inventoryItem->id}",
        inboundUnitCost: '0.25',
    );

    $count = app(SaveStockCount::class)->handle(
        $this->organization,
        $this->actor,
        [
            'number' => 'COUNT-CONCURRENCY-001',
            'location_id' => $this->location->id,
            'storage_location_id' => $this->storageLocation->id,
            'lines' => [
                [
                    'inventory_item_id' => $this->inventoryItem->id,
                    'counted_quantity' => '1200',
                    'count_unit_id' => $this->baseUnit->id,
                    'notes' => null,
                ],
            ],
        ],
    );

    $this->stockCount = app(SubmitStockCount::class)->handle(
        $this->organization,
        $this->actor,
        $count,
    );
});

test('stock-count finalization is serialized against a concurrent base-unit deactivation', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-base-unit-deactivation-');

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
                $lockedUnit = UnitOfMeasure::query()
                    ->whereKey($this->baseUnit->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Hold the row lock briefly to widen the race window so the
                // parent process's finalization must wait on it.
                usleep(500000);

                $lockedUnit->update(['active' => false]);
            });

            file_put_contents($resultPath, 'success');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultPath, get_class($exception));
            exit(1);
        }
    }

    // Give the child a head start so it acquires the base-unit row lock
    // before this process attempts to finalize the stock count.
    usleep(100000);

    $thrown = null;

    try {
        app(FinalizeStockCount::class)->handle(
            $this->organization,
            $this->actor,
            $this->stockCount,
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
            ->and($thrown->errors())->toHaveKey('stock_count')
            ->and($this->baseUnit->refresh()->active)->toBeFalse()
            ->and($this->stockCount->refresh()->status->value)->toBe('submitted')
            ->and(
                StockMovement::query()
                    ->where('organization_id', $this->organization->id)
                    ->where('reference_type', 'stock_count_line')
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
